<?php

namespace App\Service\WhatsApp;

use App\Entity\Shop;

interface WhatsAppServiceInterface
{
    /**
     * Envia uma mensagem de texto para um número de WhatsApp.
     *
     * @param bool $toShop Se true, usa a instância global (EVOLUTION_GLOBAL_INSTANCE) para enviar à barbearia;
     *                     se false, usa a instância da barbearia para enviar ao cliente.
     * @param string $phone Número no formato internacional sem + (ex: 5511999999999)
     * @param string $message Texto da mensagem
     *
     * @return bool True se enviado com sucesso
     *
     * @throws \Exception Em caso de falha na API
     */
    public function sendText(Shop $shop, string $phone, string $message, bool $toShop = false): bool;

    /**
     * Indica se a barbearia tem WhatsApp (Evolution) configurado para envio ao cliente (instância da barbearia).
     */
    public function isEnabled(Shop $shop): bool;

    /**
     * Indica se está configurada a instância global para envio de mensagens à barbearia.
     */
    public function canSendToShop(): bool;
}
