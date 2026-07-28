<?php

namespace App\Tests\Unit;

use App\Entity\Shop;
use App\Service\WhatsApp\EvolutionApiManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class EvolutionApiManagerTest extends TestCase
{
    public function testDisconnectDeletesTheInstanceUsingTheProviderFlow(): void
    {
        $requests = [];
        $httpClient = new MockHttpClient(
            function (string $method, string $url, array $options) use (&$requests): MockResponse {
                $requests[] = compact('method', 'url', 'options');

                return new MockResponse('{"status":"SUCCESS"}', [
                    'http_code' => 200,
                    'response_headers' => ['content-type: application/json'],
                ]);
            },
        );
        $manager = new EvolutionApiManager(
            $httpClient,
            'https://evolution.example.com/',
            'global-api-key',
        );
        $shop = (new Shop())->setEvolutionInstanceName('barberflow-42');

        self::assertSame(['success' => true], $manager->disconnect($shop));
        self::assertCount(1, $requests);
        self::assertSame('DELETE', $requests[0]['method']);
        self::assertSame(
            'https://evolution.example.com/instance/delete/barberflow-42',
            $requests[0]['url'],
        );
        self::assertContains(
            'apikey: global-api-key',
            $requests[0]['options']['headers'],
        );
    }

    public function testDisconnectReturnsTheDeleteError(): void
    {
        $httpClient = new MockHttpClient(new MockResponse(
            '{"error":"Bad Request","response":{"message":"Falha ao remover instância"}}',
            [
                'http_code' => 500,
                'response_headers' => ['content-type: application/json'],
            ],
        ));
        $manager = new EvolutionApiManager(
            $httpClient,
            'https://evolution.example.com',
            'global-api-key',
        );
        $shop = (new Shop())->setEvolutionInstanceName('barberflow-42');

        self::assertSame(
            ['success' => false, 'error' => 'Falha ao remover instância'],
            $manager->disconnect($shop),
        );
    }

    public function testDisconnectAcceptsAnAlreadyRemovedInstance(): void
    {
        $httpClient = new MockHttpClient(
            new MockResponse('{"message":"Instance not found"}', [
                'http_code' => 404,
                'response_headers' => ['content-type: application/json'],
            ]),
        );
        $manager = new EvolutionApiManager(
            $httpClient,
            'https://evolution.example.com',
            'global-api-key',
        );
        $shop = (new Shop())->setEvolutionInstanceName('barberflow-42');

        self::assertSame(['success' => true], $manager->disconnect($shop));
    }

    public function testDisconnectRequiresAConfiguredInstance(): void
    {
        $manager = new EvolutionApiManager(new MockHttpClient(), '', '');

        self::assertSame(
            ['success' => false, 'error' => 'Instância não configurada.'],
            $manager->disconnect(new Shop()),
        );
    }
}
