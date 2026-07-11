<?php

declare(strict_types=1);

/*
 * This file is part of the AdminBarBundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Elazhari\SuluAdminBarBundle\Tests\Twig;

use Elazhari\SuluAdminBarBundle\Tests\Fixtures\Formation;
use Elazhari\SuluAdminBarBundle\Twig\AdminBarExtension;
use PHPUnit\Framework\TestCase;
use Sulu\Component\Content\Compat\Structure\PageBridge;
use Sulu\Component\Localization\Localization;
use Sulu\Component\Webspace\Analyzer\Attributes\RequestAttributes;
use Sulu\Component\Webspace\Webspace;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;

class AdminBarExtensionTest extends TestCase
{
    private const LABELS = ['edit' => 'Edit', 'add' => 'Add new', 'logout' => 'Logout'];

    public function testRegistersTheTwigFunction(): void
    {
        $functions = $this->createExtension(new RequestStack())->getFunctions();

        self::assertCount(1, $functions);
        self::assertSame('sulu_admin_bar', $functions[0]->getName());
    }

    public function testRendersNothingWhenDisabled(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/'));

        $extension = new AdminBarExtension($requestStack, false, [], self::LABELS);

        self::assertSame('', $extension->renderAdminBar($this->createNeverRenderingTwig()));
    }

    public function testRendersNothingWithoutRequest(): void
    {
        $extension = $this->createExtension(new RequestStack());

        self::assertSame('', $extension->renderAdminBar($this->createNeverRenderingTwig()));
    }

    public function testRendersNothingInsideTheAdminPreview(): void
    {
        $request = Request::create('/');
        $request->attributes->set('preview', true);
        $request->attributes->set('partial', false);

        self::assertSame('', $this->renderWith($request, static function (): void {}, true));
    }

    public function testResolvesPageContextFromSulu2Structure(): void
    {
        if (!\class_exists(PageBridge::class)) {
            self::markTestSkipped('PageBridge only exists on Sulu 2.');
        }

        $webspace = $this->createMock(Webspace::class);
        $webspace->method('getKey')->willReturn('website');

        $localization = $this->createMock(Localization::class);
        $localization->method('getLocale')->willReturn('fr');

        $structure = $this->createMock(PageBridge::class);
        $structure->method('getUuid')->willReturn('11111111-2222-3333-4444-555555555555');
        $structure->method('getLanguageCode')->willReturn('fr');

        $request = Request::create('/');
        $request->attributes->set('_sulu', new RequestAttributes([
            'webspace' => $webspace,
            'localization' => $localization,
        ]));
        $request->attributes->set('structure', $structure);

        $context = $this->resolveContext($request);

        self::assertSame([
            'webspace' => 'website',
            'locale' => 'fr',
            'uuid' => '11111111-2222-3333-4444-555555555555',
            'id' => null,
            'resourceKey' => 'pages',
            'route' => null,
        ], $context);
    }

    public function testResolvesArticleContextFromSulu3Object(): void
    {
        if (!\class_exists(\Sulu\Article\Domain\Model\Article::class)) {
            self::markTestSkipped('The Article content entity only exists on Sulu 3.');
        }

        $article = new \Sulu\Article\Domain\Model\Article('11111111-2222-3333-4444-555555555555');
        $dimensionContent = new \Sulu\Article\Domain\Model\ArticleDimensionContent($article);
        $dimensionContent->setLocale('fr');

        $request = Request::create('/');
        $request->attributes->set('object', $dimensionContent);

        $context = $this->resolveContext($request);

        self::assertSame('articles', $context['resourceKey']);
        self::assertSame('11111111-2222-3333-4444-555555555555', $context['id']);
        self::assertSame('fr', $context['locale']);
        self::assertNull($context['uuid'], 'only pages use the uuid/webspace edit URL');
    }

    public function testAutoDetectsEntitiesByResourceKeyConvention(): void
    {
        $request = Request::create('/');
        $request->attributes->set('_route', 'formation_detail');
        $request->attributes->set('formation', new Formation(12));

        $context = $this->resolveContext($request);

        self::assertSame('formations', $context['resourceKey']);
        self::assertSame('12', $context['id']);
        self::assertNull($context['uuid']);
    }

    public function testIgnoresEntitiesWithoutId(): void
    {
        $request = Request::create('/');
        $request->attributes->set('formation', new Formation(null));

        $context = $this->resolveContext($request);

        self::assertNull($context['resourceKey']);
        self::assertNull($context['id']);
    }

    public function testConfiguredAttributeWinsOverAutoDetection(): void
    {
        $request = Request::create('/');
        // Auto-detection would see "other" first (attribute order) …
        $request->attributes->set('other', new Formation(99));
        $request->attributes->set('formation', new Formation(12));

        $context = $this->resolveContext($request, [
            'formation' => ['resource_key' => 'formations', 'security_context' => null, 'routes' => []],
        ]);

        // … but the configured "formation" attribute is checked first.
        self::assertSame('formations', $context['resourceKey']);
        self::assertSame('12', $context['id']);
    }

    public function testMatchesConfiguredRouteNames(): void
    {
        $request = Request::create('/');
        $request->attributes->set('_route', 'formation_detail');
        $request->attributes->set('id', '34');

        $context = $this->resolveContext($request, [
            'formation' => [
                'resource_key' => 'formations',
                'security_context' => null,
                'routes' => ['formation_detail'],
            ],
        ]);

        self::assertSame('formations', $context['resourceKey']);
        self::assertSame('34', $context['id']);
        self::assertNull($context['route'], 'a configured route needs no endpoint-side detection');
    }

    public function testExposesRouteAndIdForUnmatchedEntityRoutes(): void
    {
        $request = Request::create('/formation/master-marketing/34/2');
        $request->attributes->set('_route', 'formation');
        $request->attributes->set('id', '34');

        $context = $this->resolveContext($request);

        // No resource key resolvable on the website side: the endpoint
        // matches the route (and URL path) against the Doctrine entities.
        self::assertNull($context['resourceKey']);
        self::assertSame('34', $context['id']);
        self::assertSame('formation', $context['route']);
    }

    public function testExposesNoRouteWithoutANumericId(): void
    {
        $request = Request::create('/formations');
        $request->attributes->set('_route', 'formations');
        $request->attributes->set('id', 'not-numeric');

        $context = $this->resolveContext($request);

        self::assertNull($context['route']);
        self::assertNull($context['id']);
    }

    public function testFallsBackToEmptyContext(): void
    {
        $request = Request::create('/');
        $request->setLocale('fr');

        $context = $this->resolveContext($request);

        self::assertSame([
            'webspace' => null,
            'locale' => 'fr',
            'uuid' => null,
            'id' => null,
            'resourceKey' => null,
            'route' => null,
        ], $context);
    }

    public function testPassesLabelsToTheTemplate(): void
    {
        $captured = null;
        $this->renderWith(Request::create('/'), static function (array $parameters) use (&$captured): void {
            $captured = $parameters['labels'];
        });

        self::assertSame(self::LABELS, $captured);
    }

    /**
     * @param array<string, array{resource_key: string, security_context: ?string, routes: string[]}> $entities
     *
     * @return array<string, mixed>
     */
    private function resolveContext(Request $request, array $entities = []): array
    {
        $context = null;
        $this->renderWith($request, static function (array $parameters) use (&$context): void {
            $context = $parameters['context'];
        }, false, $entities);

        self::assertIsArray($context, 'the template must have been rendered');

        return $context;
    }

    /**
     * @param callable $inspector receives the template parameters
     * @param array<string, array{resource_key: string, security_context: ?string, routes: string[]}> $entities
     */
    private function renderWith(Request $request, callable $inspector, bool $expectNoRender = false, array $entities = []): string
    {
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $twig = $this->createMock(Environment::class);

        if ($expectNoRender) {
            $twig->expects(self::never())->method('render');
        } else {
            $twig->expects(self::once())
                ->method('render')
                ->with('@AdminBar/admin_bar.html.twig', self::callback(static function (array $parameters) use ($inspector): bool {
                    $inspector($parameters);

                    return true;
                }))
                ->willReturn('<script></script>');
        }

        return $this->createExtension($requestStack, $entities)->renderAdminBar($twig);
    }

    /**
     * @param array<string, array{resource_key: string, security_context: ?string, routes: string[]}> $entities
     */
    private function createExtension(RequestStack $requestStack, array $entities = []): AdminBarExtension
    {
        return new AdminBarExtension($requestStack, true, $entities, self::LABELS);
    }

    private function createNeverRenderingTwig(): Environment
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects(self::never())->method('render');

        return $twig;
    }
}
