<?php

declare(strict_types=1);

namespace App\Licences\Journey;

use App\Authorization\Role;
use App\Enums\OrganisationType;
use App\Models\LicenceJourney;
use App\Models\PassAnnouncement;
use App\Models\PassCongrats;
use App\Models\State;
use App\Models\StudyGroup;
use App\Models\User;
use App\Notifications\PassNews;
use App\Practice\Readiness;
use App\Practice\StudyPlans;
use App\Services\Authorization\Memberships;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Passing news (A10.3b, ADR 0041; candidate f05 and f06). After passing,
 * the candidate chooses who hears: their study group and school (told at
 * once) and candidates in the same state (in Passes). Only a first name and
 * city, and an optional tip: never scores. Classmates send a short congrats,
 * once each. Everyone can mute these in notification settings.
 *
 * @phpstan-type PassRow array{id: int, first_name: string, city: string|null, state: string, tip: string|null, on: string, congrats: int, sent: bool, mine: bool}
 */
final readonly class PassAnnouncements
{
    public function __construct(private LicenceJourneys $journeys, private Memberships $memberships, private Readiness $readiness) {}

    /**
     * @param  array{to_group: bool, to_school: bool, to_state: bool, city: string|null, tip: string|null}  $choice
     */
    public function share(User $user, array $choice): PassAnnouncement
    {
        $journey = $this->passed($user);

        if (! $choice['to_group'] && ! $choice['to_school'] && ! $choice['to_state']) {
            throw ValidationException::withMessages(['to_state' => __('licence.news.errors.nobody')]);
        }

        $pass = DB::transaction(function () use ($user, $journey, $choice): PassAnnouncement {
            $journey->forceFill(['news' => 'shared'])->save();

            return PassAnnouncement::query()->updateOrCreate(['user_id' => $user->id], [
                'state_code' => $journey->state_code,
                'first_name' => mb_substr(self::firstName((string) $user->name), 0, 60),
                'city' => $choice['city'],
                'tip' => $choice['tip'],
                'to_group' => $choice['to_group'],
                'to_school' => $choice['to_school'],
                'to_state' => $choice['to_state'],
            ]);
        });

        $people = collect();

        if ($choice['to_group']) {
            $people = $people->merge($this->classmates($user));
        }

        if ($choice['to_school']) {
            foreach ($this->memberships->of($user) as $membership) {
                if ($membership->organisation->type === OrganisationType::School) {
                    $people = $people->merge($this->memberships->holdersIn(Role::SchoolAdmin, $membership->organisation))
                        ->merge($this->memberships->holdersIn(Role::Instructor, $membership->organisation));
                }
            }
        }

        Notification::send($people->unique('id')->reject(static fn (User $u): bool => $u->id === $user->id)->values(), new PassNews($pass, PassNews::PASSED));

        return $pass;
    }

    /** "Not now": nothing is shared. */
    public function skip(User $user): void
    {
        $this->passed($user)->forceFill(['news' => 'skipped'])->save();
    }

    /**
     * Passes the viewer may see (f06): candidates in their state who shared
     * with the state, and classmates who shared with their group.
     *
     * @return array{week: int, state: string, readiness: int|null, passes: list<PassRow>}
     */
    public function feed(User $viewer): array
    {
        $journey = $this->journeys->journey($viewer);
        $state = $journey->state_code;
        $classmates = $this->classmates($viewer)->pluck('id')->all();
        $sent = PassCongrats::query()->where('user_id', $viewer->id)->pluck('pass_announcement_id')->all();
        $stateName = State::query()->where('code', $state)->first()->name ?? $state;

        $passes = PassAnnouncement::query()
            ->where(fn ($q) => $q->where(fn ($s) => $s->where('state_code', $state)->where('to_state', true))
                ->orWhere(fn ($g) => $g->where('to_group', true)->whereIn('user_id', $classmates))
                ->orWhere('user_id', $viewer->id))
            ->latest('id')->limit(30)->get();

        $rows = [];

        foreach ($passes as $pass) {
            $rows[] = [
                'id' => $pass->id, 'first_name' => $pass->first_name, 'city' => $pass->city, 'state' => $stateName, 'tip' => $pass->tip,
                'on' => ($pass->created_at ?? now())->toIso8601String(), 'congrats' => $pass->congrats_count,
                'sent' => in_array($pass->id, $sent, true), 'mine' => $pass->user_id === $viewer->id,
            ];
        }

        return [
            // Everyone who passed in the state this week, shared or not: a count, no names.
            'week' => LicenceJourney::query()->where('state_code', $state)->where('passed_on', '>=', StudyPlans::today()->subDays(6)->toDateString())->count(),
            'state' => $stateName,
            'readiness' => $this->readiness->of($viewer)['percent'],
            'passes' => $rows,
        ];
    }

    /** A short congrats, once per pass; the passer is told. */
    public function congratulate(User $viewer, PassAnnouncement $pass, string $message): void
    {
        $visible = collect($this->feed($viewer)['passes'])->firstWhere('id', $pass->id);

        if ($visible === null || $pass->user_id === $viewer->id) {
            throw new NotFoundHttpException;
        }

        $created = DB::transaction(function () use ($viewer, $pass, $message): bool {
            if (PassCongrats::query()->where('pass_announcement_id', $pass->id)->where('user_id', $viewer->id)->exists()) {
                return false;
            }

            PassCongrats::query()->create(['pass_announcement_id' => $pass->id, 'user_id' => $viewer->id, 'message' => $message]);
            $pass->increment('congrats_count');

            return true;
        });

        if (! $created) {
            throw ValidationException::withMessages(['message' => __('licence.news.errors.sent')]);
        }

        $pass->user->notify(new PassNews($pass, PassNews::CONGRATS, self::firstName((string) $viewer->name), $message));
    }

    /** @return Collection<int, User> Everyone in the person's study groups but them. */
    private function classmates(User $user): Collection
    {
        $groups = StudyGroup::query()->whereHas('members', fn ($q) => $q->where('users.id', $user->id))->with('members')->get();

        return $groups->flatMap(static fn (StudyGroup $g) => $g->members)->unique('id')->reject(static fn (User $u): bool => $u->id === $user->id)->values()->toBase();
    }

    private function passed(User $user): LicenceJourney
    {
        $journey = $this->journeys->journey($user);

        if ($journey->passed_on === null) {
            throw ValidationException::withMessages(['news' => __('licence.news.errors.notPassed')]);
        }

        return $journey;
    }

    public static function firstName(string $name): string
    {
        $first = explode(' ', trim($name))[0];

        return $first === '' ? $name : $first;
    }
}
