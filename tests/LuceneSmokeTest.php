<?php

declare(strict_types=1);

namespace ZendSearch\Tests;

use PHPUnit\Framework\TestCase;
use Zend\Search\Lucene\Document;
use Zend\Search\Lucene\Document\Field;
use Zend\Search\Lucene\Lucene;

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

        self::assertTrue($document->hasField('identifier'));
        self::assertTrue($document->hasField('title'));
        self::assertSame('42', $document->getFieldValue('identifier'));
        self::assertSame('Stored title', $document->getFieldValue('title'));
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
