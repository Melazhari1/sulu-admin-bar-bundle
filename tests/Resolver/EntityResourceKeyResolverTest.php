<?php

declare(strict_types=1);

/*
 * This file is part of the AdminBarBundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Elazhari\SuluAdminBarBundle\Tests\Resolver;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\ClassMetadataFactory;
use Doctrine\Persistence\ObjectManager;
use Elazhari\SuluAdminBarBundle\Resolver\EntityResourceKeyResolver;
use Elazhari\SuluAdminBarBundle\Tests\Fixtures\Formation;
use Elazhari\SuluAdminBarBundle\Tests\Fixtures\FormationDomain;
use Elazhari\SuluAdminBarBundle\Tests\Fixtures\Testimony;
use PHPUnit\Framework\TestCase;

class EntityResourceKeyResolverTest extends TestCase
{
    private const ENTITIES = [Formation::class, FormationDomain::class, Testimony::class, \stdClass::class];

    /**
     * @dataProvider provideMatches
     */
    public function testResolvesTheResourceKey(?string $expected, string $routeName, string $path): void
    {
        self::assertSame($expected, $this->createResolver(self::ENTITIES)->resolve($routeName, $path));
    }

    /**
     * @return iterable<string, array{?string, string, string}>
     */
    public static function provideMatches(): iterable
    {
        yield 'singular route name' => ['formations', 'formation', ''];
        yield 'plural route name' => ['formations', 'formations', ''];
        yield 'route name part' => ['formations', 'app_formation_show', ''];
        yield 'longest part combination wins' => ['formation_domains', 'app_formation_domain_show', ''];
        yield 'separator tolerant route name' => ['formation_domains', 'formation-domain.detail', ''];
        yield 'class short name (irregular plural)' => ['testimonies', 'testimony', ''];
        yield 'path segment' => ['formations', '', '/formation/master-marketing/12/3'];
        yield 'separator tolerant path segment' => ['formation_domains', '', '/formation-domains/12'];
        yield 'route name wins over path' => ['testimonies', 'testimony', '/formation/12'];
        yield 'slugs are not searched inside' => [null, '', '/master-in-formation/12'];
        yield 'numeric segments never match' => [null, '', '/12/34'];
        yield 'unknown names' => [null, 'unknown_route', '/somewhere/12'];
        yield 'empty input' => [null, '', ''];
    }

    public function testReturnsNullWithoutDoctrine(): void
    {
        self::assertNull((new EntityResourceKeyResolver(null))->resolve('formation', '/formation/12'));
    }

    public function testReturnsNullWhenTheMetadataCannotBeLoaded(): void
    {
        $factory = $this->createMock(ClassMetadataFactory::class);
        $factory->method('getAllMetadata')->willThrowException(new \RuntimeException('broken mapping'));

        $manager = $this->createMock(ObjectManager::class);
        $manager->method('getMetadataFactory')->willReturn($factory);

        $doctrine = $this->createMock(ManagerRegistry::class);
        $doctrine->method('getManagers')->willReturn(['default' => $manager]);

        self::assertNull((new EntityResourceKeyResolver($doctrine))->resolve('formation', '/formation/12'));
    }

    public function testBuildsTheEntityIndexOnlyOnce(): void
    {
        $factory = $this->createMock(ClassMetadataFactory::class);
        $factory->expects(self::once())
            ->method('getAllMetadata')
            ->willReturn([$this->createClassMetadata(Formation::class)]);

        $manager = $this->createMock(ObjectManager::class);
        $manager->method('getMetadataFactory')->willReturn($factory);

        $doctrine = $this->createMock(ManagerRegistry::class);
        $doctrine->method('getManagers')->willReturn(['default' => $manager]);

        $resolver = new EntityResourceKeyResolver($doctrine);

        self::assertSame('formations', $resolver->resolve('formation', ''));
        self::assertSame('formations', $resolver->resolve('formations', ''));
    }

    /**
     * @param class-string[] $classes
     */
    private function createResolver(array $classes): EntityResourceKeyResolver
    {
        $metadata = [];
        foreach ($classes as $class) {
            $metadata[] = $this->createClassMetadata($class);
        }

        $factory = $this->createMock(ClassMetadataFactory::class);
        $factory->method('getAllMetadata')->willReturn($metadata);

        $manager = $this->createMock(ObjectManager::class);
        $manager->method('getMetadataFactory')->willReturn($factory);

        $doctrine = $this->createMock(ManagerRegistry::class);
        $doctrine->method('getManagers')->willReturn(['default' => $manager]);

        return new EntityResourceKeyResolver($doctrine);
    }

    /**
     * @param class-string $class
     */
    private function createClassMetadata(string $class): ClassMetadata
    {
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('getName')->willReturn($class);

        return $metadata;
    }
}
