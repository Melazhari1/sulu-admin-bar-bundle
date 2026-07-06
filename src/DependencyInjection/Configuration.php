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
                ->scalarNode('admin_base_path')
                    ->info('Base path of the Sulu admin (e.g. "/admin"). Defaults to auto-detection from the admin firewall pattern in the security configuration; set it explicitly when the security config is not visible to every kernel context (e.g. kernel specific security_admin.yaml files).')
                    ->defaultNull()
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
                    ->info('Optional extra configuration for custom entities, keyed by the request attribute that holds the entity on the website. Entities exposed as request attributes with a RESOURCE_KEY constant are detected automatically; explicit entries are only needed for route-name matching or a stricter permission gate.')
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
                                ->info('Symfony route names that also render this entity with a numeric "id" route parameter (for detail pages not served through the Sulu route system).')
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
