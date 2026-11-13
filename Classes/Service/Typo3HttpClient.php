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
        require_once dirname((new \ReflectionClass(HttpClient::class))->getFileName()).'/ProviderHttp.php';
        return \Kiosky\Core\ProviderHttp::request(fn(string $verb,string $target,array $requestHeaders,?string $payload,int $remaining):HttpResponse=>$this->send($verb,$target,$requestHeaders,$payload,$remaining,$allowPrivateNetwork,$verifyTls),strtoupper($method),$url,$headers,$body,$timeoutSeconds);
    }

    private function send(string $method,string $url,array $headers,?string $body,int $timeoutSeconds,bool $allowPrivateNetwork,bool $verifyTls):HttpResponse
    {
        $deadline=microtime(true)+max(1,$timeoutSeconds);
        try {
            $response = $this->requestFactory->request($url, strtoupper($method), [
                'headers' => $headers + ['User-Agent' => 'Kiosky/3.40.3 TYPO3'],
                'body' => $body,
                'timeout' => $timeoutSeconds,
                'verify' => $verifyTls,
                'allow_redirects' => false,
                'stream'=>true,'read_timeout'=>$timeoutSeconds,
            ]);
        } catch (Throwable $error) {
            if (str_contains(strtolower($error->getMessage()), 'timed out')) {
                throw new RuntimeException('Der externe Dienst hat das Zeitlimit überschritten.', 504);
            }
            throw new RuntimeException('Der externe Dienst konnte nicht erreicht werden.',502);
        }
        $responseHeaders = [];
        foreach ($response->getHeaders() as $name => $values) $responseHeaders[$name] = implode(', ', $values);
        $stream=$response->getBody();$payload='';
        try{while(!$stream->eof()){if(microtime(true)>=$deadline)throw new RuntimeException('Der externe Dienst hat das Zeitlimit überschritten.',504);$chunk=$stream->read(8192);if($chunk===''&&!$stream->eof())throw new RuntimeException('Der externe Dienst hat das Zeitlimit überschritten.',504);$payload.=$chunk;if(strlen($payload)>\Kiosky\Core\ProviderHttp::LIMIT)throw new RuntimeException('Die Antwort des externen Dienstes ist zu groß.',413);}}finally{$stream->close();}
        return new HttpResponse($response->getStatusCode(), $responseHeaders, $payload);
    }
}
