<?php

declare(strict_types=1);

/*
 * This file is part of the AdminBarBundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Elazhari\SuluAdminBarBundle\Tests\DependencyInjection;

use Elazhari\SuluAdminBarBundle\DependencyInjection\AdminBarExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class AdminBarExtensionTest extends TestCase
{
    public function testLoadRegistersParametersAndServices(): void
    {
        $container = new ContainerBuilder();

        (new AdminBarExtension())->load([
            [
                'entities' => [
                    'formation' => ['resource_key' => 'formations'],
                ],
            ],
        ], $container);

        self::assertTrue($container->getParameter('admin_bar.enabled'));
        self::assertSame([
            'edit' => 'Edit',
            'add' => 'Add new',
            'logout' => 'Logout',
        ], $container->getParameter('admin_bar.labels'));
        self::assertSame([
            'formation' => [
                'resource_key' => 'formations',
                'security_context' => null,
                'routes' => [],
            ],
        ], $container->getParameter('admin_bar.entities'));

        self::assertTrue($container->hasDefinition('admin_bar.twig_extension'));
        self::assertTrue($container->hasDefinition('admin_bar.controller'));
        self::assertTrue($container->hasDefinition('admin_bar.admin_session_cookie_listener'));
    }

    public function testCanBeDisabled(): void
    {
        $container = new ContainerBuilder();

        (new AdminBarExtension())->load([['enabled' => false]], $container);

        self::assertFalse($container->getParameter('admin_bar.enabled'));
    }

    public function testAdminBasePathDefaultsToSuluDefault(): void
    {
        $container = new ContainerBuilder();

        (new AdminBarExtension())->load([[]], $container);

        self::assertSame('/admin', $container->getParameter('admin_bar.admin_base_path'));
    }

    public function testAdminBasePathCanBeConfiguredExplicitly(): void
    {
        $container = new ContainerBuilder();

        (new AdminBarExtension())->load([['admin_base_path' => 'backend/']], $container);

        self::assertSame('/backend', $container->getParameter('admin_bar.admin_base_path'));
    }

    public function testAdminBasePathIsDetectedFromAdminFirewallPattern(): void
    {
        $container = new ContainerBuilder();
        $container->prependExtensionConfig('security', [
            'firewalls' => [
                'dev' => ['pattern' => '^/(_(profiler|wdt)|css|images|js)/', 'security' => false],
                'admin' => ['pattern' => '^/_private', 'provider' => 'sulu'],
            ],
        ]);

        (new AdminBarExtension())->load([[]], $container);

        self::assertSame('/_private', $container->getParameter('admin_bar.admin_base_path'));
    }

    public function testAdminBasePathIsDetectedFromSuluFirewallWithCustomName(): void
    {
        $container = new ContainerBuilder();
        $container->prependExtensionConfig('security', [
            'firewalls' => [
                'dev' => ['pattern' => '^/(_(profiler|wdt))/', 'security' => false],
                'backend' => [
                    'pattern' => '^\/cms(\/|$)',
                    'entry_point' => 'sulu_security.authentication_entry_point',
                ],
            ],
        ]);

        (new AdminBarExtension())->load([[]], $container);

        self::assertSame('/cms', $container->getParameter('admin_bar.admin_base_path'));
    }

    public function testExplicitAdminBasePathWinsOverDetection(): void
    {
        $container = new ContainerBuilder();
        $container->prependExtensionConfig('security', [
            'firewalls' => [
                'admin' => ['pattern' => '^/_private', 'provider' => 'sulu'],
            ],
        ]);

        (new AdminBarExtension())->load([['admin_base_path' => '/backend']], $container);

        self::assertSame('/backend', $container->getParameter('admin_bar.admin_base_path'));
    }

    public function testNonSuluFirewallsAreIgnored(): void
    {
        $container = new ContainerBuilder();
        $container->prependExtensionConfig('security', [
            'firewalls' => [
                'dev' => ['pattern' => '^/(_(profiler|wdt))/', 'security' => false],
                'main' => ['pattern' => '^/', 'form_login' => ['login_path' => 'app_login']],
            ],
        ]);

        (new AdminBarExtension())->load([[]], $container);

        self::assertSame('/admin', $container->getParameter('admin_bar.admin_base_path'));
    }
}
