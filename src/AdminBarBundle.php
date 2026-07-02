<?php

declare(strict_types=1);

/*
 * This file is part of the AdminBarBundle.
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Elazhari\SuluAdminBarBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Frontend admin bar for Sulu.
 *
 * Displays a fixed toolbar on top of the website for users that are
 * authenticated in the Sulu admin, with quick links to edit the current
 * content, create new content and log out.
 *
 * Uses the classic Bundle + DependencyInjection extension pattern (instead
 * of AbstractBundle) to stay compatible with Symfony 5.4 / Sulu 2.x.
 */
class AdminBarBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
