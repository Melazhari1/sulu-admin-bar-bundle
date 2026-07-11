<?php

declare(strict_types=1);

/*
 * This file is part of the AdminBarBundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Elazhari\SuluAdminBarBundle\Tests\Controller;

use Elazhari\SuluAdminBarBundle\Controller\AdminBarController;
use Elazhari\SuluAdminBarBundle\Resolver\EntityResourceKeyResolver;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\SecurityBundle\Entity\User;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Sulu\Component\Webspace\Manager\WebspaceCollection;
use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;
use Sulu\Component\Webspace\Webspace;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class AdminBarControllerTest extends TestCase
{
    private const UUID = '11111111-2222-3333-4444-555555555555';

    public function testReturns401ForAnonymousUsers(): void
    {
        $controller = $this->createController(null, []);

        $response = $controller->infoAction(Request::create('/_private/admin-bar'));

        self::assertSame(401, $response->getStatusCode());
        self::assertFalse($this->decode($response)['authenticated']);
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function testReturns401WhenNoWebspaceExists(): void
    {
        $controller = $this->createController($this->createUser('John Doe'), []);

        $response = $controller->infoAction(Request::create('/_private/admin-bar'));

        self::assertSame(401, $response->getStatusCode());
    }

    public function testBuildsPageUrlsForAuthorizedUsers(): void
    {
        $controller = $this->createController(
            $this->createUser('John Doe'),
            ['website' => ['fr', 'en']],
            ['sulu.webspaces.website' => [PermissionTypes::EDIT => true, PermissionTypes::ADD => true]]
        );

        $response = $controller->infoAction(Request::create('/_private/admin-bar', 'GET', [
            'webspace' => 'website',
            'locale' => 'fr',
            'resourceKey' => 'pages',
            'uuid' => self::UUID,
        ]));

        $data = $this->decode($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($data['authenticated']);
        self::assertSame('John Doe', $data['user']['name']);
        self::assertSame('/admin', $data['urls']['admin']);
        self::assertSame('/admin/logout', $data['urls']['logout']);
        self::assertSame('/admin#/webspaces/website/pages/fr/' . self::UUID, $data['urls']['edit']);
        self::assertSame('/admin#/webspaces/website/pages/fr/add/' . self::UUID, $data['urls']['add']);
    }

    public function testHidesPageUrlsWithoutPermission(): void
    {
        $controller = $this->createController(
            $this->createUser('John Doe'),
            ['website' => ['fr']],
            ['sulu.webspaces.website' => [PermissionTypes::EDIT => false, PermissionTypes::ADD => false]]
        );

        $response = $controller->infoAction(Request::create('/_private/admin-bar', 'GET', [
            'webspace' => 'website',
            'locale' => 'fr',
            'resourceKey' => 'pages',
            'uuid' => self::UUID,
        ]));

        $data = $this->decode($response);

        self::assertNull($data['urls']['edit']);
        self::assertNull($data['urls']['add']);
    }

    public function testRejectsInvalidUuids(): void
    {
        $controller = $this->createController(
            $this->createUser('John Doe'),
            ['website' => ['fr']],
            ['sulu.webspaces.website' => [PermissionTypes::EDIT => true, PermissionTypes::ADD => true]]
        );

        $response = $controller->infoAction(Request::create('/_private/admin-bar', 'GET', [
            'webspace' => 'website',
            'locale' => 'fr',
            'resourceKey' => 'pages',
            'uuid' => '<script>alert(1)</script>',
        ]));

        $data = $this->decode($response);

        // Not a valid page context: no edit link, add falls back to the list.
        self::assertNull($data['urls']['edit']);
        self::assertSame('/admin#/webspaces/website/pages/fr', $data['urls']['add']);
    }

    public function testFallsBackToTheDefaultLocale(): void
    {
        $controller = $this->createController(
            $this->createUser('John Doe'),
            ['website' => ['fr', 'en']],
            ['sulu.webspaces.website' => [PermissionTypes::EDIT => true, PermissionTypes::ADD => true]]
        );

        $response = $controller->infoAction(Request::create('/_private/admin-bar', 'GET', [
            'webspace' => 'website',
            'locale' => 'xx',
            'resourceKey' => 'pages',
            'uuid' => self::UUID,
        ]));

        self::assertSame(
            '/admin#/webspaces/website/pages/fr/' . self::UUID,
            $this->decode($response)['urls']['edit']
        );
    }

    public function testBuildsEntityUrlsFromTheViewRegistry(): void
    {
        $controller = $this->createController(
            $this->createUser('John Doe'),
            ['website' => ['fr']],
            [],
            [],
            $this->createViewRegistry([
                ['resourceKey' => 'formations', 'path' => '/formations/:locale/formations/:id'],
                ['resourceKey' => 'formations', 'path' => '/formations/:locale/formations/add'],
            ])
        );

        $response = $controller->infoAction(Request::create('/_private/admin-bar', 'GET', [
            'webspace' => 'website',
            'locale' => 'fr',
            'resourceKey' => 'formations',
            'id' => '12',
        ]));

        $data = $this->decode($response);

        self::assertSame('/admin#/formations/fr/formations/12', $data['urls']['edit']);
        self::assertSame('/admin#/formations/fr/formations/add', $data['urls']['add']);
    }

    public function testBuildsArticleUrlsFromUuidIds(): void
    {
        // Sulu articles use UUID ids; the views mirror the paths Sulu 3's
        // ArticleAdmin registers ("default" article type group).
        $controller = $this->createController(
            $this->createUser('John Doe'),
            ['website' => ['fr']],
            [],
            [],
            $this->createViewRegistry([
                ['resourceKey' => 'articles', 'path' => '/:locale/default/:id'],
                ['resourceKey' => 'articles', 'path' => '/:locale/default/add'],
            ])
        );

        $response = $controller->infoAction(Request::create('/_private/admin-bar', 'GET', [
            'webspace' => 'website',
            'locale' => 'fr',
            'resourceKey' => 'articles',
            'id' => self::UUID,
        ]));

        $data = $this->decode($response);

        self::assertSame('/admin#/fr/default/' . self::UUID, $data['urls']['edit']);
        self::assertSame('/admin#/fr/default/add', $data['urls']['add']);
    }

    public function testRejectsNonNumericNonUuidEntityIds(): void
    {
        $controller = $this->createController(
            $this->createUser('John Doe'),
            ['website' => ['fr']],
            [],
            [],
            $this->createViewRegistry([
                ['resourceKey' => 'articles', 'path' => '/:locale/default/:id'],
            ])
        );

        $response = $controller->infoAction(Request::create('/_private/admin-bar', 'GET', [
            'webspace' => 'website',
            'locale' => 'fr',
            'resourceKey' => 'articles',
            'id' => '<script>alert(1)</script>',
        ]));

        self::assertNull($this->decode($response)['urls']['edit']);
    }

    public function testEntityUrlsRespectTheConfiguredSecurityContext(): void
    {
        $controller = $this->createController(
            $this->createUser('John Doe'),
            ['website' => ['fr']],
            ['sulu.formations.formation' => [PermissionTypes::EDIT => true, PermissionTypes::ADD => false]],
            [
                'formation' => [
                    'resource_key' => 'formations',
                    'security_context' => 'sulu.formations.formation',
                    'routes' => [],
                ],
            ],
            $this->createViewRegistry([
                ['resourceKey' => 'formations', 'path' => '/formations/:locale/formations/:id'],
                ['resourceKey' => 'formations', 'path' => '/formations/:locale/formations/add'],
            ])
        );

        $response = $controller->infoAction(Request::create('/_private/admin-bar', 'GET', [
            'webspace' => 'website',
            'locale' => 'fr',
            'resourceKey' => 'formations',
            'id' => '12',
        ]));

        $data = $this->decode($response);

        self::assertSame('/admin#/formations/fr/formations/12', $data['urls']['edit']);
        self::assertNull($data['urls']['add'], 'ADD permission is missing');
    }

    public function testDerivesTheResourceKeyFromRouteAndPath(): void
    {
        $resolver = $this->createMock(EntityResourceKeyResolver::class);
        $resolver->expects(self::once())
            ->method('resolve')
            ->with('formation', '/formation/master-marketing/12/9')
            ->willReturn('formations');

        $controller = $this->createController(
            $this->createUser('John Doe'),
            ['website' => ['fr']],
            [],
            [],
            $this->createViewRegistry([
                ['resourceKey' => 'formations', 'path' => '/formations/:locale/formations/:id'],
                ['resourceKey' => 'formations', 'path' => '/formations/:locale/formations/add'],
            ]),
            $resolver
        );

        // No "resourceKey": the page is served by a plain Symfony route and
        // only carries the route name, path and numeric id.
        $response = $controller->infoAction(Request::create('/_private/admin-bar', 'GET', [
            'webspace' => 'website',
            'locale' => 'fr',
            'id' => '12',
            'route' => 'formation',
            'path' => '/formation/master-marketing/12/9',
        ]));

        $data = $this->decode($response);

        self::assertSame('/admin#/formations/fr/formations/12', $data['urls']['edit']);
        self::assertSame('/admin#/formations/fr/formations/add', $data['urls']['add']);
    }

    public function testDoesNotConsultTheResolverWithoutANumericId(): void
    {
        $resolver = $this->createMock(EntityResourceKeyResolver::class);
        $resolver->expects(self::never())->method('resolve');

        $controller = $this->createController(
            $this->createUser('John Doe'),
            ['website' => ['fr']],
            [],
            [],
            null,
            $resolver
        );

        $response = $controller->infoAction(Request::create('/_private/admin-bar', 'GET', [
            'webspace' => 'website',
            'locale' => 'fr',
            'route' => 'formation',
            'path' => '/formation/master-marketing',
        ]));

        self::assertNull($this->decode($response)['urls']['edit']);
    }

    public function testEntityUrlsAreNullWithoutMatchingViews(): void
    {
        $controller = $this->createController(
            $this->createUser('John Doe'),
            ['website' => ['fr']],
            [],
            [],
            $this->createViewRegistry([])
        );

        $response = $controller->infoAction(Request::create('/_private/admin-bar', 'GET', [
            'webspace' => 'website',
            'locale' => 'fr',
            'resourceKey' => 'formations',
            'id' => '12',
        ]));

        $data = $this->decode($response);

        self::assertNull($data['urls']['edit']);
        self::assertNull($data['urls']['add']);
    }

    public function testFallsBackToTheUsernameWithoutFullName(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getFullName')->willReturn(' ');
        $user->method('getUserIdentifier')->willReturn('admin');
        $user->method('getUsername')->willReturn('admin');

        $controller = $this->createController($user, ['website' => ['fr']]);

        $response = $controller->infoAction(Request::create('/_private/admin-bar', 'GET', [
            'webspace' => 'website',
        ]));

        self::assertSame('admin', $this->decode($response)['user']['name']);
    }

    /**
     * @param array<string, string[]> $webspaces webspace key => locales (first one is the default)
     * @param array<string, array<string, bool>> $permissions security context => permission type => granted
     * @param array<string, array{resource_key: string, security_context: ?string, routes: string[]}> $entities
     * @param object|null $viewRegistry
     */
    private function createController(
        ?User $user,
        array $webspaces,
        array $permissions = [],
        array $entities = [],
        $viewRegistry = null,
        ?EntityResourceKeyResolver $entityResourceKeyResolver = null
    ): AdminBarController {
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        if (null !== $user) {
            $token = $this->createMock(TokenInterface::class);
            $token->method('getUser')->willReturn($user);
            $tokenStorage->method('getToken')->willReturn($token);
        }

        $securityChecker = $this->createMock(SecurityCheckerInterface::class);
        $securityChecker->method('hasPermission')
            ->willReturnCallback(static function ($context, $permission) use ($permissions): bool {
                return $permissions[$context][$permission] ?? false;
            });

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnMap([
            ['sulu_admin', [], UrlGeneratorInterface::ABSOLUTE_PATH, '/admin'],
            ['sulu_admin.logout', [], UrlGeneratorInterface::ABSOLUTE_PATH, '/admin/logout'],
        ]);

        $webspaceManager = $this->createMock(WebspaceManagerInterface::class);
        $webspaceManager->method('getWebspaceCollection')
            ->willReturn($this->createWebspaceCollection($webspaces));

        return new AdminBarController(
            $tokenStorage,
            $securityChecker,
            $urlGenerator,
            $webspaceManager,
            $entities,
            $viewRegistry,
            $entityResourceKeyResolver
        );
    }

    /**
     * @param array<string, string[]> $webspaces
     */
    private function createWebspaceCollection(array $webspaces): WebspaceCollection
    {
        $webspaceMocks = [];
        foreach ($webspaces as $key => $locales) {
            $localizations = [];
            foreach ($locales as $locale) {
                $localization = $this->createMock(\Sulu\Component\Localization\Localization::class);
                $localization->method('getLocale')->willReturn($locale);
                $localizations[] = $localization;
            }

            $webspace = $this->createMock(Webspace::class);
            $webspace->method('getKey')->willReturn($key);
            $webspace->method('getAllLocalizations')->willReturn($localizations);
            $webspace->method('getDefaultLocalization')->willReturn($localizations[0]);

            $webspaceMocks[$key] = $webspace;
        }

        $collection = $this->createMock(WebspaceCollection::class);
        $collection->method('getWebspace')->willReturnCallback(
            static function (string $key) use ($webspaceMocks): ?Webspace {
                return $webspaceMocks[$key] ?? null;
            }
        );
        $collection->method('getWebspaces')->willReturn($webspaceMocks);

        return $collection;
    }

    /**
     * @param array<array{resourceKey: string, path: string}> $views
     */
    private function createViewRegistry(array $views): object
    {
        $viewObjects = \array_map(static function (array $view): object {
            return new class($view['resourceKey'], $view['path']) {
                /** @var string */
                private $resourceKey;

                /** @var string */
                private $path;

                public function __construct(string $resourceKey, string $path)
                {
                    $this->resourceKey = $resourceKey;
                    $this->path = $path;
                }

                /**
                 * @return string|null
                 */
                public function getOption(string $name)
                {
                    return 'resourceKey' === $name ? $this->resourceKey : null;
                }

                public function getPath(): string
                {
                    return $this->path;
                }
            };
        }, $views);

        return new class($viewObjects) {
            /** @var object[] */
            private $views;

            /**
             * @param object[] $views
             */
            public function __construct(array $views)
            {
                $this->views = $views;
            }

            /**
             * @return object[]
             */
            public function getViews(): array
            {
                return $this->views;
            }
        };
    }

    private function createUser(string $fullName): User
    {
        $user = $this->createMock(User::class);
        $user->method('getFullName')->willReturn($fullName);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(\Symfony\Component\HttpFoundation\JsonResponse $response): array
    {
        return \json_decode((string) $response->getContent(), true);
    }
}
