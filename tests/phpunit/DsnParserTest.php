<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Adapter\Tests;

use Keboola\DbExtractor\Adapter\DsnParser;
use PHPUnit\Framework\TestCase;

class DsnParserTest extends TestCase
{
    /** @phpcs:disable Generic.Files.LineLength */
    protected static function provideLocalDSNs(): iterable
    {
        // MySQL (PDO)
        yield 'MySQL PDO' => [
            'mysql:host=127.0.0.1;port=3306;dbname=testdb',
        ];
        yield 'MySQL PDO (localhost)' => [
            'mysql:host=localhost;port=3306;dbname=testdb',
        ];
        yield 'MySQL PDO (local ipv6)' => [
            'mysql:host=[::1];port=3306;dbname=testdb',
        ];

        // MySQL (ODBC)
        yield 'MySQL ODBC' => [
            'Driver={MySQL ODBC 8.0 Driver};Server=127.0.0.1;Port=3306;Database=testdb;User=root;Password=pass;',
        ];
        yield 'MySQL ODBC (localhost)' => [
            'Driver={MySQL ODBC 8.0 Driver};Server=localhost;Port=3306;Database=testdb;User=root;Password=pass;',
        ];
        yield 'MySQL ODBC (local ipv6)' => [
            'Driver={MySQL ODBC 8.0 Driver};Server=[::1];Port=3306;Database=testdb;User=root;Password=pass;',
        ];

        // MariaDB (PDO)
        yield 'MariaDB PDO' => [
            'mysql:host=127.0.0.1;port=3306;dbname=testdb',
        ];
        yield 'MariaDB PDO (localhost)' => [
            'mysql:host=localhost;port=3306;dbname=testdb',
        ];
        yield 'MariaDB PDO (local ipv6)' => [
            'mysql:host=[::1];port=3306;dbname=testdb',
        ];

        // MariaDB (ODBC)
        yield 'MariaDB ODBC' => [
            'Driver={MariaDB ODBC 3.1 Driver};Server=127.0.0.1;Port=3306;Database=testdb;User=root;Password=pass;',
        ];
        yield 'MariaDB ODBC (localhost)' => [
            'Driver={MariaDB ODBC 3.1 Driver};Server=localhost;Port=3306;Database=testdb;User=root;Password=pass;',
        ];
        yield 'MariaDB ODBC (local ipv6)' => [
            'Driver={MariaDB ODBC 3.1 Driver};Server=[::1];Port=3306;Database=testdb;User=root;Password=pass;',
        ];

        // PostgreSQL (PDO)
        yield 'PostgreSQL PDO' => [
            'pgsql:host=127.0.0.1;port=5432;dbname=testdb',
        ];
        yield 'PostgreSQL PDO (localhost)' => [
            'pgsql:host=localhost;port=5432;dbname=testdb',
        ];
        yield 'PostgreSQL PDO (local ipv6)' => [
            'pgsql:host=[::1];port=5432;dbname=testdb',
        ];

        // PostgreSQL (ODBC)
        yield 'PostgreSQL ODBC' => [
            'Driver={PostgreSQL Unicode};Server=127.0.0.1;Port=5432;Database=testdb;Uid=postgres;Pwd=pass;',
        ];
        yield 'PostgreSQL ODBC (localhost)' => [
            'Driver={PostgreSQL Unicode};Server=localhost;Port=5432;Database=testdb;Uid=postgres;Pwd=pass;',
        ];
        yield 'PostgreSQL ODBC (local ipv6)' => [
            'Driver={PostgreSQL Unicode};Server=[::1];Port=5432;Database=testdb;Uid=postgres;Pwd=pass;',
        ];

        // DB2 (PDO)
        yield 'DB2 PDO' => [
            'ibm:DRIVER={IBM DB2 ODBC DRIVER};DATABASE=testdb;HOSTNAME=127.0.0.1;PORT=50000;PROTOCOL=TCPIP;',
        ];
        yield 'DB2 PDO (localhost)' => [
            'ibm:DRIVER={IBM DB2 ODBC DRIVER};DATABASE=testdb;HOSTNAME=localhost;PORT=50000;PROTOCOL=TCPIP;',
        ];
        yield 'DB2 PDO (local ipv6)' => [
            'ibm:DRIVER={IBM DB2 ODBC DRIVER};DATABASE=testdb;HOSTNAME=[::1];PORT=50000;PROTOCOL=TCPIP;',
        ];

        // DB2 (ODBC)
        yield 'DB2 ODBC' => [
            'Driver={IBM DB2 ODBC DRIVER};Database=testdb;Hostname=127.0.0.1;Port=50000;Protocol=TCPIP;Uid=db2user;Pwd=pass;',
        ];
        yield 'DB2 ODBC (localhost)' => [
            'Driver={IBM DB2 ODBC DRIVER};Database=testdb;Hostname=localhost;Port=50000;Protocol=TCPIP;Uid=db2user;Pwd=pass;',
        ];
        yield 'DB2 ODBC (local ipv6)' => [
            'Driver={IBM DB2 ODBC DRIVER};Database=testdb;Hostname=[::1];Port=50000;Protocol=TCPIP;Uid=db2user;Pwd=pass;',
        ];

        // Microsoft SQL Server (PDO)
        yield 'MSSQL PDO SQLSRV' => [
            'sqlsrv:Server=127.0.0.1,1433;Database=testdb',
        ];
        yield 'MSSQL PDO SQLSRV (localhost)' => [
            'sqlsrv:Server=localhost,1433;Database=testdb',
        ];
        yield 'MSSQL PDO SQLSRV (local ipv6)' => [
            'sqlsrv:Server=[::1],1433;Database=testdb',
        ];
        yield 'MSSQL PDO DBLIB' => [
            'dblib:host=127.0.0.1:1433;dbname=testdb',
        ];
        yield 'MSSQL PDO DBLIB (localhost)' => [
            'dblib:host=localhost:1433;dbname=testdb',
        ];
        yield 'MSSQL PDO DBLIB (local ipv6)' => [
            'dblib:host=[::1]:1433;dbname=testdb',
        ];

        // Microsoft SQL Server (ODBC)
        yield 'MSSQL ODBC' => [
            'Driver={ODBC Driver 17 for SQL Server};Server=127.0.0.1,1433;Database=testdb;Uid=sa;Pwd=pass;',
        ];
        yield 'MSSQL ODBC (localhost)' => [
            'Driver={ODBC Driver 17 for SQL Server};Server=localhost,1433;Database=testdb;Uid=sa;Pwd=pass;',
        ];
        yield 'MSSQL ODBC (local ipv6)' => [
            'Driver={ODBC Driver 17 for SQL Server};Server=[::1],1433;Database=testdb;Uid=sa;Pwd=pass;',
        ];

        // Hive (ODBC)
        yield 'Hive ODBC' => [
            'Driver={Cloudera ODBC Driver for Apache Hive};Host=127.0.0.1;Port=10000;Schema=default;Uid=hiveuser;Pwd=pass;',
        ];
        yield 'Hive ODBC (localhost)' => [
            'Driver={Cloudera ODBC Driver for Apache Hive};Host=localhost;Port=10000;Schema=default;Uid=hiveuser;Pwd=pass;',
        ];
        yield 'Hive ODBC (local ipv6)' => [
            'Driver={Cloudera ODBC Driver for Apache Hive};Host=[::1];Port=10000;Schema=default;Uid=hiveuser;Pwd=pass;',
        ];

        // Oracle (PDO)
        yield 'Oracle PDO' => [
            'oci:dbname=//127.0.0.1:1521/XE',
        ];
        yield 'Oracle PDO (localhost)' => [
            'oci:dbname=//localhost:1521/XE',
        ];
        yield 'Oracle PDO (local ipv6)' => [
            'oci:dbname=//[::1]:1521/XE',
        ];

        // Oracle (ODBC)
        yield 'Oracle ODBC' => [
            'Driver={Oracle in OraClient11g_home1};Dbq=127.0.0.1:1521/XE;Uid=oracleuser;Pwd=pass;',
        ];
        yield 'Oracle ODBC (localhost)' => [
            'Driver={Oracle in OraClient11g_home1};Dbq=localhost:1521/XE;Uid=oracleuser;Pwd=pass;',
        ];
        yield 'Oracle ODBC (local ipv6)' => [
            'Driver={Oracle in OraClient11g_home1};Dbq=[::1]:1521/XE;Uid=oracleuser;Pwd=pass;',
        ];
    }

    /** @dataProvider provideLocalDSNs */
    public function testDetectLocalDSNs(string $dsn): void
    {
        $parsedDsn = new DsnParser($dsn);
        self::assertTrue($parsedDsn->isLocal());
    }

    /** @phpcs:disable Generic.Files.LineLength */
    protected static function provideRemoteDSNs(): iterable
    {
        // MySQL (PDO)
        yield 'MySQL PDO' => [
            'mysql:host=example.com;port=3306;dbname=testdb',
        ];

        // MySQL (ODBC)
        yield 'MySQL ODBC' => [
            'Driver={MySQL ODBC 8.0 Driver};Server=88.208.120.45;Port=3306;Database=testdb;User=root;Password=pass;',
        ];

        // MariaDB (PDO)
        yield 'MariaDB PDO' => [
            'mysql:host=89.24.80.123;port=3306;dbname=testdb',
        ];

        // MariaDB (ODBC)
        yield 'MariaDB ODBC' => [
            'Driver={MariaDB ODBC 3.1 Driver};Server=example.com;Port=3306;Database=testdb;User=root;Password=pass;',
        ];

        // PostgreSQL (PDO)
        yield 'PostgreSQL PDO' => [
            'pgsql:host=94.112.180.92;port=5432;dbname=testdb',
        ];

        // PostgreSQL (ODBC)
        yield 'PostgreSQL ODBC' => [
            'Driver={PostgreSQL Unicode};Server=195.113.189.34;Port=5432;Database=testdb;Uid=postgres;Pwd=pass;',
        ];

        // DB2 (PDO)
        yield 'DB2 PDO' => [
            'ibm:DRIVER={IBM DB2 ODBC DRIVER};DATABASE=testdb;HOSTNAME=85.162.45.118;PORT=50000;PROTOCOL=TCPIP;',
        ];

        // DB2 (ODBC)
        yield 'DB2 ODBC' => [
            'Driver={IBM DB2 ODBC DRIVER};Database=testdb;Hostname=db2.example.com;Port=50000;Protocol=TCPIP;Uid=db2user;Pwd=pass;',
        ];

        // Microsoft SQL Server (PDO)
        yield 'MSSQL PDO SQLSRV' => [
            'sqlsrv:Server=81.0.216.55,1433;Database=testdb',
        ];
        yield 'MSSQL PDO DBLIB' => [
            'dblib:host=sql.abc123.cz:1433;dbname=testdb',
        ];

        // Microsoft SQL Server (ODBC)
        yield 'MSSQL ODBC' => [
            'Driver={ODBC Driver 17 for SQL Server};Server=178.255.168.231,1433;Database=testdb;Uid=sa;Pwd=pass;',
        ];

        // Hive (ODBC)
        yield 'Hive ODBC' => [
            'Driver={Cloudera ODBC Driver for Apache Hive};Host=51.148.58.234;Port=10000;Schema=default;Uid=hiveuser;Pwd=pass;',
        ];

        // Oracle (PDO)
        yield 'Oracle PDO' => [
            'oci:dbname=//81.2.69.192:1521/XE',
        ];

        // Oracle (ODBC)
        yield 'Oracle ODBC' => [
            'Driver={Oracle in OraClient11g_home1};Dbq=109.176.212.12:1521/XE;Uid=oracleuser;Pwd=pass;',
        ];
    }

    /** @dataProvider provideRemoteDSNs */
    public function testDetectRemoteDSNs(string $dsn): void
    {
        $parsedDsn = new DsnParser($dsn);
        self::assertFalse($parsedDsn->isLocal());
    }

    /** @phpcs:disable Generic.Files.LineLength */
    protected static function provideDSNsToParsePorts(): iterable
    {
        // MySQL (PDO)
        yield 'MySQL PDO (local)' => [
            'dsn' => 'mysql:host=127.0.0.1;port=3306;dbname=testdb',
            'local' => true,
            'port' => 3306,
        ];
        yield 'MySQL PDO (remote)' => [
            'dsn' => 'mysql:host=subdomain.example.co.uk;port=3306;dbname=testdb',
            'local' => false,
            'port' => 3306,
        ];

        // MySQL (ODBC)
        yield 'MySQL ODBC (local)' => [
            'dsn' => 'Driver={MySQL ODBC 8.0 Driver};Server=127.0.0.1;Port=3306;Database=testdb;User=root;Password=pass;',
            'local' => true,
            'port' => 3306,
        ];
        yield 'MySQL ODBC (remote)' => [
            'dsn' => 'Driver={MySQL ODBC 8.0 Driver};Server=82.12.42.81;Port=3306;Database=testdb;User=root;Password=pass;',
            'local' => false,
            'port' => 3306,
        ];

        // MariaDB (PDO)
        yield 'MariaDB PDO (local)' => [
            'dsn' => 'mysql:host=127.0.0.1;port=3306;dbname=testdb',
            'local' => true,
            'port' => 3306,
        ];
        yield 'MariaDB PDO (remote)' => [
            'dsn' => 'mysql:host=77.102.195.180;port=3306;dbname=testdb',
            'local' => false,
            'port' => 3306,
        ];

        // MariaDB (ODBC)
        yield 'MariaDB ODBC (local)' => [
            'dsn' => 'Driver={MariaDB ODBC 3.1 Driver};Server=127.0.0.1;Port=3306;Database=testdb;User=root;Password=pass;',
            'local' => true,
            'port' => 3306,
        ];
        yield 'MariaDB ODBC (remote)' => [
            'dsn' => 'Driver={MariaDB ODBC 3.1 Driver};Server=62.172.97.30;Port=3306;Database=testdb;User=root;Password=pass;',
            'local' => false,
            'port' => 3306,
        ];

        // PostgreSQL (PDO)
        yield 'PostgreSQL PDO (local)' => [
            'dsn' => 'pgsql:host=127.0.0.1;port=5432;dbname=testdb',
            'local' => true,
            'port' => 5432,
        ];
        yield 'PostgreSQL PDO (remote)' => [
            'dsn' => 'pgsql:host=185.38.44.201;port=5432;dbname=testdb',
            'local' => false,
            'port' => 5432,
        ];

        // PostgreSQL (ODBC)
        yield 'PostgreSQL ODBC (local)' => [
            'dsn' => 'Driver={PostgreSQL Unicode};Server=127.0.0.1;Port=5432;Database=testdb;Uid=postgres;Pwd=pass;',
            'local' => true,
            'port' => 5432,
        ];
        yield 'PostgreSQL ODBC (remote)' => [
            'dsn' => 'Driver={PostgreSQL Unicode};Server=34.202.188.100;Port=5432;Database=testdb;Uid=postgres;Pwd=pass;',
            'local' => false,
            'port' => 5432,
        ];

        // DB2 (PDO)
        yield 'DB2 PDO (local)' => [
            'dsn' => 'ibm:DRIVER={IBM DB2 ODBC DRIVER};DATABASE=testdb;HOSTNAME=127.0.0.1;PORT=50000;PROTOCOL=TCPIP;',
            'local' => true,
            'port' => 50000,
        ];
        yield 'DB2 PDO (remote)' => [
            'dsn' => 'ibm:DRIVER={IBM DB2 ODBC DRIVER};DATABASE=testdb;HOSTNAME=52.27.120.74;PORT=50000;PROTOCOL=TCPIP;',
            'local' => false,
            'port' => 50000,
        ];

        // DB2 (ODBC)
        yield 'DB2 ODBC (local)' => [
            'dsn' => 'Driver={IBM DB2 ODBC DRIVER};Database=testdb;Hostname=127.0.0.1;Port=50000;Protocol=TCPIP;Uid=db2user;Pwd=pass;',
            'local' => true,
            'port' => 50000,
        ];
        yield 'DB2 ODBC (remote)' => [
            'dsn' => 'Driver={IBM DB2 ODBC DRIVER};Database=testdb;Hostname=104.244.42.129;Port=50000;Protocol=TCPIP;Uid=db2user;Pwd=pass;',
            'local' => false,
            'port' => 50000,
        ];

        // Snowflake (ODBC)
        yield 'Snowflake ODBC' => [
            'dsn' => 'Driver={SnowflakeDSIIDriver};Server=abc123.snowflakecomputing.com;Warehouse=COMPUTE_WH;Database=MYDB;Schema=PUBLIC;Uid=user;Pwd=pass;',
            'local' => false,
            'port' => null,
        ];

        // Microsoft SQL Server (PDO)
        yield 'MSSQL PDO SQLSRV (local)' => [
            'dsn' => 'sqlsrv:Server=127.0.0.1,1433;Database=testdb',
            'local' => true,
            'port' => 1433,
        ];
        yield 'MSSQL PDO SQLSRV (localhost)' => [
            'dsn' => 'sqlsrv:Server=localhost,1433;Database=testdb',
            'local' => true,
            'port' => 1433,
        ];
        yield 'MSSQL PDO SQLSRV (local ipv6)' => [
            'dsn' => 'sqlsrv:Server=[::1],1433;Database=testdb',
            'local' => true,
            'port' => 1433,
        ];
        yield 'MSSQL PDO SQLSRV (remote)' => [
            'dsn' => 'sqlsrv:Server=66.249.93.180,1433;Database=testdb',
            'local' => false,
            'port' => 1433,
        ];
        yield 'MSSQL PDO DBLIB (local)' => [
            'dsn' => 'dblib:host=127.0.0.1:1433;dbname=testdb',
            'local' => true,
            'port' => 1433,
        ];
        yield 'MSSQL PDO DBLIB (localhost)' => [
            'dsn' => 'dblib:host=localhost:1433;dbname=testdb',
            'local' => true,
            'port' => 1433,
        ];
        yield 'MSSQL PDO DBLIB (local ipv6)' => [
            'dsn' => 'dblib:host=[::1]:1433;dbname=testdb',
            'local' => true,
            'port' => 1433,
        ];
        yield 'MSSQL PDO DBLIB (remote)' => [
            'dsn' => 'dblib:host=sql.foo.example.de:1433;dbname=testdb',
            'local' => false,
            'port' => 1433,
        ];

        // Microsoft SQL Server (ODBC)
        yield 'MSSQL ODBC (local)' => [
            'dsn' => 'Driver={ODBC Driver 17 for SQL Server};Server=127.0.0.1,1433;Database=testdb;Uid=sa;Pwd=pass;',
            'local' => true,
            'port' => 1433,
        ];
        yield 'MSSQL ODBC (localhost)' => [
            'dsn' => 'Driver={ODBC Driver 17 for SQL Server};Server=localhost,1433;Database=testdb;Uid=sa;Pwd=pass;',
            'local' => true,
            'port' => 1433,
        ];
        yield 'MSSQL ODBC (local ipv6)' => [
            'dsn' => 'Driver={ODBC Driver 17 for SQL Server};Server=::1,1433;Database=testdb;Uid=sa;Pwd=pass;',
            'local' => true,
            'port' => 1433,
        ];
        yield 'MSSQL ODBC (remote)' => [
            'dsn' => 'Driver={ODBC Driver 17 for SQL Server};Server=192.0.2.146,1433;Database=testdb;Uid=sa;Pwd=pass;',
            'local' => false,
            'port' => 1433,
        ];

        // Hive (ODBC)
        yield 'Hive ODBC (local)' => [
            'dsn' => 'Driver={Cloudera ODBC Driver for Apache Hive};Host=127.0.0.1;Port=10000;Schema=default;Uid=hiveuser;Pwd=pass;',
            'local' => true,
            'port' => 10000,
        ];
        yield 'Hive ODBC (remote)' => [
            'dsn' => 'Driver={Cloudera ODBC Driver for Apache Hive};Host=204.79.197.200;Port=10000;Schema=default;Uid=hiveuser;Pwd=pass;',
            'local' => false,
            'port' => 10000,
        ];

        // Oracle (PDO)
        yield 'Oracle PDO (local)' => [
            'dsn' => 'oci:dbname=//127.0.0.1:1521/XE',
            'local' => true,
            'port' => 1521,
        ];
        yield 'Oracle PDO (localhost)' => [
            'dsn' => 'oci:dbname=//localhost:1521/XE',
            'local' => true,
            'port' => 1521,
        ];
        yield 'Oracle PDO (local ipv6)' => [
            'dsn' => 'oci:dbname=//[::1]:1521/XE',
            'local' => true,
            'port' => 1521,
        ];
        yield 'Oracle PDO (remote)' => [
            'dsn' => 'oci:dbname=//db.uk.spacex.com:1521/XE',
            'local' => false,
            'port' => 1521,
        ];

        // Oracle (ODBC)
        yield 'Oracle ODBC (local)' => [
            'dsn' => 'Driver={Oracle in OraClient11g_home1};Dbq=127.0.0.1:1521/XE;Uid=oracleuser;Pwd=pass;',
            'local' => true,
            'port' => 1521,
        ];
        yield 'Oracle ODBC (localhost)' => [
            'dsn' => 'Driver={Oracle in OraClient11g_home1};Dbq=localhost:1521/XE;Uid=oracleuser;Pwd=pass;',
            'local' => true,
            'port' => 1521,
        ];
        yield 'Oracle ODBC (local ipv6)' => [
            'dsn' => 'Driver={Oracle in OraClient11g_home1};Dbq=[::1]:1521/XE;Uid=oracleuser;Pwd=pass;',
            'local' => true,
            'port' => 1521,
        ];
        yield 'Oracle ODBC (remote)' => [
            'dsn' => 'Driver={Oracle in OraClient11g_home1};Dbq=23.20.239.12:1521/XE;Uid=oracleuser;Pwd=pass;',
            'local' => false,
            'port' => 1521,
        ];
    }

    /** @dataProvider provideDSNsToParsePorts */
    public function testDetectLocalDSNsAndPorts(
        string $dsn,
        bool $local,
        ?int $port,
    ): void {
        $parsedDsn = new DsnParser($dsn);

        self::assertSame($local, $parsedDsn->isLocal());
        self::assertSame($port, $parsedDsn->parsePort());
    }

    protected static function provideInvalidDNSs(): iterable
    {
        yield 'missing port attribute #1' => [
            'mysql:host=127.0.0.1;dbname=testdb',
        ];
        yield 'missing port attribute #2' => [
            'Driver={PostgreSQL Unicode};Server=example.com;Database=testdb;Uid=postgres;Pwd=pass;',
        ];
        yield 'invalid port attribute name' => [
            'Driver={MySQL ODBC 8.0 Driver};Server=invalid_domain;_Port=3306;Database=testdb;User=root;Password=pass;',
        ];
        yield 'unparsable port due to invalid ipv4 address #1' => [
            'sqlsrv:Server=192.168.1,1433;Database=testdb',
        ];
        yield 'unparsable port due to invalid ipv4 address #2' => [
            'Driver={ODBC Driver 17 for SQL Server};Server=192.168..1,1433;Database=testdb;Uid=sa;Pwd=pass;',
        ];
        yield 'unparsable port due to invalid ipv4 address #3' => [
            'sqlsrv:Server=62.172.97.30x,1433;Database=testdb',
        ];
        yield 'unparsable port due to invalid ipv6 address #1' => [
            'Driver={ODBC Driver 17 for SQL Server};Server=[2001:db8:85a3:8a2e:370g:7334],1433;Database=testdb;Uid=sa;Pwd=pass;',
        ];
        yield 'unparsable port due to invalid ipv6 address #2' => [
            'Driver={ODBC Driver 17 for SQL Server};Server=[2001:db8:85a3::8a2e:0370:7334:12345],1433;Database=testdb;Uid=sa;Pwd=pass;',
        ];
        yield 'unparsable port due to invalid ipv6 address #3' => [
            'Driver={ODBC Driver 17 for SQL Server};Server=[::2001::7334],1433;Database=testdb;Uid=sa;Pwd=pass;',
        ];
        yield 'unparsable port due to invalid domain' => [
            'sqlsrv:Server=invalid_domain,1433;Database=testdb',
        ];
    }

    /** @dataProvider provideInvalidDNSs */
    public function testInvalidDSNsAndPorts(string $dsn): void
    {
        $parsedDsn = new DsnParser($dsn);
        self::assertNull($parsedDsn->parsePort());
    }
}
