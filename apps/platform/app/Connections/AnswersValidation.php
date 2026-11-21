<?php

declare(strict_types=1);

namespace App\Connections;

use Illuminate\Http\Request;

/**
 * Drivers whose tool checks REOS's webhook address before sending to it
 * (Microsoft Graph's validation handshake): REOS echoes the token back.
 */
interface AnswersValidation
{
    /** The plain-text answer, or null when the request isn't a validation. */
    public function validationAnswer(Request $request): ?string;
}
