<?php

declare(strict_types=1);

namespace App\Http\Requests\Messaging;

use App\Models\Message;
use App\Models\MessageAttachment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/** Send or edit a message (B6.1), optionally as a reply. */
final class SendMessageRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'body' => ['nullable', 'required_without:files', 'string', 'max:'.Message::MAX_LENGTH],
            // Images and PDFs, scanned before anyone opens them (B6.1b).
            'files' => ['nullable', 'array', 'max:'.MessageAttachment::MAX_FILES],
            'files.*' => ['file', 'mimes:'.implode(',', MessageAttachment::MIMES), 'max:'.MessageAttachment::MAX_KB],
            'reply_to' => ['nullable', 'uuid'],
        ];
    }

    public function replyTo(): ?string
    {
        $reply = $this->input('reply_to');

        return is_string($reply) && $reply !== '' ? $reply : null;
    }

    /** @return list<UploadedFile> */
    public function attachments(): array
    {
        $files = $this->file('files');

        return is_array($files) ? array_values($files) : [];
    }
}
