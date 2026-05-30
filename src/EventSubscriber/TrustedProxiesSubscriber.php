<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class TrustedProxiesSubscriber implements EventSubscriberInterface
{
    public function onKernelRequest(RequestEvent $event): void
    {
        // Production proxy trust is configured in config/packages/framework.yaml.
        if (($_ENV['APP_ENV'] ?? 'dev') === 'prod') {
            return;
        }

        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $remoteAddr = $request->server->get('REMOTE_ADDR');

        // Trust the proxy IP (ngrok's IP)
        // Collect all possible proxy IPs
        $trustedProxies = [];
        if ($remoteAddr) {
            $trustedProxies[] = $remoteAddr;
        }
        // Also trust common localhost addresses
        $trustedProxies[] = '127.0.0.1';
        $trustedProxies[] = '::1';

        // Trust proxy headers for ngrok/reverse proxies
        // This allows Symfony to detect HTTPS when behind a proxy
        Request::setTrustedProxies(
            array_filter(array_unique($trustedProxies)),
            Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO
        );
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Must run early, before any other listeners
            KernelEvents::REQUEST => ['onKernelRequest', 1000],
        ];
    }
}

