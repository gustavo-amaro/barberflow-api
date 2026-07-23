<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class MustChangePasswordSubscriber implements EventSubscriberInterface
{
    public function __construct(private Security $security) {}
    public static function getSubscribedEvents(): array { return [KernelEvents::CONTROLLER => 'onController']; }
    public function onController(ControllerEvent $event): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User || !$user->mustChangePassword()) return;
        $path = $event->getRequest()->getPathInfo();
        if (in_array($path, ['/api/me', '/api/change-password', '/api/logout'], true)) return;
        $event->setController(fn () => new JsonResponse([
            'error' => 'Troque sua senha temporária antes de continuar.',
            'code' => 'PASSWORD_CHANGE_REQUIRED',
        ], 403));
    }
}
