# DB extractor adapter

This library contains a common interface for connecting to and data extracting from, various sources:
 - It is intended for use with [db-extractor-common](https://github.com/keboola/db-extractor-common).
 - It supports **PDO** and **ODBC** connections for now.
 - The interfaces defined in this library can be easily used to support other methods, e.g. cli BCP tool.
 
## Main Classes

- **Interface [`DbConnection`](https://github.com/keboola/db-extractor-adapter/blob/master/src/Connection/DbConnection.php)** is an abstraction that represents a connection to the database.
    - Abstract class [`BaseDbConnection`](https://github.com/keboola/db-extractor-adapter/blob/master/src/Connection/BaseDbConnection.php) contains common code and retry mechanisms.
    - Class [`PdoDbConnection`](https://github.com/keboola/db-extractor-adapter/blob/master/src/PDO/PdoConnection.php) implements connection using PDO extension. 
    - Class [`OdbcDbConnection`](https://github.com/keboola/db-extractor-adapter/blob/master/src/ODBC/OdbcConnection.php) implements connection using ODBC extension.
- **Interface [`QueryResult`](https://github.com/keboola/db-extractor-adapter/blob/master/src/ValueObject/QueryResult.php)** is an abstraction that represents query result - rows returned from database.
    - Class [`PdoQueryResult`](https://github.com/keboola/db-extractor-adapter/blob/master/src/PDO/PdoQueryResult.php) represents result from PDO connection.
    - Class [`OdbcQueryResult`](https://github.com/keboola/db-extractor-adapter/blob/master/src/ODBC/OdbcQueryResult.php) represents result from ODBC connection.
- **Interface [`ExportAdapter`](https://github.com/keboola/db-extractor-adapter/blob/master/src/ExportAdapter.php)**  is an abstraction which defines how the data is to be extracted.
    - Based on `ExportConfig`, [`ExportResult`](https://github.com/keboola/db-extractor-adapter/blob/master/src/ValueObject/ExportResult.php) is generated. The rows are written to the specified CSV file.
    - By implementing this interface, it is possible to add support for CLI tools for export.
    - Abstract class [`BaseExportAdapter`](https://github.com/keboola/db-extractor-adapter/blob/master/src/BaseExportAdapter.php) contains common code.
    - Class [`PdoExportAdapter`](https://github.com/keboola/db-extractor-adapter/blob/master/src/PDO/PdoExportAdapter.php) implements export for PDO connection.
    - Class [`OdbcExportAdapter`](https://github.com/keboola/db-extractor-adapter/blob/master/src/ODBC/OdbcExportAdapter.php) implements export for ODBC connection.
    - Class [`FallbackExportAdapter`](https://github.com/keboola/db-extractor-adapter/blob/master/src/FallbackExportAdapter.php) allows you to use multiple adapters. If one fails, then fallback adapter is used.
- **Interface [`QueryFactory`](https://github.com/keboola/db-extractor-adapter/blob/master/src/Query/QueryFactory.php)** used to generate SQL query from `ExportConfig`. It is used if query is not set in the config.
    - Class [`DefaultQueryFactory`](https://github.com/keboola/db-extractor-adapter/blob/master/src/Query/DefaultQueryFactory.php) is base implementation for MySQL/MariaDb compatible SQL dialects. 
- **Class [`QueryResultCsvWriter`](https://github.com/keboola/db-extractor-adapter/blob/master/src/QueryResultCsvWriter.php)** used to write rows from the `QueryResult` to the specified CSV file. 

## Incremental Fetching

When `incrementalFetchingColumn` is set, [`DefaultQueryFactory`](https://github.com/keboola/db-extractor-adapter/blob/master/src/Query/DefaultQueryFactory.php)
builds the `WHERE` clause from the incremental-fetching config on `ExportConfig`. The dialect-specific
factories in the individual extractors either inherit this logic or mirror it. The lower/upper bounds
are resolved by [`WindowBoundResolver`](https://github.com/keboola/db-extractor-config/blob/master/src/Incremental/WindowBoundResolver.php)
(from `db-extractor-config`) and quoted for the target column type (`INTEGER`, `NUMERIC`, `FLOAT` or
`TIMESTAMP`).

`incrementalFetchingMode` selects the strategy. It is **optional and defaults to `watermark`**, so
configs without it produce exactly the same query as before this feature was added.

- **`watermark`** (default) — `WHERE column >= <last fetched value>` (the value stored in state). On the
  first run there is no watermark yet, so no `WHERE` is emitted (full fetch).
  - `incrementalFetchingLookback` *(optional)* lowers that bound by a fixed margin —
    `column >= (watermark − N)` — so a row committed slightly after its own timestamp is re-scanned on a
    later run. It is a **duration** for `TIMESTAMP` (e.g. `"20 minutes"`) or a **number** for numeric
    columns. Being watermark-anchored, it has no dependency on the current time.

- **`window`** — `column >= start [AND column <= end]`, **ignoring** the watermark, for a bounded or
  segmented backfill. `incrementalFetchingStart` / `incrementalFetchingEnd` accept a relative
  (`"20 minutes ago"`, `"now"`) or absolute (`"2021-01-01"`, `"1000"`) value. **At least one bound is
  required** — window mode with neither `incrementalFetchingStart` nor `incrementalFetchingEnd` throws a
  `UserException` rather than silently degrading to an unfiltered full-table scan.

The modes are mutually exclusive; keys belonging to the other mode are ignored. Cross-cutting validation
(e.g. requiring a primary key when a lookback/window re-fetches rows) lives in
[`db-extractor-common`](https://github.com/keboola/db-extractor-common).

## Development

Clone this repository and init the workspace with following command:

```
git clone https://github.com/keboola/db-extractor-adapter
cd db-extractor-adapter
docker compose build
docker compose run --rm dev composer install --no-scripts
```

Run the test suite using this command:

```
docker compose run --rm dev composer tests
```

## License

MIT licensed, see [LICENSE](./LICENSE) file.
