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
 *  - Sulu 3: "object" request attribute (DimensionContentInterface)
 *  - Sulu 2: "structure" request attribute (PageBridge)
 * The instanceof checks against version specific classes are safe because
 * instanceof never triggers autoloading and simply yields false when the
 * class does not exist.
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
     * Custom entity map: request attribute name => ['resource_key' => ..., 'security_context' => ...].
     *
     * @var array<string, array{resource_key: string, security_context: string}>
     */
    private $entities;

    /**
     * Toolbar link labels: ['edit' => ..., 'add' => ..., 'logout' => ...].
     *
     * @var array<string, string>
     */
    private $labels;

    /**
     * @param array<string, array{resource_key: string, security_context: string}> $entities
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
     * @return array{webspace: ?string, locale: ?string, uuid: ?string, id: ?string, resourceKey: ?string}
     */
    private function resolveContext(Request $request): array
    {
        $context = [
            'webspace' => null,
            'locale' => $request->getLocale(),
            'uuid' => null,
            'id' => null,
            'resourceKey' => null,
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
            }

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

        // Custom entities rendered by plain Symfony routes: match the route
        // name and take the numeric "id" route parameter.
        $route = (string) $request->attributes->get('_route', '');
        $id = $request->attributes->get('id');
        if ('' !== $route && (\is_int($id) || (\is_string($id) && \ctype_digit($id)))) {
            foreach ($this->entities as $entityConfig) {
                if (\in_array($route, $entityConfig['routes'] ?? [], true)) {
                    $context['resourceKey'] = $entityConfig['resource_key'];
                    $context['id'] = (string) $id;

                    return $context;
                }
            }
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
