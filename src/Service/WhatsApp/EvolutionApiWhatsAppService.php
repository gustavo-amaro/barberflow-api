<?php

namespace App\Service\WhatsApp;

use App\Entity\Shop;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

/**
 * Implementação usando Evolution API (https://evolution-api.com).
 * Cada barbearia tem sua própria instância; envio usa a config da Shop.
 */
class EvolutionApiWhatsAppService implements WhatsAppServiceInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $evolutionBaseUrl,
        private string $evolutionGlobalApiKey = '',
    ) {
        $this->evolutionBaseUrl = rtrim($evolutionBaseUrl, '/');
    }

    public function isEnabled(Shop $shop): bool
    {
        if ($this->evolutionBaseUrl === '') {
            return false;
        }
        $name = $shop->getEvolutionInstanceName();
        return $name !== null && $name !== '';
    }

    public function sendText(Shop $shop, string $phone, string $message): bool
    {
        if (!$this->isEnabled($shop)) {
            return false;
        }

        $phone = $this->normalizePhone($phone);
        if ($phone === '') {
            return false;
        }

        $instanceName = $shop->getEvolutionInstanceName();
        $url = sprintf('%s/message/sendText/%s', $this->evolutionBaseUrl, $instanceName);
        $headers = [
            'Content-Type' => 'application/json',
        ];
        $apiKey = $shop->getEvolutionInstanceApiKey();
        if ($apiKey !== null && $apiKey !== '') {
            $headers['apikey'] = $apiKey;
        } elseif ($this->evolutionGlobalApiKey !== '') {
            $headers['apikey'] = $this->evolutionGlobalApiKey;
        }

        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => $headers,
                'json' => [
                    'number' => $phone,
                    'text' => $message,
                ],
                'timeout' => 15,
            ]);

            $status = $response->getStatusCode();
            if ($status >= 200 && $status < 300) {
                return true;
            }

            return false;
        } catch (ExceptionInterface $e) {
            throw new \RuntimeException('Falha ao enviar WhatsApp: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Normaliza o número para formato internacional (apenas dígitos, ex: 5511999999999).
     */
    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        if ($digits === '') {
            return '';
        }
        // Brasil: se já tem 11 dígitos (DDD+9+número), adiciona 55
        if (strlen($digits) === 11 && str_starts_with($digits, '0') === false) {
            return '55' . $digits;
        }
        if (strlen($digits) === 12 && str_starts_with($digits, '55')) {
            return $digits;
        }
        if (strlen($digits) >= 10) {
            return '55' . $digits;
        }
        return $digits;
    }
}
