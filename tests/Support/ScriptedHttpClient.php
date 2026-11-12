<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusEinvoicePennylanePlugin\Support;

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

/** An HTTP client that answers the scripted responses in order and records the requests. No real call is ever made. */
final class ScriptedHttpClient implements HttpClientInterface
{
    /** @var list<RecordedRequest> */
    public array $requests = [];

    /** @var list<MockResponse> */
    private array $responses = [];

    /** @param array<string, mixed> $body */
    public static function json(int $status, array $body, array $headers = []): MockResponse
    {
        return new MockResponse(json_encode($body, \JSON_THROW_ON_ERROR), [
            'http_code' => $status,
            'response_headers' => ['content-type: application/json', ...$headers],
        ]);
    }

    /** The answer of a timeout or of a connection that fails. */
    public static function timeout(): MockResponse
    {
        return new MockResponse('', ['error' => 'Idle timeout reached for "https://app.pennylane.com".']);
    }

    public function enqueue(MockResponse ...$responses): void
    {
        array_push($this->responses, ...$responses);
    }

    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $response = array_shift($this->responses) ?? throw new \LogicException(sprintf('No scripted response left for %s %s.', $method, $url));
        $client = new MockHttpClient(function (string $method, string $url, array $options) use ($response): MockResponse {
            $this->requests[] = new RecordedRequest($method, $url, array_values($options['headers']), $this->bodyOf($options['body'] ?? ''));

            return $response;
        });

        return $client->request($method, $url, $options);
    }

    public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): ResponseStreamInterface
    {
        throw new \LogicException('Not used by the adapter.');
    }

    public function withOptions(array $options): static
    {
        return $this;
    }

    private function bodyOf(mixed $body): string
    {
        if (\is_string($body)) {
            return $body;
        }
        $content = '';
        if ($body instanceof \Closure) {
            while ('' !== ($chunk = $body(8192))) {
                $content .= $chunk;
            }

            return $content;
        }
        foreach ($body as $chunk) {
            $content .= $chunk;
        }

        return $content;
    }
}
