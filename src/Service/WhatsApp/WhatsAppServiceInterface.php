<?php

namespace App\Service\WhatsApp;

interface WhatsAppServiceInterface
{
    /**
     * Envia uma mensagem de texto para um número de WhatsApp.
     *
     * @param string $phone Número no formato internacional sem + (ex: 5511999999999)
     * @param string $message Texto da mensagem
     *
     * @return bool True se enviado com sucesso
     *
     * @throws \Exception Em caso de falha na API
     */
    public function sendText(string $phone, string $message): bool;

    /**
     * Indica se o serviço está configurado e disponível para envio.
     */
    public function isEnabled(): bool;
}
