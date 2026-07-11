<?php

declare(strict_types=1);

/*
 * This file is part of the AdminBarBundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Elazhari\SuluAdminBarBundle\Resolver;

use Doctrine\Persistence\ManagerRegistry;

/**
 * Derives the Sulu resource key of entity pages that are served by plain
 * Symfony routes (no entity object in the request attributes) from the
 * Doctrine entity map — no per-entity configuration required.
 *
 * All managed entities following the Sulu convention (a RESOURCE_KEY class
 * constant) are indexed by normalized name "stems" built from the resource
 * key and the class short name. The current route name (including every
 * contiguous combination of its "_"/"."/"-" separated parts) and the URL
 * path segments are matched against that index; singular/plural and
 * separator differences are tolerated ("formation" matches "formations",
 * "formation-domain" matches "formation_domains").
 *
 * The resolver only runs on the authenticated endpoint, so building the
 * index never costs anonymous website traffic anything. The result is only
 * ever used to look up admin views in Sulu's view registry, which is
 * permission-filtered per user — a wrong guess can therefore never expose
 * anything the user is not allowed to see.
 */
class EntityResourceKeyResolver
{
    /**
     * @var ManagerRegistry|null
     */
    private $doctrine;

    /**
     * Lazily built index: normalized name stem => resource key.
     *
     * @var array<string, string>|null
     */
    private $stemMap;

    public function __construct(?ManagerRegistry $doctrine = null)
    {
        $this->doctrine = $doctrine;
    }

    /**
     * Returns the resource key of the entity the given route/path most
     * likely renders, or null when nothing matches.
     */
    public function resolve(string $routeName, string $path): ?string
    {
        $map = $this->getStemMap();

        if ([] === $map) {
            return null;
        }

        foreach ($this->collectCandidates($routeName, $path) as $candidate) {
            $stem = self::stem($candidate);

            if ('' !== $stem && isset($map[$stem])) {
                return $map[$stem];
            }
        }

        return null;
    }

    /**
     * Candidate names to match, most specific first: the route name and all
     * contiguous combinations of its parts (longest first, so a route like
     * "app_formation_domain_show" matches "formation_domains" before
     * "formations"), then the URL path segments from left to right.
     *
     * Path segments are only matched as a whole — slugs like
     * "master-in-formation" must not match the "formations" entity.
     *
     * @return string[]
     */
    private function collectCandidates(string $routeName, string $path): array
    {
        $candidates = [];

        $parts = \preg_split('/[._\-]+/', $routeName, -1, \PREG_SPLIT_NO_EMPTY) ?: [];
        $count = \count($parts);

        for ($length = $count; $length >= 1; --$length) {
            for ($start = 0; $start + $length <= $count; ++$start) {
                $candidates[] = \implode('', \array_slice($parts, $start, $length));
            }
        }

        foreach (\explode('/', \trim($path, '/')) as $segment) {
            if ('' !== $segment) {
                $candidates[] = $segment;
            }
        }

        return $candidates;
    }

    /**
     * @return array<string, string>
     */
    private function getStemMap(): array
    {
        if (null !== $this->stemMap) {
            return $this->stemMap;
        }

        $map = [];

        if (null !== $this->doctrine) {
            try {
                foreach ($this->doctrine->getManagers() as $manager) {
                    foreach ($manager->getMetadataFactory()->getAllMetadata() as $metadata) {
                        $class = $metadata->getName();

                        if (!\defined($class . '::RESOURCE_KEY')) {
                            continue;
                        }

                        $resourceKey = \constant($class . '::RESOURCE_KEY');

                        if (!\is_string($resourceKey) || '' === $resourceKey) {
                            continue;
                        }

                        $shortName = false !== ($pos = \strrpos($class, '\\'))
                            ? \substr($class, $pos + 1)
                            : $class;

                        foreach ([$resourceKey, $shortName] as $name) {
                            $stem = self::stem($name);

                            if ('' !== $stem && !isset($map[$stem])) {
                                $map[$stem] = $resourceKey;
                            }
                        }
                    }
                }
            } catch (\Throwable $exception) {
                // The admin bar is a convenience feature: broken entity
                // metadata must never take the endpoint down, it only
                // disables the route based detection.
                $map = [];
            }
        }

        return $this->stemMap = $map;
    }

    /**
     * Normalizes a name for comparison: case, "_"/"-"/"." separators and a
     * trailing plural "s" are ignored. Purely numeric values (e.g. id path
     * segments) never produce a stem.
     */
    private static function stem(string $value): string
    {
        $stem = \strtolower((string) \preg_replace('/[^a-zA-Z0-9]+/', '', $value));

        if ('' === $stem || \ctype_digit($stem)) {
            return '';
        }

        return 's' === \substr($stem, -1) ? \substr($stem, 0, -1) : $stem;
    }
}
