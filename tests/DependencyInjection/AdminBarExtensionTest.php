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
        self::assertTrue($container->hasDefinition('admin_bar.entity_resource_key_resolver'));
    }

    public function testCanBeDisabled(): void
    {
        $container = new ContainerBuilder();

        (new AdminBarExtension())->load([['enabled' => false]], $container);

        self::assertFalse($container->getParameter('admin_bar.enabled'));
    }
}
