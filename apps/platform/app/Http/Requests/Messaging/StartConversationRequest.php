<?php

declare(strict_types=1);

namespace App\Http\Requests\Messaging;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageAttachment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/** New message (B6.1): who to (people the sender may message), an optional group name and the first message. */
final class StartConversationRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'people' => ['required', 'array', 'min:1', 'max:'.(Conversation::MAX_PEOPLE - 1)],
            'people.*' => ['integer', 'distinct'],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['nullable', 'required_without:files', 'string', 'max:'.Message::MAX_LENGTH],
            // Images and PDFs, scanned before anyone opens them (B6.1b).
            'files' => ['nullable', 'array', 'max:'.MessageAttachment::MAX_FILES],
            'files.*' => ['file', 'mimes:'.implode(',', MessageAttachment::MIMES), 'max:'.MessageAttachment::MAX_KB],
        ];
    }

    /** @return list<int> */
    public function people(): array
    {
        $people = $this->input('people');

        return is_array($people) ? array_values(array_map(intval(...), array_filter($people, is_numeric(...)))) : [];
    }

    /** @return list<UploadedFile> */
    public function attachments(): array
    {
        $files = $this->file('files');

        return is_array($files) ? array_values($files) : [];
    }
}
