<?php

declare(strict_types=1);

namespace App\Integrations;

/** Input type and validation for a connection field. */
enum FieldKind: string
{
    case Text = 'text';
    case Url = 'url';
    case Email = 'email';
    case Phone = 'phone';
    case Multiline = 'multiline';
}
