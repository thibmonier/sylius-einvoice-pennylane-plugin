<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusEinvoicePennylanePlugin\Support;

final readonly class RecordedRequest
{
    /** @param list<string> $headers lines "Name: value" */
    public function __construct(
        public string $method,
        public string $url,
        public array $headers,
        public string $body,
    ) {
    }

    public function path(): string
    {
        return (string) parse_url($this->url, \PHP_URL_PATH);
    }

    public function host(): string
    {
        return (string) parse_url($this->url, \PHP_URL_HOST);
    }

    /** @return array<string, string> */
    public function query(): array
    {
        parse_str((string) parse_url($this->url, \PHP_URL_QUERY), $query);

        return array_map(strval(...), $query);
    }

    /** @return array<string, mixed> */
    public function json(): array
    {
        $decoded = json_decode($this->body, true);

        return \is_array($decoded) ? $decoded : [];
    }

    public function header(string $name): ?string
    {
        foreach ($this->headers as $line) {
            if (str_starts_with(strtolower($line), strtolower($name) . ':')) {
                return trim(substr($line, \strlen($name) + 1));
            }
        }

        return null;
    }
}
