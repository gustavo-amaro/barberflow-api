<?php

namespace App\Tests\Unit;

use App\Controller\Api\ShopWhatsAppController;
use App\Entity\Shop;
use App\Entity\User;
use App\Service\WhatsApp\EvolutionApiManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class ShopWhatsAppControllerTest extends TestCase
{
    public function testDisconnectClearsTheRemovedEvolutionInstance(): void
    {
        $shop = (new Shop())
            ->setEvolutionInstanceName('barberflow-42')
            ->setEvolutionInstanceApiKey('instance-api-key');
        $user = (new User())->setShop($shop);

        $manager = $this->createMock(EvolutionApiManager::class);
        $manager->expects(self::once())
            ->method('isConfigured')
            ->willReturn(true);
        $manager->expects(self::once())
            ->method('disconnect')
            ->with($shop)
            ->willReturn(['success' => true]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'api'));
        $container = new Container();
        $container->set('security.token_storage', $tokenStorage);

        $controller = new ShopWhatsAppController($manager, $entityManager);
        $controller->setContainer($container);
        $response = $controller->disconnect();

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([
            'instanceName' => null,
            'state' => 'close',
            'message' => 'WhatsApp desconectado. Conecte novamente quando quiser.',
        ], json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR));
        self::assertNull($shop->getEvolutionInstanceName());
        self::assertNull($shop->getEvolutionInstanceApiKey());
    }
}
