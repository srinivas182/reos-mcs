<?php

declare(strict_types=1);

namespace App\Http\Requests\Messaging;

use Illuminate\Foundation\Http\FormRequest;

/** New announcement (B6.1b): title, words, and an optional audience (roles, teams, states). */
final class AnnouncementRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:4000'],
            'roles' => ['present', 'array'],
            'roles.*' => ['string', 'max:40'],
            'teams' => ['present', 'array'],
            'teams.*' => ['integer'],
            'states' => ['present', 'array'],
            'states.*' => ['string', 'size:2'],
        ];
    }

    /** @return array{roles: list<string>, teams: list<int>, states: list<string>} */
    public function audience(): array
    {
        $strings = static fn (mixed $v): array => is_array($v) ? array_values(array_filter($v, is_string(...))) : [];
        $teams = $this->input('teams');

        return [
            'roles' => $strings($this->input('roles')),
            'teams' => is_array($teams) ? array_values(array_map(intval(...), array_filter($teams, is_numeric(...)))) : [],
            'states' => $strings($this->input('states')),
        ];
    }
}
