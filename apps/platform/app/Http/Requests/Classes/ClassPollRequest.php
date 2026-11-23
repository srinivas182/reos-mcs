<?php

declare(strict_types=1);

namespace App\Http\Requests\Classes;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** Launch a poll in a live class (A10.2b, instructor i12). */
final class ClassPollRequest extends FormRequest
{
    /** @return array<string, array<int, ValidationRule|string>> */
    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'min:3', 'max:200'],
            /** Two to five answers. */
            'options' => ['required', 'array', 'min:2', 'max:5'],
            'options.*' => ['required', 'string', 'min:1', 'max:80'],
        ];
    }

    /** @return list<string> */
    public function options(): array
    {
        $options = [];

        foreach ($this->array('options') as $option) {
            if (is_string($option) && trim($option) !== '') {
                $options[] = trim($option);
            }
        }

        return $options;
    }
}
