<?php

namespace App\EventSubscriber;

use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;

class AuthCookieSubscriber implements EventSubscriberInterface
{
    public function __construct(private string $appEnvironment) {}
    public static function getSubscribedEvents(): array
    {
        return [
            Events::AUTHENTICATION_SUCCESS => ['onAuthenticationSuccess', -2048],
        ];
    }

    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        $data = $event->getData();
        $response = $event->getResponse();
        $secure = $this->appEnvironment === 'prod';
        if (!empty($data['token'])) {
            $response->headers->setCookie(Cookie::create('lb_access')
                ->withValue($data['token'])->withPath('/api')->withSecure($secure)
                ->withHttpOnly(true)->withSameSite(Cookie::SAMESITE_LAX)
                ->withExpires(new \DateTimeImmutable('+15 minutes')));
            unset($data['token']);
        }
        $data['authenticated'] = true;
        $event->setData($data);
    }
}
