<?php

namespace tests\unit\Http;

use Codeception\Test\Unit;
use Esia\Http\GuzzleHttpClient;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use HttpException;
use Psr\Http\Client\ClientExceptionInterface;

/**
 * Class GuzzleHttpClientTest
 *
 * @coversDefaultClass \Esia\Http\GuzzleHttpClient
 */
class GuzzleHttpClientTest extends Unit
{
    /**
     * @throws ClientExceptionInterface
     * @throws HttpException
     */
    public function testSendRequest(): void
    {
        $mock = new MockHandler([
            new Response(),
            new RequestException('Error Communicating with Server', new Request('GET', 'test'))
        ]);

        $handler = HandlerStack::create($mock);
        $guzzleClient = new Client(['handler' => $handler]);

        $client = new GuzzleHttpClient($guzzleClient);

        $response = $client->sendRequest(new Request('GET', '/'));

        self::assertSame(200, $response->getStatusCode());

        $this->expectException(ClientExceptionInterface::class);
        $client->sendRequest(new Request('GET', '/'));
    }
}
