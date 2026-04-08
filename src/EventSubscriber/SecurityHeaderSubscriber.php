<?php

/*
SPDX-License-Identifier: MIT
SPDX-FileCopyrightText: 2026 Project Tick
SPDX-FileContributor: Project Tick
*/

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class SecurityHeaderSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        $headers = $response->headers;

        // Security Hardening Headers
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('X-XSS-Protection', '1; mode=block');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        
        // Permissions Policy
        $headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=(), magnetometer=(), gyroscope=(), fullscreen=(self), payment=()');

        // CSP - Move from meta tag to header for better security and control
        // We'll keep it permissive enough for Google Fonts and YouTube as seen in base.html.twig
        $csp = "default-src 'self'; " .
               "script-src 'self' 'unsafe-inline' https:; " .
               "img-src 'self' data: https:; " .
               "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; " .
               "font-src 'self' https://fonts.gstatic.com; " .
               "frame-src 'self' https://www.fsf.org https://www.youtube.com https://player.vimeo.com; " .
               "connect-src 'self' https:;";
        
        $headers->set('Content-Security-Policy', $csp);
    }
}
