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
 * Entity fixture following the Sulu convention the auto-detection relies
 * on: a RESOURCE_KEY class constant and a scalar getId().
 */
class Formation
{
    public const RESOURCE_KEY = 'formations';

    /**
     * @var int|null
     */
    private $id;

    public function __construct(?int $id)
    {
        $this->id = $id;
    }

    public function getId(): ?int
    {
        return $this->id;
    }
}
