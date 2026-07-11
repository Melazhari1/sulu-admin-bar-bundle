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
 * Entity fixture with an irregular plural: "testimony" can only be related
 * to the "testimonies" resource key via the class short name.
 */
class Testimony
{
    public const RESOURCE_KEY = 'testimonies';

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
