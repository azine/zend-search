# Zend Search Lucene maintenance fork

This repository is the maintained `excelwebzone/zend-search` fork used by Azine applications.

## Requirements

- PHP 8.5
- `ext-iconv`
- `ext-mbstring`

## Installation

```bash
composer require excelwebzone/zend-search:^2.0
```

## Compatibility

The PHP 8.5 upgrade intentionally leaves the `Zend\Search\Lucene` implementation and on-disk index format unchanged. The upgrade changes package metadata, runtime requirements, tests and CI only. Existing indexes created by the previous 1.x release therefore remain compatible; applications may still choose to rebuild indexes as an operational validation step.

The public API remains the existing `Zend\Search\Lucene` API, including `Lucene::create()`, `Lucene::open()`, document fields and query execution.

## Development

```bash
composer update
composer lint
composer test
```

CI validates Composer metadata, lints the complete PHP source tree and runs the Lucene create/write/search/reopen smoke tests on PHP 8.5.

## Upgrade from 1.x

1. Upgrade the runtime to PHP 8.5 and enable `iconv` and `mbstring`.
2. Require `excelwebzone/zend-search:^2.0`.
3. Run the application search smoke tests against a copy of the existing index.
4. Keep an index backup available during the application deployment even though the index format itself is unchanged.
