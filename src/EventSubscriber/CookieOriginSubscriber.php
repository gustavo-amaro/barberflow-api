<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class CookieOriginSubscriber implements EventSubscriberInterface
{
    public function __construct(private string $frontendUrl) {}
    public static function getSubscribedEvents(): array { return [KernelEvents::REQUEST => ['validateOrigin', 32]]; }

    public function validateOrigin(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true)
            || !$request->cookies->has('lb_access')) return;
        $expected = $this->origin($this->frontendUrl);
        $origin = $this->origin((string) $request->headers->get('Origin'));
        if ($expected === null || $origin !== $expected) {
            $event->setResponse(new JsonResponse(['error' => 'Origem da requisição não permitida'], 403));
        }
    }

    private function origin(string $url): ?string
    {
        $parts = parse_url($url);
        if (!isset($parts['scheme'], $parts['host'])) return null;
        return strtolower($parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : ''));
    }
}
