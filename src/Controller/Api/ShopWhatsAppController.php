<?php

namespace App\Controller\Api;

use App\Entity\Shop;
use App\Entity\User;
use App\Service\WhatsApp\EvolutionApiManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/shops/whatsapp')]
class ShopWhatsAppController extends AbstractController
{
    public function __construct(
        private EvolutionApiManager $evolutionManager,
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * Estado da conexão WhatsApp da barbearia (open, close, connecting).
     */
    #[Route('/status', name: 'api_shop_whatsapp_status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        $shop = $this->getShop();
        if (!$shop) {
            return $this->json(['error' => 'Barbearia não encontrada'], Response::HTTP_NOT_FOUND);
        }

        if (!$this->evolutionManager->isConfigured()) {
            return $this->json([
                'configured' => false,
                'instanceName' => null,
                'state' => 'close',
                'message' => 'Evolution API não configurada no servidor.',
            ]);
        }

        $result = $this->evolutionManager->connectionState($shop);
        return $this->json([
            'configured' => true,
            'instanceName' => $shop->getEvolutionInstanceName(),
            'state' => $result['state'],
            'owner' => $result['owner'] ?? null,
            'profileName' => $result['profileName'] ?? null,
            'error' => $result['error'] ?? null,
        ]);
    }

    /**
     * Cria uma instância Evolution para a barbearia e retorna o QR code para escanear.
     * Se já existir instância, retorna erro (use GET qrcode para novo QR).
     */
    #[Route('/create', name: 'api_shop_whatsapp_create', methods: ['POST'])]
    public function create(): JsonResponse
    {
        $shop = $this->getShop();
        if (!$shop) {
            return $this->json(['error' => 'Barbearia não encontrada'], Response::HTTP_NOT_FOUND);
        }

        if ($shop->getEvolutionInstanceName() !== null) {
            return $this->json([
                'error' => 'Esta barbearia já possui uma instância WhatsApp. Use "Atualizar QR" para exibir o código novamente.',
                'instanceName' => $shop->getEvolutionInstanceName(),
            ], Response::HTTP_CONFLICT);
        }

        if (!$this->evolutionManager->isConfigured()) {
            return $this->json(['error' => 'Evolution API não configurada no servidor.'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $result = $this->evolutionManager->createInstanceForShop($shop);

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], Response::HTTP_BAD_REQUEST);
        }

        $shop->setEvolutionInstanceName($result['instanceName']);
        if (!empty($result['instanceApiKey'])) {
            $shop->setEvolutionInstanceApiKey($result['instanceApiKey']);
        }
        $this->entityManager->flush();

        return $this->json([
            'instanceName' => $result['instanceName'],
            'qrcode' => $result['qrcode'],
            'message' => 'Escaneie o QR code com o WhatsApp no celular para conectar.',
        ]);
    }

    /**
     * Obtém o QR code atual da instância (para reconectar ou exibir novamente).
     */
    #[Route('/qrcode', name: 'api_shop_whatsapp_qrcode', methods: ['GET'])]
    public function qrcode(): JsonResponse
    {
        $shop = $this->getShop();
        if (!$shop) {
            return $this->json(['error' => 'Barbearia não encontrada'], Response::HTTP_NOT_FOUND);
        }

        if (!$shop->getEvolutionInstanceName()) {
            return $this->json([
                'error' => 'Nenhuma instância WhatsApp configurada. Use "Conectar WhatsApp" para criar.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $result = $this->evolutionManager->fetchQrcode($shop);

        if (isset($result['error'])) {
            return $this->json(['error' => $result['error']], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'qrcode' => $result['qrcode'],
            'message' => 'Escaneie o QR code com o WhatsApp no celular.',
        ]);
    }

    private function getShop(): ?Shop
    {
        /** @var User|null $user */
        $user = $this->getUser();
        return $user?->getShop();
    }
}
