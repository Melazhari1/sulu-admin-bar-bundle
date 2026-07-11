<?php

declare(strict_types=1);

/*
 * This file is part of the AdminBarBundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Elazhari\SuluAdminBarBundle\Tests\Fixtures;

/**
 * Multi-word entity fixture: its resource key can only be matched by
 * combining several route name parts ("formation" + "domain").
 */
class FormationDomain
{
    public const RESOURCE_KEY = 'formation_domains';

    /**
     * @var int|null
     */
    private $id;

    public function __construct(?int $id = null)
    {
        $this->id = $id;
    }

    public function getId(): ?int
    {
        return $this->id;
    }
}
