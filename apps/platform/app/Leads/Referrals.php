<?php

declare(strict_types=1);

namespace App\Leads;

use App\Authorization\Role;
use App\Compliance\ComplianceEngine;
use App\Deals\DealAccess;
use App\Models\Referral;
use App\Models\User;
use App\Notifications\ReferralAnswered;
use App\Notifications\ReferralEmail;
use App\Notifications\ReferralReceived;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\ActiveRole;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Agent-to-agent referrals (B14.3b, ADR 0067): an agent sends a client to
 * an agent in another area, with the referral fee their brokers agreed.
 *
 * An agent on REOS gets it in their inbox and accepts or declines; on
 * accepting, the client lands in their CRM with a follow-up (the same
 * path as an open house sign-in). An agent outside REOS gets an email.
 * Either agent can mark it closed. The fee is paid broker to broker at
 * closing (TREC: only through the sponsoring broker); REOS records it and
 * never takes or pays a referral fee.
 *
 * @phpstan-type ReferralInput array{to_name: string, to_email: string, to_brokerage: string|null, client_name: string, client_email: string|null, client_phone: string|null, kind: 'buyer'|'seller', area: string|null, note: string|null, fee_percent: int|null}
 * @phpstan-type ReferralRow array{id: string, direction: 'sent'|'received', status: string, kind: string, client: array{name: string, email: string|null, phone: string|null}, area: string|null, note: string|null, fee_percent: int|null, from: array{name: string, broker: string|null}, to: array{name: string, email: string, broker: string|null, on_reos: bool}, contact: string|null, sent_at: string, answered_at: string|null, closed_at: string|null}
 */
final readonly class Referrals
{
    public const KINDS = ['buyer', 'seller'];

    public function __construct(private DealAccess $access, private ActiveRole $roles, private ComplianceEngine $compliance, private Leads $leads, private AuditLogger $audit) {}

    /** @return array{sent: list<ReferralRow>, received: list<ReferralRow>} */
    public function list(User $agent): array
    {
        $this->access->allow($agent);
        $list = ['sent' => [], 'received' => []];

        foreach (['sent' => 'from_user_id', 'received' => 'to_user_id'] as $direction => $column) {
            foreach (Referral::query()->where($column, $agent->id)->with(['sender', 'receiver'])->latest('id')->limit(100)->get() as $referral) {
                $list[$direction][] = self::row($referral, $direction);
            }
        }

        return $list;
    }

    /**
     * @param  ReferralInput  $input
     * @return ReferralRow
     */
    public function send(User $from, array $input): array
    {
        $this->access->allow($from);
        $email = mb_strtolower(trim($input['to_email']));
        $to = $this->agentByEmail($email);

        if ($to?->id === $from->id || $email === mb_strtolower((string) $from->email)) {
            throw ValidationException::withMessages(['to_email' => __('leads.referrals.self')]);
        }

        $clean = static fn (?string $s): ?string => $s === null || trim($s) === '' ? null : trim($s);
        $referral = Referral::query()->create([
            'uuid' => (string) Str::uuid7(), 'organisation_id' => $this->roles->for($from)?->organisation->id,
            'from_user_id' => $from->id, 'to_user_id' => $to?->id,
            'to_name' => $to === null ? trim($input['to_name']) : (string) $to->name, 'to_email' => $email,
            'to_brokerage' => $clean($input['to_brokerage']),
            'from_broker' => $this->compliance->brokerName($from), 'to_broker' => $to === null ? $clean($input['to_brokerage']) : $this->compliance->brokerName($to),
            'client_name' => trim($input['client_name']), 'client_email' => $clean($input['client_email']) === null ? null : mb_strtolower(trim((string) $input['client_email'])),
            'client_phone' => $clean($input['client_phone']), 'kind' => $input['kind'], 'area' => $clean($input['area']), 'note' => $clean($input['note']),
            'fee_percent' => $input['fee_percent'], 'status' => $to === null ? 'emailed' : 'sent',
        ]);
        $this->audit->record('referral.sent', 'referral', $referral->uuid, ['to_reos' => $to !== null, 'kind' => $referral->kind, 'fee_percent' => $referral->fee_percent], $from);

        if ($to !== null) {
            $to->notify(new ReferralReceived($referral->uuid, (string) $from->name, $referral->client_name));
        } else {
            Notification::route('mail', $email)->notify(new ReferralEmail($referral, $from));
        }

        return self::row($referral->load(['sender', 'receiver']), 'sent');
    }

    /**
     * The receiving agent takes the client: into their CRM with a follow-up.
     *
     * @return ReferralRow
     */
    public function accept(User $agent, string $uuid): array
    {
        $referral = $this->received($agent, $uuid);

        if ($referral->status !== 'sent') {
            throw ValidationException::withMessages(['referral' => __('leads.referrals.answered')]);
        }

        $lang = $agent->locale->value;
        $words = ['agent' => (string) $referral->sender->name, 'kind' => self::tr('leads.referrals.kinds.'.$referral->kind, [], $lang), 'area' => $referral->area ?? '—', 'fee' => $referral->fee_percent === null ? '—' : $referral->fee_percent.'%'];
        $note = self::tr('leads.referrals.note', $words, $lang).($referral->note === null ? '' : ' '.self::tr('leads.referrals.noteFrom', ['note' => $referral->note], $lang));
        $first = (preg_split('/\s+/u', $referral->client_name) ?: [''])[0];
        $draft = self::tr('leads.referrals.draft', ['name' => $first, 'from' => (string) $referral->sender->name, 'agent' => (string) $agent->name], $lang);
        $contact = $this->leads->add($agent, ['name' => $referral->client_name, 'email' => $referral->client_email, 'phone' => $referral->client_phone], $note, $draft, false, [self::tr('leads.referrals.tag', [], $lang)], 'referral');

        $referral->forceFill(['status' => 'accepted', 'answered_at' => CarbonImmutable::now(), 'contact_id' => $contact->id])->save();
        $this->audit->record('referral.accepted', 'referral', $referral->uuid, [], $agent);
        $referral->sender->notify(new ReferralAnswered($referral->uuid, (string) $agent->name, $referral->client_name, true));

        return self::row($referral, 'received');
    }

    /** @return ReferralRow */
    public function decline(User $agent, string $uuid): array
    {
        $referral = $this->received($agent, $uuid);

        if ($referral->status !== 'sent') {
            throw ValidationException::withMessages(['referral' => __('leads.referrals.answered')]);
        }

        $referral->forceFill(['status' => 'declined', 'answered_at' => CarbonImmutable::now()])->save();
        $referral->sender->notify(new ReferralAnswered($referral->uuid, (string) $agent->name, $referral->client_name, false));

        return self::row($referral, 'received');
    }

    /**
     * Either agent marks it closed (the client bought or sold).
     *
     * @return ReferralRow
     */
    public function close(User $agent, string $uuid): array
    {
        $this->access->allow($agent);
        $referral = Referral::query()->where('uuid', $uuid)->where(fn ($q) => $q->where('from_user_id', $agent->id)->orWhere('to_user_id', $agent->id))->with(['sender', 'receiver'])->first() ?? throw new NotFoundHttpException;

        if (! in_array($referral->status, ['emailed', 'accepted'], true)) {
            throw ValidationException::withMessages(['referral' => __('leads.referrals.notOpen')]);
        }

        $referral->forceFill(['status' => 'closed', 'closed_at' => CarbonImmutable::now()])->save();

        return self::row($referral, $referral->from_user_id === $agent->id ? 'sent' : 'received');
    }

    private function received(User $agent, string $uuid): Referral
    {
        $this->access->allow($agent);

        return Referral::query()->where('uuid', $uuid)->where('to_user_id', $agent->id)->with(['sender', 'receiver'])->first() ?? throw new NotFoundHttpException;
    }

    /** An agent on REOS with this email, in any brokerage. */
    private function agentByEmail(string $email): ?User
    {
        $agents = DB::table('model_has_roles')->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('roles.name', Role::Agent->value)->where('model_has_roles.model_type', (new User)->getMorphClass())->select('model_has_roles.model_id');

        return User::query()->whereRaw('lower(email) = ?', [$email])->whereIn('id', $agents)->first();
    }

    /**
     * @param  'sent'|'received'  $direction
     * @return ReferralRow
     */
    private static function row(Referral $r, string $direction): array
    {
        $contact = $direction === 'received' && $r->contact_id !== null ? DB::table('contacts')->where('id', $r->contact_id)->value('uuid') : null;

        return [
            'id' => $r->uuid, 'direction' => $direction, 'status' => $r->status, 'kind' => $r->kind,
            'client' => ['name' => $r->client_name, 'email' => $r->client_email, 'phone' => $r->client_phone],
            'area' => $r->area, 'note' => $r->note, 'fee_percent' => $r->fee_percent,
            'from' => ['name' => (string) $r->sender->name, 'broker' => $r->from_broker],
            'to' => ['name' => $r->to_name, 'email' => $r->to_email, 'broker' => $r->to_broker, 'on_reos' => $r->to_user_id !== null],
            'contact' => is_string($contact) ? $contact : null,
            'sent_at' => ($r->created_at ?? CarbonImmutable::now())->toIso8601String(),
            'answered_at' => $r->answered_at?->toIso8601String(), 'closed_at' => $r->closed_at?->toIso8601String(),
        ];
    }

    /** @param array<string, string> $words */
    private static function tr(string $key, array $words, string $lang): string
    {
        $text = __($key, $words, $lang);

        return is_string($text) ? $text : $key;
    }
}
