<?php

declare(strict_types=1);

namespace App\Http\Requests\Brokerage;

use App\Recruiting\SponsoredHelp;
use Illuminate\Foundation\Http\FormRequest;

/** A candidate's sponsored AI cap (A17.2). */
final class SponsorCapRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['cap' => ['required', 'integer', 'min:0', 'max:'.SponsoredHelp::MAX_CAP]];
    }
}
