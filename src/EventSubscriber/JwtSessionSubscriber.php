<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Repository\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class JwtSessionSubscriber implements EventSubscriberInterface
{
    public function __construct(private UserRepository $users) {}
    public static function getSubscribedEvents(): array
    {
        return [Events::JWT_CREATED => 'onCreated', Events::JWT_DECODED => 'onDecoded'];
    }
    public function onCreated(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) return;
        $data = $event->getData();
        $data['session_version'] = $user->getSessionVersion();
        $event->setData($data);
    }
    public function onDecoded(JWTDecodedEvent $event): void
    {
        $payload = $event->getPayload();
        $email = (string) ($payload['username'] ?? '');
        $user = $email !== '' ? $this->users->findByEmail($email) : null;
        if (!$user || (int) ($payload['session_version'] ?? -1) !== $user->getSessionVersion()) {
            $event->markAsInvalid();
        }
    }
}
