<?php

declare(strict_types=1);

namespace App\Sso;

/**
 * Draft: saved, not yet proven. Tested: a test sign-in passed with these
 * settings. On: members see the button. Any change to the settings goes
 * back to Draft, so what members use is always what was tested.
 */
enum SsoStatus: string
{
    case Draft = 'draft';
    case Tested = 'tested';
    case On = 'on';
}
