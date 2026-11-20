<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Middleware\HandleAppearance;
use App\Http\Requests\Settings\AppearanceUpdateRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cookie;

/**
 * Saves a person's theme and text size to their account, so the choice follows
 * them to every device. The cookies let the very first paint use the right
 * theme before the account is loaded (no flash).
 */
final class AppearanceController extends Controller
{
    public function update(AppearanceUpdateRequest $request, #[CurrentUser] User $user): RedirectResponse
    {
        /** @var array{appearance?: string, text_size?: string} $preferences */
        $preferences = $request->validated();

        $user->fill($preferences)->save();

        Cookie::queue(HandleAppearance::APPEARANCE_COOKIE, $user->appearance->value, HandleAppearance::COOKIE_MINUTES);
        Cookie::queue(HandleAppearance::TEXT_SIZE_COOKIE, $user->text_size->value, HandleAppearance::COOKIE_MINUTES);

        return back();
    }
}
