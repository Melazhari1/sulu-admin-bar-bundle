<?php

declare(strict_types=1);

/*
 * This file is part of the AdminBarBundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Elazhari\SuluAdminBarBundle\Tests\EventListener;

use Elazhari\SuluAdminBarBundle\EventListener\AdminSessionCookieListener;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\SecurityBundle\Entity\User;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class AdminSessionCookieListenerTest extends TestCase
{
    public function testSetsCookieForAuthenticatedRequestWithoutCookie(): void
    {
        $response = $this->dispatch(true, Request::create('/admin'));

        $cookie = $this->findCookie($response);
        self::assertInstanceOf(Cookie::class, $cookie);
        self::assertSame('1', $cookie->getValue());
        self::assertSame(0, $cookie->getExpiresTime(), 'must be a session cookie');
        self::assertFalse($cookie->isHttpOnly(), 'must stay readable by the loader script');
        self::assertSame(Cookie::SAMESITE_LAX, \strtolower((string) $cookie->getSameSite()));
    }

    public function testDoesNothingWhenCookieAlreadyPresent(): void
    {
        $request = Request::create('/admin');
        $request->cookies->set(AdminSessionCookieListener::COOKIE_NAME, '1');

        $response = $this->dispatch(true, $request);

        self::assertNull($this->findCookie($response));
    }

    public function testClearsStaleCookieForAnonymousRequest(): void
    {
        $request = Request::create('/admin');
        $request->cookies->set(AdminSessionCookieListener::COOKIE_NAME, '1');

        $response = $this->dispatch(false, $request);

        $cookie = $this->findCookie($response);
        self::assertInstanceOf(Cookie::class, $cookie);
        self::assertTrue($cookie->isCleared(), 'stale marker must be expired');
    }

    public function testDoesNothingForAnonymousRequestWithoutCookie(): void
    {
        $response = $this->dispatch(false, Request::create('/admin'));

        self::assertNull($this->findCookie($response));
    }

    public function testIgnoresSubRequests(): void
    {
        $tokenStorage = $this->createTokenStorage(true);
        $listener = new AdminSessionCookieListener($tokenStorage);

        $response = new Response();
        $listener->onKernelResponse(new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            Request::create('/admin'),
            HttpKernelInterface::SUB_REQUEST,
            $response
        ));

        self::assertNull($this->findCookie($response));
    }

    private function dispatch(bool $authenticated, Request $request): Response
    {
        $listener = new AdminSessionCookieListener($this->createTokenStorage($authenticated));

        $response = new Response();
        $listener->onKernelResponse(new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            $this->mainRequestType(),
            $response
        ));

        return $response;
    }

    private function createTokenStorage(bool $authenticated): TokenStorageInterface
    {
        $tokenStorage = $this->createMock(TokenStorageInterface::class);

        if (!$authenticated) {
            $tokenStorage->method('getToken')->willReturn(null);

            return $tokenStorage;
        }

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($this->createMock(User::class));
        $tokenStorage->method('getToken')->willReturn($token);

        return $tokenStorage;
    }

    private function mainRequestType(): int
    {
        // MAIN_REQUEST only exists since Symfony 5.3, MASTER_REQUEST was
        // removed in Symfony 6 — both share the value.
        return \defined(HttpKernelInterface::class . '::MAIN_REQUEST')
            ? \constant(HttpKernelInterface::class . '::MAIN_REQUEST')
            : \constant(HttpKernelInterface::class . '::MASTER_REQUEST');
    }

    private function findCookie(Response $response): ?Cookie
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if (AdminSessionCookieListener::COOKIE_NAME === $cookie->getName()) {
                return $cookie;
            }
        }

        return null;
    }
}
