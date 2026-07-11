<?php

declare(strict_types=1);

/*
 * This file is part of the AdminBarBundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Elazhari\SuluAdminBarBundle\Twig;

use Sulu\Component\Localization\Localization;
use Sulu\Component\Webspace\Analyzer\Attributes\RequestAttributes;
use Sulu\Component\Webspace\Webspace;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Provides the "sulu_admin_bar()" Twig function which renders the loader
 * snippet of the frontend admin bar.
 *
 * The snippet itself is identical for every visitor (and therefore safe to
 * cache): the user-specific bar is only injected client-side after a
 * successful call to the authenticated admin bar endpoint.
 *
 * Detects the current page for both Sulu major versions:
 *  - Sulu 3: "object" request attribute (DimensionContentInterface,
 *    covering pages, articles and any other content entity)
 *  - Sulu 2: "structure" request attribute (PageBridge) and, with
 *    SuluArticleBundle, the "object" attribute (ArticleDocument)
 * The instanceof checks against version specific classes are safe because
 * instanceof never triggers autoloading and simply yields false when the
 * class does not exist.
 *
 * Entity pages served by plain Symfony routes carry no entity object; for
 * those only the route name and the numeric "id" parameter are exposed and
 * the entity is resolved server-side by the authenticated endpoint (see
 * EntityResourceKeyResolver).
 */
class AdminBarExtension extends AbstractExtension
{
    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var bool
     */
    private $enabled;

    /**
     * Custom entity map: request attribute name => ['resource_key' => ..., 'security_context' => ..., 'routes' => ...].
     *
     * @var array<string, array{resource_key: string, security_context: ?string, routes?: string[]}>
     */
    private $entities;

    /**
     * Toolbar link labels: ['edit' => ..., 'add' => ..., 'logout' => ...].
     *
     * @var array<string, string>
     */
    private $labels;

    /**
     * @param array<string, array{resource_key: string, security_context: ?string, routes?: string[]}> $entities
     * @param array<string, string> $labels
     */
    public function __construct(RequestStack $requestStack, bool $enabled = true, array $entities = [], array $labels = [])
    {
        $this->requestStack = $requestStack;
        $this->enabled = $enabled;
        $this->entities = $entities;
        $this->labels = $labels;
    }

    /**
     * @return TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'sulu_admin_bar',
                [$this, 'renderAdminBar'],
                ['is_safe' => ['html'], 'needs_environment' => true]
            ),
        ];
    }

    public function renderAdminBar(Environment $twig): string
    {
        $request = $this->requestStack->getCurrentRequest();

        if (!$this->enabled
            || null === $request
            // Never show the bar inside the admin preview iframe. Sulu's
            // PreviewRenderer sets both the "preview" and the "partial"
            // attribute; checking both avoids false positives with route
            // defaults providers that expose their own "preview" default
            // on regular website requests.
            || ($request->attributes->getBoolean('preview') && $request->attributes->has('partial'))
        ) {
            return '';
        }

        return $twig->render('@AdminBar/admin_bar.html.twig', [
            'context' => $this->resolveContext($request),
            'labels' => $this->labels,
        ]);
    }

    /**
     * @return array{webspace: ?string, locale: ?string, uuid: ?string, id: ?string, resourceKey: ?string, route: ?string}
     */
    private function resolveContext(Request $request): array
    {
        $context = [
            'webspace' => null,
            'locale' => $request->getLocale(),
            'uuid' => null,
            'id' => null,
            'resourceKey' => null,
            'route' => null,
        ];

        $suluAttributes = $request->attributes->get('_sulu');
        if ($suluAttributes instanceof RequestAttributes) {
            $webspace = $suluAttributes->getAttribute('webspace');
            if ($webspace instanceof Webspace) {
                $context['webspace'] = $webspace->getKey();
            }

            $localization = $suluAttributes->getAttribute('localization');
            if ($localization instanceof Localization) {
                $context['locale'] = $localization->getLocale();
            }
        }

        // Sulu 3: content entities are passed as "object" request attribute.
        $object = $request->attributes->get('object');
        if ($object instanceof \Sulu\Content\Domain\Model\DimensionContentInterface) {
            $context['resourceKey'] = $object::getResourceKey();
            $context['locale'] = $object->getLocale() ?? $context['locale'];

            $resource = $object->getResource();
            if ($resource instanceof \Sulu\Page\Domain\Model\PageInterface) {
                $context['uuid'] = $resource->getUuid();
            } elseif (null !== ($id = $this->resolveEntityId($resource))) {
                // Articles (and any other content entity): expose the id
                // (a UUID for articles) so the authenticated endpoint can
                // resolve the admin edit view from the view registry.
                $context['id'] = $id;
            }

            return $context;
        }

        // Sulu 2 with SuluArticleBundle: articles are rendered with the
        // PHPCR ArticleDocument as "object" request attribute.
        if ($object instanceof \Sulu\Bundle\ArticleBundle\Document\ArticleDocument) {
            $context['resourceKey'] = 'articles';
            $context['id'] = $object->getUuid();
            $context['locale'] = $object->getLocale() ?: $context['locale'];

            return $context;
        }

        // Sulu 2: pages are rendered with a "structure" request attribute.
        $structure = $request->attributes->get('structure');
        if ($structure instanceof \Sulu\Component\Content\Compat\Structure\PageBridge) {
            $context['resourceKey'] = 'pages';
            $context['uuid'] = $structure->getUuid();
            $context['locale'] = $structure->getLanguageCode() ?: $context['locale'];

            return $context;
        }

        // Custom entities routed through a route defaults provider: the
        // entity is exposed as a request attribute. Explicitly configured
        // attributes ("admin_bar.entities") win over auto-detection.
        foreach ($this->entities as $attribute => $entityConfig) {
            $entity = $request->attributes->get($attribute);
            if (null !== ($id = $this->resolveEntityId($entity))) {
                $context['resourceKey'] = $entityConfig['resource_key'];
                $context['id'] = $id;

                return $context;
            }
        }

        // Auto-detection: any request attribute holding an object that
        // follows the Sulu entity convention (RESOURCE_KEY constant and
        // getId()) is linkable without configuration. Attribute order is
        // the route defaults order, so the primary entity of a route
        // defaults provider is found first.
        foreach ($request->attributes->all() as $name => $value) {
            if ('' === $name || '_' === $name[0] || !\is_object($value)) {
                continue;
            }

            if (\defined(\get_class($value) . '::RESOURCE_KEY')
                && null !== ($id = $this->resolveEntityId($value))
            ) {
                $context['resourceKey'] = (string) \constant(\get_class($value) . '::RESOURCE_KEY');
                $context['id'] = $id;

                return $context;
            }
        }

        // Custom entities rendered by plain Symfony routes with a numeric
        // "id" parameter. Explicitly configured route names map directly to
        // their resource key; any other route is passed along (still
        // visitor-independent, so still cache-safe) and matched against the
        // Doctrine entities by the authenticated endpoint itself.
        $route = (string) $request->attributes->get('_route', '');
        $id = $request->attributes->get('id');
        if ('' !== $route && (\is_int($id) || (\is_string($id) && \ctype_digit($id)))) {
            $context['id'] = (string) $id;

            foreach ($this->entities as $entityConfig) {
                if (\in_array($route, $entityConfig['routes'] ?? [], true)) {
                    $context['resourceKey'] = $entityConfig['resource_key'];

                    return $context;
                }
            }

            $context['route'] = $route;
        }

        return $context;
    }

    /**
     * Returns the scalar id of an entity-like object, null otherwise.
     *
     * @param mixed $entity
     */
    private function resolveEntityId($entity): ?string
    {
        if (!\is_object($entity) || !\method_exists($entity, 'getId')) {
            return null;
        }

        $id = $entity->getId();

        return \is_scalar($id) && '' !== (string) $id ? (string) $id : null;
    }
}
