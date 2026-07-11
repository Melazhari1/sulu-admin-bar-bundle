<?php

declare(strict_types=1);

/*
 * This file is part of the AdminBarBundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Elazhari\SuluAdminBarBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class AdminBarExtension extends Extension implements PrependExtensionInterface
{
    /**
     * Admin URL prefix of a stock Sulu skeleton, used when nothing could
     * be detected from the security configuration.
     */
    private const DEFAULT_ADMIN_ROUTE_PREFIX = '/admin';

    /**
     * Detects the admin URL prefix from the project's security
     * configuration and prepends it as "admin_route_prefix" default.
     *
     * Prepended config has the lowest priority, so an explicit
     * "admin_bar.admin_route_prefix" in the project always wins.
     */
    public function prepend(ContainerBuilder $container): void
    {
        $detectedPrefix = $this->detectAdminRoutePrefix($container);

        if (null !== $detectedPrefix) {
            $container->prependExtensionConfig('admin_bar', [
                'admin_route_prefix' => $detectedPrefix,
            ]);
        }
    }

    /**
     * @param array<array<string, mixed>> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        /** @var array{enabled: bool, admin_route_prefix: ?string, labels: array{edit: string, add: string, logout: string}, entities: array<string, array{resource_key: string, security_context: string}>} $config */
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('admin_bar.enabled', $config['enabled']);
        $container->setParameter(
            'admin_bar.admin_route_prefix',
            $config['admin_route_prefix'] ?? self::DEFAULT_ADMIN_ROUTE_PREFIX
        );
        $container->setParameter('admin_bar.labels', $config['labels']);
        $container->setParameter('admin_bar.entities', $config['entities']);

        $loader = new YamlFileLoader($container, new FileLocator(\dirname(__DIR__, 2) . '/config'));
        $loader->load('services.yaml');
    }

    /**
     * Extracts the literal path prefix from the "admin" firewall pattern
     * of the project's security configuration, e.g. "/admin" from
     * "^/admin(\/|$)" or "/_private" from "^/_private". Returns null when
     * no such firewall (or no literal prefix) is found.
     */
    private function detectAdminRoutePrefix(ContainerBuilder $container): ?string
    {
        foreach ($container->getExtensionConfig('security') as $config) {
            $pattern = $config['firewalls']['admin']['pattern'] ?? null;

            if (!\is_string($pattern)) {
                continue;
            }

            if (1 === \preg_match('#^\^(/[A-Za-z0-9_.~-]+(?:/[A-Za-z0-9_.~-]+)*)#', $pattern, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }
}
