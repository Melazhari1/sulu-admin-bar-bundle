<?php

declare(strict_types=1);

/*
 * This file is part of the AdminBarBundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Elazhari\SuluAdminBarBundle\EventListener;

use Sulu\Bundle\SecurityBundle\Entity\User;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Maintains a JavaScript-readable marker cookie that tells the frontend
 * loader whether an admin session exists at all.
 *
 * Without it, every anonymous visitor would fire a request against the
 * admin bar endpoint just to receive a 401. With the marker, the loader
 * only calls the endpoint when the cookie is present; the cookie itself
 * carries no data and grants nothing — the endpoint stays the single
 * authenticated source of truth.
 *
 * The listener only runs in the Sulu "admin" context (see services.yaml):
 * it sets the cookie on any response to an authenticated admin request
 * (covering logins as well as already existing sessions) and removes it
 * again when an admin request is answered without an authenticated user
 * (logout or expired session). Website responses are never touched, so
 * they stay fully HTTP cacheable.
 */
class AdminSessionCookieListener
{
    public const COOKIE_NAME = 'sulu_admin_bar';

    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    public function __construct(TokenStorageInterface $tokenStorage)
    {
        $this->tokenStorage = $tokenStorage;
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        // isMainRequest() only exists since Symfony 5.3.
        $isMainRequest = \method_exists($event, 'isMainRequest')
            ? $event->isMainRequest()
            : $event->isMasterRequest();

        if (!$isMainRequest) {
            return;
        }

        $request = $event->getRequest();
        $token = $this->tokenStorage->getToken();
        $authenticated = null !== $token && $token->getUser() instanceof User;
        $hasCookie = $request->cookies->has(self::COOKIE_NAME);

        if ($authenticated && !$hasCookie) {
            $event->getResponse()->headers->setCookie(
                Cookie::create(
                    self::COOKIE_NAME,
                    '1',
                    0, // session cookie
                    '/',
                    null,
                    $request->isSecure(),
                    false, // must be readable by the loader script
                    false,
                    Cookie::SAMESITE_LAX
                )
            );
        } elseif (!$authenticated && $hasCookie) {
            $event->getResponse()->headers->clearCookie(self::COOKIE_NAME, '/');
        }
    }
}
