<?php

declare(strict_types=1);

namespace App\Http\Requests\Sessions;

use Illuminate\Foundation\Http\FormRequest;

/** Saving the shared whiteboard (B6.3b): the drawing and a picture of it. */
final class WhiteboardRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            /** The Excalidraw elements, as JSON text. */
            'elements' => ['required', 'string', 'json', 'max:2000000'],
            /** A PNG picture of the board, up to 5 MB. */
            'image' => ['nullable', 'file', 'mimes:png', 'mimetypes:image/png', 'max:5120', 'dimensions:max_width=8000,max_height=8000'],
        ];
    }

    /** @return list<mixed> */
    public function elements(): array
    {
        $elements = json_decode($this->string('elements')->toString(), true);

        return is_array($elements) ? array_values($elements) : [];
    }
}
