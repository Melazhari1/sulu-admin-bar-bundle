<?php

declare(strict_types=1);

/*
 * This file is part of the AdminBarBundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Elazhari\SuluAdminBarBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('admin_bar');

        $treeBuilder->getRootNode()
            ->children()
                ->booleanNode('enabled')
                    ->info('Whether the admin bar loader is rendered on the website at all.')
                    ->defaultTrue()
                ->end()
                ->scalarNode('admin_route_prefix')
                    ->info('URL prefix the Sulu admin lives under, e.g. "/admin". The admin bar endpoint is registered below it so the request runs through the admin firewall. Defaults to auto-detection from the "admin" firewall pattern of the security configuration, with "/admin" as fallback.')
                    ->defaultNull()
                    ->beforeNormalization()
                        ->ifString()
                        ->then(static function (string $value): string {
                            return \rtrim($value, '/');
                        })
                    ->end()
                    ->validate()
                        ->ifTrue(static function ($value): bool {
                            return null !== $value && (!\is_string($value) || '' === $value || '/' !== $value[0]);
                        })
                        ->thenInvalid('admin_route_prefix must be a path starting with "/" (e.g. "/admin"), got %s.')
                    ->end()
                ->end()
                ->arrayNode('labels')
                    ->info('Texts of the toolbar links. Override them to localize the bar.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('edit')->cannotBeEmpty()->defaultValue('Edit')->end()
                        ->scalarNode('add')->cannotBeEmpty()->defaultValue('Add new')->end()
                        ->scalarNode('logout')->cannotBeEmpty()->defaultValue('Logout')->end()
                    ->end()
                ->end()
                ->arrayNode('entities')
                    ->info('Optional extra configuration for custom entities, keyed by the request attribute that holds the entity on the website. Entities are detected automatically (request attributes with a RESOURCE_KEY constant, or the route name / URL path matched against the Doctrine entities); explicit entries are only needed for a stricter permission gate or when the automatic detection does not match.')
                    ->useAttributeAsKey('attribute')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('resource_key')
                                ->info('Sulu resource key of the entity (e.g. "formations").')
                                ->isRequired()
                                ->cannotBeEmpty()
                            ->end()
                            ->scalarNode('security_context')
                                ->info('Optional Sulu security context checked additionally for the edit/add links (e.g. "sulu.formations.formation"). Without it, a link is shown whenever the Sulu admin exposes a matching view to the current user.')
                                ->defaultNull()
                            ->end()
                            ->arrayNode('routes')
                                ->info('Symfony route names that render this entity with a numeric "id" route parameter. Only needed when the automatic detection (route name / URL path matched against the entity resource keys) does not find the entity.')
                                ->scalarPrototype()->end()
                                ->defaultValue([])
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
