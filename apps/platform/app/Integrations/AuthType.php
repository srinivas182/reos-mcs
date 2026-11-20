<?php

declare(strict_types=1);

namespace App\Integrations;

/** How REOS signs in to a vendor (from the register's `connection.auth`). */
enum AuthType: string
{
    case ApiKey = 'api_key';
    case OAuth2 = 'oauth2';
    case Aws = 'aws';
    /** Public data: no keys and no test system. */
    case None = 'none';
}
