<?php

declare(strict_types=1);

namespace ZendSearch\Tests;

use PHPUnit\Framework\TestCase;
use Zend\Search\Lucene\Document;
use Zend\Search\Lucene\Document\Field;
use Zend\Search\Lucene\Lucene;
use Zend\Search\Lucene\Index\Term;
use Zend\Search\Lucene\Search\Query\Boolean as BooleanQuery;
use Zend\Search\Lucene\Search\Query\MultiTerm;
use Zend\Search\Lucene\Search\Query\Term as TermQuery;

final class LuceneSmokeTest extends TestCase
{
    private string $indexDirectory;

    protected function setUp(): void
    {
        $this->indexDirectory = sys_get_temp_dir().'/azine-zend-search-'.bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->indexDirectory);
    }

    public function testIndexCanBeCreatedSearchedAndReopened(): void
    {
        $index = Lucene::create($this->indexDirectory);

        $document = new Document();
        $document->addField(Field::keyword('identifier', 'azine-homepage'));
        $document->addField(Field::text('title', 'Azine freelancer platform'));
        $document->addField(Field::unStored('body', 'Symfony consultants and software projects in Zurich'));
        $index->addDocument($document);
        $index->commit();

        self::assertSame(1, $index->count());

        $hits = $index->find('consultants');
        self::assertCount(1, $hits);
        self::assertSame('azine-homepage', $hits[0]->identifier);
        self::assertSame('Azine freelancer platform', $hits[0]->title);

        unset($index);

        $reopenedIndex = Lucene::open($this->indexDirectory);
        $reopenedHits = $reopenedIndex->find('Zurich');

        self::assertCount(1, $reopenedHits);
        self::assertSame('azine-homepage', $reopenedHits[0]->identifier);
    }

    public function testDocumentFieldsRetainStoredValues(): void
    {
        $document = new Document();
        $document->addField(Field::keyword('identifier', '42'));
        $document->addField(Field::text('title', 'Stored title'));

        self::assertSame(['identifier', 'title'], $document->getFieldNames());
        self::assertTrue(isset($document->identifier));
        self::assertTrue(isset($document->title));
        self::assertSame('42', $document->getFieldValue('identifier'));
        self::assertSame('Stored title', $document->getFieldValue('title'));
        self::assertSame('42', $document->identifier);
        self::assertSame('Stored title', $document->title);
    }

    public function testNestedConjunctionPreservesTermIdsAndWeights(): void
    {
        $index = Lucene::create($this->indexDirectory);
        foreach (['common rare', 'common', 'common'] as $body) {
            $document = new Document();
            $document->addField(Field::text('body', $body));
            $document->addField(Field::keyword('type', 'job'));
            $index->addDocument($document);
        }
        $index->commit();

        // Optimizing boolean queries can leave gaps where prohibited terms were
        // removed. Parent queries initialize weights before child execution.
        $terms = [1 => new Term('common', 'body'), 3 => new Term('rare', 'body')];
        $conjunction = new MultiTerm($terms);
        $query = new BooleanQuery([$conjunction, new TermQuery(new Term('job', 'type'))], [true, true]);
        $query->execute($index);

        self::assertSame($terms, $conjunction->getTerms());
        self::assertSame([0], array_keys($query->matchedDocs()));
        $score = $query->score(0, $index);
        self::assertGreaterThan(0, $score);
        $query->execute($index);
        self::assertSame($score, $query->score(0, $index));
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($directory);
    }
}
