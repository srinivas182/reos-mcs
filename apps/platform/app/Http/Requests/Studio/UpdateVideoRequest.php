<?php

declare(strict_types=1);

namespace App\Http\Requests\Studio;

use App\Studio\Video\Music;
use App\Studio\Video\StudioVideos;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Edit a video (B12.2; screen 168): hook, scenes (order, trim, length, caption), music, brand; fix; render. */
final class UpdateVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'nullable', 'string', 'max:80'],
            'music' => ['sometimes', Rule::in(Music::TRACKS)],
            'brand' => ['sometimes', 'boolean'],
            'scenes' => ['sometimes', 'array', 'min:1', 'max:'.StudioVideos::MAX_SCENES],
            'scenes.*.media' => ['required', 'string', 'max:2048'],
            'scenes.*.start' => ['nullable', 'numeric', 'min:0', 'max:600'],
            'scenes.*.seconds' => ['required', 'numeric', 'min:'.StudioVideos::MIN_SCENE, 'max:'.StudioVideos::MAX_SCENE],
            'scenes.*.caption' => ['nullable', 'string', 'max:90'],
            'fix' => ['sometimes', 'boolean'],
            'render' => ['sometimes', 'boolean'],
        ];
    }
}
