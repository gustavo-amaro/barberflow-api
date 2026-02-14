<?php

namespace App\Service\WhatsApp;

use App\Entity\Shop;

interface WhatsAppServiceInterface
{
    /**
     * Envia uma mensagem de texto para um número de WhatsApp usando a instância Evolution da barbearia.
     *
     * @param string $phone Número no formato internacional sem + (ex: 5511999999999)
     * @param string $message Texto da mensagem
     *
     * @return bool True se enviado com sucesso
     *
     * @throws \Exception Em caso de falha na API
     */
    public function sendText(Shop $shop, string $phone, string $message): bool;

    /**
     * Indica se a barbearia tem WhatsApp (Evolution) configurado e disponível para envio.
     */
    public function isEnabled(Shop $shop): bool;
}
