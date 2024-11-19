<?php

declare(strict_types=1);

namespace Keboola\DbExtractor\Adapter;

readonly class DsnParser
{
    private const LOCALHOST_PATTERN = '127\.0\.0\.1|\[?::1\]?|localhost';

    // for IP detection only, not for validation
    private const IPV4_PATTERN = '\d{1,3}(?:\.\d{1,3}){3}';
    private const IPV6_PATTERN = '\[?(?:[0-9a-fA-F]{1,4}:){1,7}[0-9a-fA-F]{1,4}\]?';
    private const DOMAIN_PATTERN = '(?:[a-zA-Z0-9-]+\.)+[a-zA-Z]{2,63}';

    public function __construct(
        private string $dsn,
    ) {
    }

    public function isLocal(): bool
    {
        return preg_match(sprintf('/%s/u', self::LOCALHOST_PATTERN), $this->dsn) === 1;
    }

    public function parsePort(): ?int
    {
        // port as an attribute (port=, Port= or PORT=)
        if (preg_match('/(?:;|^)port=(\d+)/ui', $this->dsn, $matches) === 1) {
            return (int) $matches[1];
        }

        // port as a part of host spec (separated by a colon or a comma)
        $regex = sprintf('/(?:%s)[:,](\d+)(?:;|\/|$)/u', self::hostPattern());
        if (preg_match($regex, $this->dsn, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    private static function hostPattern(): string
    {
        return implode('|', [
            self::LOCALHOST_PATTERN, self::IPV4_PATTERN, self::IPV6_PATTERN, self::DOMAIN_PATTERN,
        ]);
    }
}
