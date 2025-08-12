<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Adapter\Tests\Traits;

use Keboola\SSHTunnel\SSH;
use RuntimeException;
use Symfony\Component\Process\Process;

trait SshTunnelsTrait
{
    protected const SSH_PROXY_HOST = 'sshproxy';
    protected const DEFAULT_SSH_LOCAL_PORT = 33006;

    private ?int $currentSshLocalPort = null;

    protected function openSshTunnel(
        string $remoteHost = 'mariadb',
        int $remotePort = 3306,
        ?int $localPort = null,
    ): int {
        if ($localPort === null) {
            $localPort = $this->findAvailablePort();
        }

        $this->currentSshLocalPort = $localPort;

        $ssh = new SSH();
        $ssh->openTunnel([
            'user' => 'root',
            'sshHost' => self::SSH_PROXY_HOST,
            'sshPort' => 22,
            'localPort' => $localPort,
            'remoteHost' => $remoteHost,
            'remotePort' => $remotePort,
            'privateKey' => $this->getPrivateKey(),
        ]);

        return $localPort;
    }

    protected function getCurrentSshLocalPort(): int
    {
        if ($this->currentSshLocalPort === null) {
            throw new RuntimeException('No SSH tunnel has been opened');
        }
        return $this->currentSshLocalPort;
    }

    protected function closeSshTunnels(): void
    {
        # Close SSH tunnel if created
        $process = new Process(['sh', '-c', 'pgrep ssh | xargs -r kill']);
        $process->mustRun();
        $this->currentSshLocalPort = null;
    }

    private function findAvailablePort(): int
    {
        // Try ports starting from the default port
        $startPort = self::DEFAULT_SSH_LOCAL_PORT;
        $maxAttempts = 100; // Try up to 100 ports

        for ($i = 0; $i < $maxAttempts; $i++) {
            $port = $startPort + $i;
            if ($this->isPortAvailable($port)) {
                return $port;
            }
        }

        throw new RuntimeException('Could not find available port for SSH tunnel');
    }

    private function isPortAvailable(int $port): bool
    {
        $socket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($socket === false) {
            return false;
        }

        $result = @socket_bind($socket, '127.0.0.1', $port);
        @socket_close($socket);

        return $result !== false;
    }

    private function getPrivateKey(): string
    {
        return (string) file_get_contents('/root/.ssh/id_rsa');
    }
}
