<?php

namespace App\Service\WhatsApp;

use App\Entity\Shop;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

/**
 * Gerencia instâncias da Evolution API por barbearia: criar, obter QR code e estado da conexão.
 */
class EvolutionApiManager
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $evolutionBaseUrl,
        private string $evolutionGlobalApiKey,
    ) {
        $this->evolutionBaseUrl = rtrim($evolutionBaseUrl, '/');
    }

    public function isConfigured(): bool
    {
        return $this->evolutionBaseUrl !== '' && $this->evolutionGlobalApiKey !== '';
    }

    /**
     * Cria uma instância na Evolution API para a barbearia e retorna o QR code para conexão.
     *
     * @return array{instanceName: string, instanceApiKey: string|null, qrcode: string|null, error?: string}
     */
    public function createInstanceForShop(Shop $shop): array
    {
        if (!$this->isConfigured()) {
            return ['instanceName' => '', 'instanceApiKey' => null, 'qrcode' => null, 'error' => 'Evolution API não configurada.'];
        }

        $instanceName = 'barberflow-' . $shop->getId();
        $url = $this->evolutionBaseUrl . '/instance/create';
        $headers = $this->defaultHeaders();

        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => $headers,
                'json' => [
                    'instanceName' => $instanceName,
                    'integration' => 'WHATSAPP-BAILEYS',
                    'qrcode' => true,
                ],
                'timeout' => 30,
            ]);

            $data = $response->toArray(false);
            $status = $response->getStatusCode();

            if ($status >= 200 && $status < 300) {
                $apiKey = $data['hash']['apikey'] ?? $data['instance']['apikey'] ?? null;
                $qrcode = $data['qrcode']['base64'] ?? $data['qrcode']['code'] ?? $data['code'] ?? null;
                if ($qrcode === null) {
                    $fetched = $this->fetchQrcodeByInstance($instanceName, $apiKey);
                    $qrcode = $fetched['qrcode'] ?? null;
                }
                return [
                    'instanceName' => $instanceName,
                    'instanceApiKey' => $apiKey,
                    'qrcode' => $qrcode,
                ];
            }

            $message = $data['message'] ?? $data['error'] ?? 'Erro ao criar instância';
            if (is_array($message)) {
                $message = implode(', ', $message);
            }
            return ['instanceName' => '', 'instanceApiKey' => null, 'qrcode' => null, 'error' => $message];
        } catch (ExceptionInterface $e) {
            return [
                'instanceName' => '',
                'instanceApiKey' => null,
                'qrcode' => null,
                'error' => 'Falha ao comunicar com Evolution API: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Obtém o QR code atual da instância (para reconectar ou exibir novamente).
     *
     * @return array{qrcode: string|null, error?: string}
     */
    public function fetchQrcode(Shop $shop): array
    {
        $instanceName = $shop->getEvolutionInstanceName();
        if (!$instanceName || !$this->isConfigured()) {
            return ['qrcode' => null, 'error' => 'Instância não configurada.'];
        }
        return $this->fetchQrcodeByInstance($instanceName, $shop->getEvolutionInstanceApiKey());
    }

    /**
     * @return array{qrcode: string|null, error?: string}
     */
    private function fetchQrcodeByInstance(string $instanceName, ?string $apiKey): array
    {
        $url = $this->evolutionBaseUrl . '/instance/connect/' . $instanceName;
        $headers = $this->defaultHeaders();
        if ($apiKey !== null && $apiKey !== '') {
            $headers['apikey'] = $apiKey;
        }

        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => $headers,
                'timeout' => 15,
            ]);

            $data = $response->toArray(false);
            $qrcode = $data['base64'] ?? $data['qrcode']['base64'] ?? $data['code'] ?? $data['qrcode']['code'] ?? null;
            return ['qrcode' => $qrcode];
        } catch (ExceptionInterface $e) {
            return ['qrcode' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Retorna o estado da conexão da instância: open, close, connecting.
     *
     * @return array{state: string, owner?: string, profileName?: string, error?: string}
     */
    public function connectionState(Shop $shop): array
    {
        $instanceName = $shop->getEvolutionInstanceName();
        if (!$instanceName || !$this->isConfigured()) {
            return ['state' => 'close'];
        }

        $url = $this->evolutionBaseUrl . '/instance/connectionState/' . $instanceName;
        $headers = $this->defaultHeaders();
        $apiKey = $this->evolutionGlobalApiKey;
        if ($apiKey) {
            $headers['apikey'] = $apiKey;
        }

        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => $headers,
                'timeout' => 10,
            ]);

            $data = $response->toArray(false);
            $state = $data['state'] ?? $data['instance']['state'] ?? 'close';
            $result = ['state' => (string) $state];

            if ($result['state'] === 'open') {
                $instanceInfo = $this->fetchInstanceInfo($shop);
                if (!empty($instanceInfo['owner'])) {
                    $result['owner'] = $instanceInfo['owner'];
                }
                if (isset($instanceInfo['profileName'])) {
                    $result['profileName'] = $instanceInfo['profileName'];
                }
            }

            return $result;
        } catch (ExceptionInterface $e) {
            return ['state' => 'close', 'error' => $e->getMessage()];
        }
    }

    /**
     * Obtém dados da instância (owner = número conectado no formato 5511999999999, profileName).
     *
     * @return array{owner?: string, profileName?: string}
     */
    public function fetchInstanceInfo(Shop $shop): array
    {
        $instanceName = $shop->getEvolutionInstanceName();
        if (!$instanceName || !$this->isConfigured()) {
            return [];
        }
        return $this->fetchInstanceInfoByInstance($instanceName, $this->evolutionGlobalApiKey);
    }

    /**
     * @return array{owner?: string, profileName?: string}
     */
    private function fetchInstanceInfoByInstance(string $instanceName, ?string $apiKey): array
    {
        $headers = $this->defaultHeaders();
        if ($apiKey !== null && $apiKey !== '') {
            $headers['apikey'] = $apiKey;
        }

        $instance = $this->requestFetchInstances($instanceName, $headers);
        if ($instance === null) {
            $instance = $this->requestInstanceInfo($instanceName, $headers);
        }
        if ($instance === null) {
            $instance = $this->requestGetInformation($instanceName, $headers);
        }

        if (!$instance || !is_array($instance)) {
            return [];
        }

        $owner = $this->normalizeOwner(
            $instance['ownerJid'] ?? $instance['ownerId'] ?? $instance['wid'] ?? $instance['number'] ?? null
        );

        return [
            'owner' => $owner,
            'profileName' => $instance['profileName'] ?? $instance['pushName'] ?? null,
        ];
    }

    private function normalizeOwner(mixed $owner): ?string
    {
        if (!is_string($owner) || $owner === '') {
            return null;
        }
        $owner = preg_replace('/@.*$/', '', $owner);
        return $owner !== '' ? $owner : null;
    }

    /**
     * GET /instance/fetchInstances?instanceName=...
     *
     * @return array<string, mixed>|null
     */
    private function requestFetchInstances(string $instanceName, array $headers): ?array
    {
        $url = $this->evolutionBaseUrl . '/instance/fetchInstances?instanceName=' . urlencode($instanceName);
        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => $headers,
                'timeout' => 10,
            ]);
            $data = $response->toArray(false);
            return $this->extractInstanceFromResponse($data);
        } catch (ExceptionInterface $e) {
            return null;
        }
    }

    /**
     * GET /instance/info/{instanceName} (fallback em algumas versões)
     *
     * @return array<string, mixed>|null
     */
    private function requestInstanceInfo(string $instanceName, array $headers): ?array
    {
        $url = $this->evolutionBaseUrl . '/instance/info/' . $instanceName;
        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => $headers,
                'timeout' => 10,
            ]);
            $data = $response->toArray(false);
            return $this->extractInstanceFromResponse($data);
        } catch (ExceptionInterface $e) {
            return null;
        }
    }

    /**
     * GET /instance/getInformation/{instanceName} – retorna dados do WhatsApp conectado em algumas versões.
     *
     * @return array<string, mixed>|null
     */
    private function requestGetInformation(string $instanceName, array $headers): ?array
    {
        $url = $this->evolutionBaseUrl . '/instance/getInformation/' . $instanceName;
        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => $headers,
                'timeout' => 10,
            ]);
            $data = $response->toArray(false);
            $instance = $this->extractInstanceFromResponse($data);
            if ($instance !== null) {
                return $instance;
            }
            $owner = $data['owner'] ?? $data['wid'] ?? $data['number'] ?? null;
            if ($owner !== null || isset($data['pushName'])) {
                return [
                    'owner' => is_string($owner) ? $owner : null,
                    'profileName' => $data['pushName'] ?? $data['profileName'] ?? null,
                ];
            }
            return null;
        } catch (ExceptionInterface $e) {
            return null;
        }
    }

    /**
     * Extrai o objeto 'instance' de várias estruturas possíveis da Evolution API.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>|null
     */
    private function extractInstanceFromResponse(array $data): ?array
    {
        $instance = $data[0] ?? null;

        if ($instance === null && isset($data['response']) && is_array($data['response'])) {
            $instance = $this->extractInstanceFromResponse($data['response']);
        }

        if (isset($data['message']) && is_array($data['message'])) {
            $first = $data['message'][0] ?? null;
            if (is_array($first)) {
                $instance = $instance ?? ($first['instance'] ?? $first);
            }
        }

        if ($instance === null && isset($data[0]) && is_array($data[0])) {
            $first = $data[0];
            $instance = $first['instance'] ?? $first;
        }

        return is_array($instance) ? $instance : null;
    }

    private function defaultHeaders(): array
    {
        $h = ['Content-Type' => 'application/json'];
        if ($this->evolutionGlobalApiKey !== '') {
            $h['apikey'] = $this->evolutionGlobalApiKey;
        }
        return $h;
    }
}
