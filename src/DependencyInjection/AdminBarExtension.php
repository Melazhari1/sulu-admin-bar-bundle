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
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class AdminBarExtension extends Extension
{
    /**
     * Admin base path of a stock Sulu installation, used when neither the
     * configuration nor the security firewalls reveal the real one.
     */
    private const DEFAULT_ADMIN_BASE_PATH = '/admin';

    /**
     * @param array<array<string, mixed>> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        /** @var array{enabled: bool, admin_base_path: ?string, labels: array{edit: string, add: string, logout: string}, entities: array<string, array{resource_key: string, security_context: string}>} $config */
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('admin_bar.enabled', $config['enabled']);
        $container->setParameter('admin_bar.labels', $config['labels']);
        $container->setParameter('admin_bar.entities', $config['entities']);
        $container->setParameter(
            'admin_bar.admin_base_path',
            $this->resolveAdminBasePath($config['admin_base_path'], $container)
        );

        $loader = new YamlFileLoader($container, new FileLocator(\dirname(__DIR__, 2) . '/config'));
        $loader->load('services.yaml');
    }

    /**
     * Returns the base path the Sulu admin is served from ("/admin",
     * "/_private", ...), used as prefix of the admin bar endpoint route so
     * that it runs through the admin firewall.
     *
     * Priority: the explicit "admin_bar.admin_base_path" configuration,
     * then auto-detection from the admin firewall pattern in the project's
     * security configuration, then Sulu's default "/admin".
     */
    private function resolveAdminBasePath(?string $configuredPath, ContainerBuilder $container): string
    {
        if (null !== $configuredPath && '' !== \trim($configuredPath, '/ ')) {
            return '/' . \trim($configuredPath, '/ ');
        }

        return $this->detectAdminBasePath($container) ?? self::DEFAULT_ADMIN_BASE_PATH;
    }

    /**
     * Detects the admin base path from the raw "security" extension
     * configuration: Sulu projects guard the admin with a firewall whose
     * pattern starts with the admin prefix (e.g. "^/admin(\/|$)").
     *
     * The firewall named "admin" (Sulu skeleton convention) wins; otherwise
     * the first firewall wired to Sulu security services is used.
     */
    private function detectAdminBasePath(ContainerBuilder $container): ?string
    {
        $firewalls = [];
        foreach ($container->getExtensionConfig('security') as $securityConfig) {
            if (isset($securityConfig['firewalls']) && \is_array($securityConfig['firewalls'])) {
                // Later files win, like Symfony's own config merging.
                $firewalls = \array_merge($firewalls, $securityConfig['firewalls']);
            }
        }

        if (isset($firewalls['admin']) && \is_array($firewalls['admin'])) {
            $path = $this->extractPathPrefix($firewalls['admin']['pattern'] ?? null);

            if (null !== $path) {
                return $path;
            }
        }

        foreach ($firewalls as $firewall) {
            if (!\is_array($firewall) || !isset($firewall['pattern'])) {
                continue;
            }

            // Duck-type the Sulu admin firewall: it references Sulu
            // security services (provider, entry point, handlers).
            if (false === \strpos(\strtolower((string) \json_encode($firewall)), 'sulu')) {
                continue;
            }

            $path = $this->extractPathPrefix($firewall['pattern']);

            if (null !== $path) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Extracts the literal path prefix of a firewall pattern regex:
     * "^/admin(\/|$)" => "/admin", "^\/_private" => "/_private".
     *
     * @param mixed $pattern
     */
    private function extractPathPrefix($pattern): ?string
    {
        if (!\is_string($pattern)) {
            return null;
        }

        $pattern = \str_replace('\\/', '/', $pattern);

        if (1 !== \preg_match('#^\^(/[A-Za-z0-9_\-./]*[A-Za-z0-9_\-])#', $pattern, $matches)) {
            return null;
        }

        return $matches[1];
    }
}
