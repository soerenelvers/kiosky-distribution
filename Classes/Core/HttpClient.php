<?php

declare(strict_types=1);

namespace Kiosky\Core;

use RuntimeException;

interface HttpClient
{
    /** @param array<string,string> $headers */
    public function request(string $method, string $url, array $headers = [], ?string $body = null, int $timeoutSeconds = 15, bool $allowPrivateNetwork = false, bool $verifyTls = true): HttpResponse;
}
final class HttpResponse
{
    /** @param array<string,string> $headers */
    public function __construct(
        public readonly int $status,
        public readonly array $headers,
        public readonly string $body,
    ) {
    }

    /** @return array<string,mixed> */
    public function json(): array
    {
        try {
            $value = json_decode($this->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new RuntimeException('Der externe Dienst hat kein gültiges JSON geliefert.', 502);
        }
        if (!is_array($value)) throw new RuntimeException('Der externe Dienst hat eine ungültige Antwort geliefert.', 502);
        return $value;
    }

    public function header(string $name): string
    {
        foreach ($this->headers as $key => $value) {
            if (strcasecmp($key, $name) === 0) return $value;
        }
        return '';
    }
}
