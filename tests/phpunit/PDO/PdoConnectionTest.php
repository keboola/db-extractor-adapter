<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Adapter\Tests\PDO;

use Keboola\CommonExceptions\UserExceptionInterface;
use Keboola\DbExtractor\Adapter\Exception\DeadConnectionException;
use Keboola\DbExtractor\Adapter\Exception\SshTunnelClosedException;
use Keboola\DbExtractor\Adapter\Tests\BaseTest;
use Keboola\DbExtractor\Adapter\Tests\Traits\PdoCreateConnectionTrait;
use Keboola\DbExtractor\Adapter\Tests\Traits\SshTunnelsTrait;
use Keboola\DbExtractor\Adapter\ValueObject\QueryResult;
use PDO;
use PHPUnit\Framework\Assert;

class PdoConnectionTest extends BaseTest
{
    use PdoCreateConnectionTrait;
    use SshTunnelsTrait;

    public function testInvalidHost(): void
    {
        $retries = 2;
        try {
            $this->createPdoConnection('invalid', null, $retries);
            Assert::fail('Exception expected.');
        } catch (UserExceptionInterface $e) {
            Assert::assertStringContainsString('Name or service not known', $e->getMessage());
        }

        for ($attempt=1; $attempt < $retries; $attempt++) {
            Assert::assertTrue($this->logger->hasInfoThatContains("Retrying... [{$attempt}x]"));
        }
    }

    public function testInvalidHostNoErrorHandler(): void
    {
        // Disable error handler, ... tests that PDO throws exception in this situation
        set_error_handler(null);
        $retries = 2;
        try {
            $this->createPdoConnection('invalid', null, $retries);
            Assert::fail('Exception expected.');
        } catch (UserExceptionInterface $e) {
            Assert::assertStringContainsString('Name or service not known', $e->getMessage());
        }

        for ($attempt=1; $attempt < $retries; $attempt++) {
            Assert::assertTrue($this->logger->hasInfoThatContains("Retrying... [{$attempt}x]"));
        }
    }


    public function testDisableConnectRetries(): void
    {
        // Disable error handler, ... tests that PDO throws exception in this situation
        set_error_handler(null);
        try {
            $this->createPdoConnection('invalid', null, 1);
            Assert::fail('Exception expected.');
        } catch (UserExceptionInterface $e) {
            Assert::assertStringContainsString('Name or service not known', $e->getMessage());
        }

        // No retry in logs
        Assert::assertFalse($this->logger->hasInfoThatContains('Retrying...'));
    }


    public function testTestConnection(): void
    {
        $connection = $this->createPdoConnection();
        $connection->testConnection();
        Assert::assertTrue($this->logger->hasInfoThatContains(
            'Creating PDO connection to "mysql:host=mariadb;port=3306;dbname=testdb;charset=utf8".',
        ));
    }

    public function testTestConnectionFailed(): void
    {
        $proxy = $this->createProxyToDb();
        $connection = $this->createPdoConnection(self::TOXIPROXY_HOST, (int) $proxy->getListenPort());
        $this->makeProxyDown($proxy);

        try {
            $connection->testConnection();
            Assert::fail('Exception expected.');
        } catch (UserExceptionInterface $e) {
            Assert::assertStringContainsString('MySQL server has gone away', $e->getMessage());
        }
    }

    public function testTestConnectionFailedClosedSshTunnel(): void
    {
        $this->openSshTunnel();
        $connection = $this->createPdoConnection('127.0.0.1', self::DEFAULT_SSH_LOCAL_PORT);
        $this->closeSshTunnels();

        try {
            $connection->testConnection();
            Assert::fail('Exception expected.');
        } catch (SshTunnelClosedException $e) {
            Assert::assertStringContainsString('SSH tunnel has been closed', $e->getMessage());
        }
    }

    public function testTestConnectionFailedOpenSshTunnel(): void
    {
        $proxy = $this->createProxyToDb();
        $this->openSshTunnel(remoteHost: self::TOXIPROXY_HOST, remotePort: (int) $proxy->getListenPort());
        $connection = $this->createPdoConnection('127.0.0.1', self::DEFAULT_SSH_LOCAL_PORT);
        $this->makeProxyDown($proxy);

        try {
            $connection->testConnection();
            Assert::fail('Exception expected.');
        } catch (UserExceptionInterface $e) {
            Assert::assertStringContainsString('MySQL server has gone away', $e->getMessage());
            $this->closeSshTunnels();
        }
    }

    public function testTestConnectionFailedNoErrorHandler(): void
    {
        // Disable error handler, ... tests that PDO throws exception in this situation
        set_error_handler(null);
        $proxy = $this->createProxyToDb();
        $connection = $this->createPdoConnection(self::TOXIPROXY_HOST, (int) $proxy->getListenPort());
        $this->makeProxyDown($proxy);

        try {
            $connection->testConnection();
            Assert::fail('Exception expected.');
        } catch (UserExceptionInterface $e) {
            Assert::assertStringContainsString('MySQL server has gone away', $e->getMessage());
        }
    }

    public function testGetConnection(): void
    {
        $connection = $this->createPdoConnection();
        Assert::assertTrue($connection->getConnection() instanceof PDO);
    }

    public function testQuote(): void
    {
        $connection = $this->createPdoConnection();
        Assert::assertSame("'abc\''", $connection->quote("abc'"));
    }

    public function testQuoteIdentifier(): void
    {
        $connection = $this->createPdoConnection();
        Assert::assertSame('`abc```', $connection->quoteIdentifier('abc`'));
    }

    public function testIsAlive(): void
    {
        $connection = $this->createPdoConnection();
        $connection->isAlive();
        $this->expectNotToPerformAssertions();
    }

    public function testIsAliveFailed(): void
    {
        $proxy = $this->createProxyToDb();
        $connection = $this->createPdoConnection(self::TOXIPROXY_HOST, (int) $proxy->getListenPort());
        $this->makeProxyDown($proxy);

        try {
            $connection->isAlive();
            Assert::fail('Exception expected.');
        } catch (DeadConnectionException $e) {
            Assert::assertStringContainsString('Dead connection:', $e->getMessage());
            Assert::assertStringContainsString('MySQL server has gone away', $e->getMessage());
        }
    }

    public function testQuery(): void
    {
        $connection = $this->createPdoConnection();
        Assert::assertSame(
            [['X' => 123, 'Y' => 456]],
            $connection->query('SELECT 123 as X, 456 as Y')->fetchAll(),
        );
        Assert::assertTrue($this->logger->hasDebug('Running query "SELECT 123 as X, 456 as Y".'));
    }

    public function testQueryFailed(): void
    {
        $proxy = $this->createProxyToDb();
        $connection = $this->createPdoConnection(self::TOXIPROXY_HOST, (int) $proxy->getListenPort());
        $this->makeProxyDown($proxy);

        $retries = 4;
        try {
            $connection->query('SELECT 123 as X, 456 as Y', $retries);
            Assert::fail('Exception expected.');
        } catch (UserExceptionInterface $e) {
            Assert::assertStringContainsString('MySQL server has gone away', $e->getMessage());
        }

        for ($attempt=1; $attempt < $retries; $attempt++) {
            Assert::assertTrue($this->logger->hasInfoThatContains("Retrying... [{$attempt}x]"));
        }
    }

    public function testQueryFailedClosedSshTunnel(): void
    {
        $this->openSshTunnel();
        $connection = $this->createPdoConnection('127.0.0.1', self::DEFAULT_SSH_LOCAL_PORT);
        $this->closeSshTunnels();

        $retries = 4;
        try {
            $connection->query('SELECT 123 as X, 456 as Y', $retries);
            Assert::fail('Exception expected.');
        } catch (SshTunnelClosedException $e) {
            Assert::assertStringContainsString('SSH tunnel has been closed', $e->getMessage());
        }
    }

    public function testQueryFailedOpenSshTunnel(): void
    {
        $proxy = $this->createProxyToDb();
        $this->openSshTunnel(remoteHost: self::TOXIPROXY_HOST, remotePort: (int) $proxy->getListenPort());
        $connection = $this->createPdoConnection('127.0.0.1', self::DEFAULT_SSH_LOCAL_PORT);
        $this->makeProxyDown($proxy);

        $retries = 4;
        try {
            $connection->query('SELECT 123 as X, 456 as Y', $retries);
            Assert::fail('Exception expected.');
        } catch (UserExceptionInterface $e) {
            Assert::assertStringContainsString('MySQL server has gone away', $e->getMessage());
            $this->closeSshTunnels();
        }
    }

    public function testQueryAndProcess(): void
    {
        $connection = $this->createPdoConnection();
        Assert::assertSame(
            [['X', 'Y' ]],
            $connection->queryAndProcess('SELECT 123 as X, 456 as Y', 3, function (QueryResult $result) {
                return array_map(function (array $row) {
                    return array_keys($row);
                }, $result->fetchAll());
            }),
        );
    }

    public function testQueryAndProcessFailed(): void
    {
        $proxy = $this->createProxyToDb();
        $connection = $this->createPdoConnection(self::TOXIPROXY_HOST, (int) $proxy->getListenPort());
        $this->makeProxyDown($proxy);

        $retries = 4;
        try {
            $connection->queryAndProcess('SELECT 123 as X, 456 as Y', $retries, function (QueryResult $result) {
                return array_map(function (array $row) {
                    return array_keys($row);
                }, $result->fetchAll());
            });
            Assert::fail('Exception expected.');
        } catch (UserExceptionInterface $e) {
            Assert::assertStringContainsString('MySQL server has gone away', $e->getMessage());
        }

        for ($attempt=1; $attempt < $retries; $attempt++) {
            Assert::assertTrue($this->logger->hasInfoThatContains("Retrying... [{$attempt}x]"));
        }
    }
}
