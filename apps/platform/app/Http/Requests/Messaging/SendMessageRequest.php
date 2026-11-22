<?php

declare(strict_types=1);

namespace App\Http\Requests\Messaging;

use App\Models\Message;
use Illuminate\Foundation\Http\FormRequest;

/** Send or edit a message (B6.1), optionally as a reply. */
final class SendMessageRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:'.Message::MAX_LENGTH],
            'reply_to' => ['nullable', 'uuid'],
        ];
    }

    public function replyTo(): ?string
    {
        $reply = $this->input('reply_to');

        return is_string($reply) && $reply !== '' ? $reply : null;
    }
}
