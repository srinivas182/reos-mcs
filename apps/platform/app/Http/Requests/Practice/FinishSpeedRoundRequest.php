<?php

declare(strict_types=1);

namespace App\Http\Requests\Practice;

use App\Practice\SpeedRounds;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** The taps of a speed round (A9.2d): each statement answered, True or False. */
final class FinishSpeedRoundRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|string>> */
    public function rules(): array
    {
        return [
            'taps' => ['present', 'array', 'max:'.SpeedRounds::STATEMENTS],
            'taps.*.id' => ['required', 'integer', 'distinct', 'min:1'],
            'taps.*.true' => ['required', 'boolean'],
        ];
    }

    /** @return array<int, bool> */
    public function taps(): array
    {
        $taps = $this->validated('taps');
        $out = [];

        foreach (is_array($taps) ? $taps : [] as $tap) {
            if (is_array($tap) && is_numeric($tap['id'] ?? null)) {
                $out[(int) $tap['id']] = filter_var($tap['true'] ?? false, FILTER_VALIDATE_BOOLEAN);
            }
        }

        return $out;
    }
}
