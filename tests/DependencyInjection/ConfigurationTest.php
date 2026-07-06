<?php

declare(strict_types=1);

/*
 * This file is part of the AdminBarBundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Elazhari\SuluAdminBarBundle\Tests\DependencyInjection;

use Elazhari\SuluAdminBarBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

class ConfigurationTest extends TestCase
{
    public function testDefaults(): void
    {
        $config = $this->process([]);

        self::assertSame([
            'enabled' => true,
            'admin_base_path' => null,
            'labels' => [
                'edit' => 'Edit',
                'add' => 'Add new',
                'logout' => 'Logout',
            ],
            'entities' => [],
        ], $config);
    }

    public function testAdminBasePathCanBeConfigured(): void
    {
        $config = $this->process(['admin_base_path' => '/_private']);

        self::assertSame('/_private', $config['admin_base_path']);
    }

    public function testEntityDefaults(): void
    {
        $config = $this->process([
            'entities' => [
                'formation' => ['resource_key' => 'formations'],
            ],
        ]);

        self::assertSame([
            'formation' => [
                'resource_key' => 'formations',
                'security_context' => null,
                'routes' => [],
            ],
        ], $config['entities']);
    }

    public function testFullEntityConfiguration(): void
    {
        $config = $this->process([
            'entities' => [
                'article' => [
                    'resource_key' => 'articles',
                    'security_context' => 'sulu.articles.article',
                    'routes' => ['article_detail', 'article_preview'],
                ],
            ],
        ]);

        self::assertSame([
            'article' => [
                'resource_key' => 'articles',
                'security_context' => 'sulu.articles.article',
                'routes' => ['article_detail', 'article_preview'],
            ],
        ], $config['entities']);
    }

    public function testLabelsCanBeOverridden(): void
    {
        $config = $this->process([
            'labels' => ['edit' => 'Modifier'],
        ]);

        self::assertSame([
            'edit' => 'Modifier',
            'add' => 'Add new',
            'logout' => 'Logout',
        ], $config['labels']);
    }

    public function testResourceKeyIsRequired(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process([
            'entities' => [
                'formation' => [],
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function process(array $config): array
    {
        return (new Processor())->processConfiguration(new Configuration(), [$config]);
    }
}
