<?php

declare(strict_types=1);

namespace Kiosky\Kiosky\Service;

use Kiosky\Core\HttpClient;
use Kiosky\Core\HttpResponse;
use RuntimeException;
use Throwable;
use TYPO3\CMS\Core\Http\RequestFactory;

final readonly class Typo3HttpClient implements HttpClient
{
    public function __construct(private RequestFactory $requestFactory)
    {
    }

    public function request(string $method, string $url, array $headers = [], ?string $body = null, int $timeoutSeconds = 15, bool $allowPrivateNetwork = false, bool $verifyTls = true): HttpResponse
    {
        try {
            $response = $this->requestFactory->request($url, strtoupper($method), [
                'headers' => $headers + ['User-Agent' => 'Kiosky/3.0.0 TYPO3'],
                'body' => $body,
                'timeout' => $timeoutSeconds,
                'verify' => $verifyTls,
                'allow_redirects' => ['max' => 3, 'strict' => true, 'referer' => false, 'protocols' => $allowPrivateNetwork ? ['http', 'https'] : ['https']],
            ]);
        } catch (Throwable $error) {
            if (str_contains(strtolower($error->getMessage()), 'timed out')) {
                throw new RuntimeException('Der externe Dienst hat das Zeitlimit überschritten.', 504, $error);
            }
            throw new RuntimeException('Der externe Dienst konnte nicht erreicht werden: ' . $error->getMessage(), 502, $error);
        }
        $responseHeaders = [];
        foreach ($response->getHeaders() as $name => $values) $responseHeaders[$name] = implode(', ', $values);
        return new HttpResponse($response->getStatusCode(), $responseHeaders, (string)$response->getBody());
    }
}
