<?php

declare(strict_types=1);

namespace App\Messaging;

/** Sends a text message (SMS). Numbers are E.164 (+18325550123). */
interface TextMessages
{
    public function send(string $to, string $body): void;
}
