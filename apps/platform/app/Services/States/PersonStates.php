<?php

declare(strict_types=1);

namespace App\Services\States;

use App\Enums\LicenceStatus;
use App\Enums\StateStatus;
use App\Models\Licence;
use App\Models\State;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * The states a person works in, and the one in use now (A5.1).
 *
 * - A person's states are those they hold a licence in (checked or
 *   approved) and the state they're studying for.
 * - Only live states are worked in; licences elsewhere are tracked for
 *   renewal only. With none, the platform's home state (Texas) is used.
 * - The state switcher (web top bar, phone Me) appears when there is more
 *   than one live state to choose from. Content, plans and the CE card
 *   follow the current state.
 */
final readonly class PersonStates
{
    /**
     * Live states the person holds or studies for, in a stable order.
     *
     * @return list<string>
     */
    public function codes(User $user): array
    {
        $counting = array_map(static fn (LicenceStatus $status): string => $status->value, array_filter(LicenceStatus::cases(), static fn (LicenceStatus $status): bool => $status->counts()));
        $licensed = Licence::query()->where('user_id', $user->id)->whereIn('status', $counting)->pluck('state_code')->all();
        $wanted = array_values(array_unique(array_filter([...$licensed, $user->target_state], static fn (mixed $code): bool => is_string($code))));

        /** @var list<string> $live */
        $live = State::query()->whereIn('code', $wanted)->where('status', StateStatus::Live)->orderBy('name')->pluck('code')->all();

        return $live === [] ? [self::home()] : $live;
    }

    /** The state in use now. */
    public function current(User $user): string
    {
        $codes = $this->codes($user);

        return $user->current_state !== null && in_array($user->current_state, $codes, true) ? $user->current_state : $codes[0];
    }

    /** @throws ValidationException when the person doesn't work in that state */
    public function choose(User $user, string $code): void
    {
        $code = strtoupper($code);

        if (! in_array($code, $this->codes($user), true)) {
            throw ValidationException::withMessages(['state' => __('licences.errors.notYours')]);
        }

        $user->forceFill(['current_state' => $code])->save();
    }

    /** After a licence is removed or rejected, stop using its state. */
    public function forget(User $user, string $code): void
    {
        if ($user->current_state === $code && ! in_array($code, $this->codes($user), true)) {
            $user->forceFill(['current_state' => null])->save();
        }
    }

    /**
     * For the switcher: each live state, with the current one marked.
     *
     * @return array{current: array{code: string, name: string}, options: list<array{code: string, name: string}>}
     */
    public function switcher(User $user): array
    {
        $codes = $this->codes($user);
        $current = $this->current($user);
        $names = State::query()->whereIn('code', $codes)->pluck('name', 'code')->all();
        $name = static fn (string $code): string => is_string($names[$code] ?? null) ? $names[$code] : $code;

        return [
            'current' => ['code' => $current, 'name' => $name($current)],
            'options' => array_map(static fn (string $code): array => ['code' => $code, 'name' => $name($code)], $codes),
        ];
    }

    public static function home(): string
    {
        return strtoupper(config()->string('reos.region.code', 'TX'));
    }
}
