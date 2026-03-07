<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AsaasClientService
{
    private HttpClientInterface $httpClient;
    private string $accessToken;
    private string $baseURL;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
        $env = $_ENV['APP_ENV'] ?? 'dev';
        $this->accessToken = $env === 'prod'
            ? ($_ENV['ASSAS_PROD_ACCESS_TOKEN'] ?? '')
            : ($_ENV['ASSAS_SANDBOX_ACCESS_TOKEN'] ?? '');
        $base = $env === 'prod'
            ? ($_ENV['ASSAS_PROD_BASE_URL'] ?? 'https://api.asaas.com')
            : ($_ENV['ASSAS_SANDBOX_BASE_URL'] ?? 'https://sandbox.asaas.com/api');
        $this->baseURL = rtrim($base, '/') . '/v3/';

        if ($this->accessToken === '') {
            throw new \RuntimeException('ASAAS access token não configurado (ASSAS_SANDBOX_ACCESS_TOKEN ou ASSAS_PROD_ACCESS_TOKEN)');
        }
    }

    /**
     * Busca cliente por CPF/CNPJ ou cria novo.
     *
     * @param array{name: string, cpfCnpj: string, email?: string} $data
     * @return array{id: string, ...}
     */
    public function findOrCreateClient(array $data): array
    {
        $cpfCnpj = preg_replace('/\D/', '', $data['cpfCnpj'] ?? '');
        if ($cpfCnpj === '') {
            throw new \InvalidArgumentException('CPF/CNPJ é obrigatório.');
        }

        $response = $this->httpClient->request('GET', $this->baseURL . 'customers', [
            'headers' => $this->getHeaders(),
            'query'   => ['cpfCnpj' => $cpfCnpj],
        ]);

        $result = $response->toArray(false);
        if (!empty($result['data'])) {
            return $result['data'][0];
        }

        $payload = [
            'name'    => $data['name'] ?? 'Cliente',
            'cpfCnpj' => $cpfCnpj,
        ];
        if (!empty($data['email'])) {
            $payload['email'] = $data['email'];
        }

        $response = $this->httpClient->request('POST', $this->baseURL . 'customers', [
            'headers' => $this->getHeaders(),
            'json'    => $payload,
        ]);

        if ($response->getStatusCode() !== Response::HTTP_OK) {
            $erros = $response->toArray(false);
            $msg = $erros['errors'][0]['description'] ?? 'Erro ao criar cliente no ASAAS';
            throw new \RuntimeException($msg, $response->getStatusCode());
        }

        return $response->toArray();
    }

    private function getHeaders(): array
    {
        return [
            'accept'       => 'application/json',
            'content-type' => 'application/json',
            'access_token' => $this->accessToken,
        ];
    }

    public function getBaseURL(): string
    {
        return $this->baseURL;
    }

    public function getAccessToken(): string
    {
        return $this->accessToken;
    }
}
