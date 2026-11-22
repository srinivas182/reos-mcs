<?php

declare(strict_types=1);

namespace App\Services\Privacy;

use App\Models\AuditEvent;
use App\Models\Connection;
use App\Models\User;
use App\Services\Authorization\Memberships;
use App\Tenancy\Tenancy;
use Illuminate\Support\Facades\DB;

/**
 * "Download my data" (TDPSA right of access and portability, A4.3b): what
 * REOS holds about one person, as JSON a person or another service can
 * read. Never secrets (password hashes, two-step secrets, tokens) and never
 * other people's data. docs/privacy/data-map.md lists where each part lives;
 * a new table holding personal data is added here and there.
 */
final readonly class DataExport
{
    public function __construct(private Tenancy $tenancy, private Memberships $memberships) {}

    /** @return array<string, mixed> */
    public function for(User $user): array
    {
        return $this->tenancy->asPlatform(fn (): array => [
            'exported_at' => now()->toIso8601String(),
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'phone' => $user->phone,
                'locale' => $user->locale,
                'target_state' => $user->target_state,
                'current_state' => $user->current_state,
                'created_at' => $user->created_at?->toIso8601String(),
            ],
            'roles' => array_map(static fn ($m): array => ['role' => $m->role->value, 'organisation' => $m->organisation->name], $this->memberships->of($user)),
            'sign_in' => [
                'password' => $user->password !== null,
                'two_step' => $user->two_factor_confirmed_at !== null,
                'linked_accounts' => DB::table('social_accounts')->where('user_id', $user->id)->get(['provider', 'email', 'created_at'])->map(static fn ($r): array => (array) $r)->all(),
                'single_sign_on' => DB::table('sso_identities')->join('organisations', 'organisations.id', '=', 'sso_identities.organisation_id')
                    ->where('sso_identities.user_id', $user->id)->get(['organisations.name as organisation', 'sso_identities.email', 'sso_identities.last_signed_in_at'])->map(static fn ($r): array => (array) $r)->all(),
            ],
            'devices' => DB::table('known_devices')->where('user_id', $user->id)->get(['name', 'platform', 'first_seen_at', 'last_seen_at'])->map(static fn ($r): array => (array) $r)->all(),
            'getting_started' => [
                'waitlists' => DB::table('state_waitlist')->where('user_id', $user->id)->get(['state_code', 'created_at'])->map(static fn ($r): array => (array) $r)->all(),
                'quiz_attempts' => DB::table('quiz_attempts')->where('user_id', $user->id)->get(['kind', 'state_code', 'correct', 'total', 'completed_at'])->map(static fn ($r): array => (array) $r)->all(),
            ],
            'connections' => Connection::query()->where('owner_type', Connection::OWNER_USER)->where('owner_id', $user->id)->get()
                ->map(static fn (Connection $c): array => [
                    'tool' => $c->integration_id,
                    'account' => $c->account_label,
                    'status' => $c->status->value,
                    'connected_at' => $c->created_at?->toIso8601String(),
                ])->all(),
            // State licences (A5.1).
            'licences' => DB::table('licences')->where('user_id', $user->id)->whereNull('deleted_at')->orderBy('id')
                ->get(['state_code', 'type', 'number', 'status', 'broker_role', 'holder_name', 'licensed_on', 'expires_on', 'sponsor_name', 'verified_by', 'verified_at', 'review_note', 'created_at'])
                ->map(static fn ($r): array => (array) $r)->all(),
            // CE completed (A5.2).
            'ce_completions' => DB::table('ce_completions')->where('user_id', $user->id)->whereNull('deleted_at')->orderBy('completed_on')
                ->get(['state_code', 'title', 'course_code', 'hours', 'provider', 'source', 'completed_on'])->map(static fn ($r): array => (array) $r)->all(),
            // Purchases, plans and what they unlock (B5.1). Card details are never held by REOS.
            'billing' => [
                'orders' => DB::table('orders')->join('products', 'products.id', '=', 'orders.product_id')->where('orders.user_id', $user->id)->orderBy('orders.id')
                    ->get(['products.name as product', 'orders.state_code', 'orders.postal_code', 'orders.amount_cents', 'orders.discount_cents', 'orders.tax_cents', 'orders.total_cents', 'orders.currency', 'orders.status', 'orders.gateway', 'orders.paid_at', 'orders.created_at'])
                    ->map(static fn ($r): array => (array) $r)->all(),
                'subscriptions' => DB::table('subscriptions')->join('products', 'products.id', '=', 'subscriptions.product_id')->where('subscriptions.user_id', $user->id)
                    ->get(['products.name as product', 'subscriptions.gateway', 'subscriptions.status', 'subscriptions.current_period_end', 'subscriptions.canceled_at'])
                    ->map(static fn ($r): array => (array) $r)->all(),
                'entitlements' => DB::table('entitlements')->where('user_id', $user->id)->orderBy('id')
                    ->get(['feature', 'source', 'state_code', 'starts_at', 'ends_at', 'revoked_at'])->map(static fn ($r): array => (array) $r)->all(),
                'sponsor_codes_used' => DB::table('sponsor_code_redemptions')->join('sponsor_codes', 'sponsor_codes.id', '=', 'sponsor_code_redemptions.sponsor_code_id')
                    ->where('sponsor_code_redemptions.user_id', $user->id)->get(['sponsor_codes.code', 'sponsor_code_redemptions.created_at'])->map(static fn ($r): array => (array) $r)->all(),
                'gateway_customers' => DB::table('gateway_customers')->where('user_id', $user->id)->get(['gateway', 'environment', 'customer_id'])->map(static fn ($r): array => (array) $r)->all(),
            ],
            // To-dos, typed or spoken (B4.3); what was said is never stored.
            'todos' => DB::table('todos')->where('user_id', $user->id)->whereNull('deleted_at')->orderBy('id')->limit(5000)
                ->get(['title', 'notes', 'due_at', 'due_date', 'remind_at', 'related_label', 'source', 'done_at', 'created_at'])->map(static fn ($r): array => (array) $r)->all(),
            'ai_credit_uses' => DB::table('ai_credit_uses')->where('user_id', $user->id)->orderBy('id')->limit(5000)
                ->get(['feature', 'engine', 'credits', 'created_at'])->map(static fn ($r): array => (array) $r)->all(),
            // The REOS calendar: events made in REOS and those synced from calendars (B4.1).
            'calendar' => [
                'events' => DB::table('calendar_events')->where('user_id', $user->id)->whereNull('deleted_at')->orderBy('starts_at')->limit(5000)
                    ->get(['source', 'kind', 'title', 'location', 'description', 'attendees', 'starts_at', 'ends_at', 'all_day'])->map(static fn ($r): array => (array) $r)->all(),
                'conflicts' => DB::table('calendar_conflicts')->where('user_id', $user->id)->orderBy('id')->limit(1000)
                    ->get(['kept', 'reason', 'losing', 'created_at', 'undone_at'])->map(static fn ($r): array => (array) $r)->all(),
            ],
            'tool_interests' => DB::table('integration_interests')->where('user_id', $user->id)->get(['integration_id', 'kind', 'created_at'])->map(static fn ($r): array => (array) $r)->all(),
            'tool_requests' => DB::table('tool_requests')->where('user_id', $user->id)->get(['name', 'note', 'created_at'])->map(static fn ($r): array => (array) $r)->all(),
            'privacy_requests' => DB::table('privacy_requests')->where('user_id', $user->id)->get(['kind', 'details', 'status', 'created_at', 'completed_at', 'response'])->map(static fn ($r): array => (array) $r)->all(),
            // What this person did in REOS, newest first (the audit log's own rows).
            'activity' => AuditEvent::query()->where('actor_id', $user->id)->latest('id')->limit(1000)
                ->get(['action', 'ip_address', 'created_at'])->map(static fn (AuditEvent $e): array => [
                    'action' => $e->action,
                    'ip_address' => $e->ip_address,
                    'at' => $e->created_at->toIso8601String(),
                ])->all(),
        ]);
    }
}
