<?php

declare(strict_types=1);

namespace App\Sso;

/** The two kinds of identity provider a tenant can connect (ADR 0016). */
enum SsoProtocol: string
{
    case Saml = 'saml';
    case Oidc = 'oidc';
}
