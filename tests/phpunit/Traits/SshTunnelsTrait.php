<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Adapter\Tests\Traits;

use Keboola\SSHTunnel\SSH;
use Symfony\Component\Process\Process;

trait SshTunnelsTrait
{
    protected const SSH_PROXY_HOST = 'sshproxy';
    protected const DEFAULT_SSH_LOCAL_PORT = 33006;

    protected function openSshTunnel(
        string $remoteHost = 'mariadb',
        int $remotePort = 3306,
        int $localPort = self::DEFAULT_SSH_LOCAL_PORT,
    ): void {
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
    }

    protected function closeSshTunnels(): void
    {
        # Close SSH tunnel if created
        $process = new Process(['sh', '-c', 'pgrep ssh | xargs -r kill']);
        $process->mustRun();
    }

    private function getPrivateKey(): string
    {
        return (string) file_get_contents('/root/.ssh/id_rsa');
    }
}
