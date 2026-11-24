import { Check, Gavel, Lock, Palette, Undo2 } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';

export type AdvertisingRules = {
    approval_new_agents: boolean;
    approval_all: boolean;
    banned: string[];
};

export type PendingPost = {
    id: string;
    agent: string;
    body: string;
    attribution: string;
    media: { url: string; kind: string }[];
    channels: string[];
    scheduled_at: string | null;
    requested_at: string | null;
    issues: { code: string; phrase: string; fix: string }[];
};

export type AdvertisingPage = {
    brokerage: string;
    texas: {
        key: string;
        text: string;
        citation: string;
        url: string | null;
    }[];
    checks: { fair_housing: number; claims: number };
    rules: AdvertisingRules;
    brand: { colour: string | null; logo: string | null };
    pending: PendingPost[];
};

/**
 * Advertising policy (broker web screen 35; broker journey screen 6): the
 * locked Texas rules, the brokerage's rules with switches, banned phrases,
 * the brand kit, and the posts waiting for approval with Approve or Send
 * back with a note.
 */
export function AdvertisingPolicyView({
    data,
    settingsHref,
    onSave,
    onApprove,
    onSendBack,
    busy = false,
}: {
    data: AdvertisingPage;
    settingsHref: string;
    onSave: (rules: AdvertisingRules) => void;
    onApprove: (id: string) => void;
    onSendBack: (id: string, note: string) => void;
    busy?: boolean;
}) {
    const { t, i18n } = useTranslation();
    const [newAgents, setNewAgents] = useState(data.rules.approval_new_agents);
    const [all, setAll] = useState(data.rules.approval_all);
    const [banned, setBanned] = useState(data.rules.banned.join('\n'));
    const [notes, setNotes] = useState<Record<string, string>>({});
    const when = (iso: string) =>
        new Intl.DateTimeFormat(i18n.language, {
            dateStyle: 'medium',
            timeStyle: 'short',
        }).format(new Date(iso));

    return (
        <div className="space-y-6">
            <section aria-labelledby="adv-pending" className="space-y-3">
                <h2 id="adv-pending" className="text-lg font-semibold">
                    {t('studio.policy.pending')}
                </h2>
                {data.pending.length === 0 ? (
                    <EmptyState
                        icon={Check}
                        title={t('studio.policy.noPending')}
                        description={t('studio.policy.noPendingBody')}
                    />
                ) : (
                    <ul className="space-y-3">
                        {data.pending.map((p) => (
                            <li
                                key={p.id}
                                className="space-y-3 rounded-reos-lg border border-border bg-card p-5"
                            >
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <p className="text-base font-semibold">
                                        {p.agent}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {p.scheduled_at
                                            ? t('studio.policy.scheduledFor', {
                                                  time: when(p.scheduled_at),
                                              })
                                            : t('studio.policy.now')}
                                    </p>
                                </div>
                                <p className="text-base whitespace-pre-line">
                                    {p.body}
                                </p>
                                <p className="text-sm whitespace-pre-line text-muted-foreground">
                                    {p.attribution}
                                </p>
                                {p.media.length > 0 ? (
                                    <div className="flex gap-2">
                                        {p.media
                                            .slice(0, 4)
                                            .map((m) =>
                                                m.kind === 'image' ? (
                                                    <img
                                                        key={m.url}
                                                        src={m.url}
                                                        alt=""
                                                        className="size-24 rounded-reos-md object-cover"
                                                    />
                                                ) : null,
                                            )}
                                    </div>
                                ) : null}
                                <div className="flex flex-wrap gap-2">
                                    {p.channels.map((c) => (
                                        <Badge key={c} variant="outline">
                                            {t(
                                                `social.channels.${c as 'facebook'}`,
                                            )}
                                        </Badge>
                                    ))}
                                </div>
                                {p.issues.length > 0 ? (
                                    <p className="text-base text-destructive">
                                        {t('studio.policy.issues', {
                                            phrases: p.issues
                                                .map((i) => i.phrase)
                                                .join(', '),
                                        })}
                                    </p>
                                ) : null}
                                <div className="space-y-2">
                                    <Label htmlFor={`note-${p.id}`}>
                                        {t('studio.policy.noteLabel')}
                                    </Label>
                                    <Textarea
                                        id={`note-${p.id}`}
                                        rows={2}
                                        maxLength={500}
                                        placeholder={t('studio.policy.note')}
                                        value={notes[p.id] ?? ''}
                                        onChange={(e) =>
                                            setNotes({
                                                ...notes,
                                                [p.id]: e.target.value,
                                            })
                                        }
                                    />
                                </div>
                                <div className="flex flex-wrap gap-3">
                                    <Button
                                        type="button"
                                        disabled={busy}
                                        onClick={() => onApprove(p.id)}
                                    >
                                        <Check aria-hidden />
                                        {t('studio.policy.approve')}
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={
                                            busy ||
                                            (notes[p.id] ?? '').trim().length <
                                                3
                                        }
                                        onClick={() =>
                                            onSendBack(
                                                p.id,
                                                (notes[p.id] ?? '').trim(),
                                            )
                                        }
                                    >
                                        <Undo2 aria-hidden />
                                        {t('studio.policy.sendBack')}
                                    </Button>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <section
                aria-labelledby="adv-texas"
                className="space-y-3 rounded-reos-lg border border-border bg-card p-5"
            >
                <h2
                    id="adv-texas"
                    className="flex items-center gap-2 text-lg font-semibold"
                >
                    <Lock aria-hidden className="size-5" />
                    {t('studio.policy.texas')}
                </h2>
                <p className="text-base text-muted-foreground">
                    {t('studio.policy.texasBody')}
                </p>
                <ul className="space-y-2">
                    {data.texas.map((r) => (
                        <li key={r.key} className="flex gap-3 text-base">
                            <Gavel
                                aria-hidden
                                className="mt-1 size-5 shrink-0 text-muted-foreground"
                            />
                            <span>
                                {r.text}{' '}
                                {r.url ? (
                                    <a
                                        href={r.url}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="font-medium text-primary underline-offset-4 hover:underline"
                                    >
                                        {r.citation}
                                    </a>
                                ) : (
                                    <span className="text-muted-foreground">
                                        {r.citation}
                                    </span>
                                )}
                            </span>
                        </li>
                    ))}
                </ul>
                <p className="text-base">
                    {t('studio.policy.checks', {
                        fairHousing: data.checks.fair_housing,
                        claims: data.checks.claims,
                    })}
                </p>
            </section>

            <form
                aria-labelledby="adv-rules"
                className="space-y-4 rounded-reos-lg border border-border bg-card p-5"
                onSubmit={(e) => {
                    e.preventDefault();
                    onSave({
                        approval_new_agents: newAgents,
                        approval_all: all,
                        banned: banned
                            .split('\n')
                            .map((s) => s.trim())
                            .filter(Boolean),
                    });
                }}
            >
                <h2 id="adv-rules" className="text-lg font-semibold">
                    {t('studio.policy.brokerage')}
                </h2>
                <div className="flex items-center gap-3">
                    <Switch
                        id="adv-new"
                        checked={newAgents}
                        onCheckedChange={setNewAgents}
                    />
                    <Label htmlFor="adv-new" className="text-base">
                        {t('studio.policy.newAgents')}
                    </Label>
                </div>
                <div className="flex items-center gap-3">
                    <Switch
                        id="adv-all"
                        checked={all}
                        onCheckedChange={setAll}
                    />
                    <Label htmlFor="adv-all" className="text-base">
                        {t('studio.policy.all')}
                    </Label>
                </div>
                <div className="space-y-2">
                    <Label htmlFor="adv-banned">
                        {t('studio.policy.banned')}
                    </Label>
                    <Textarea
                        id="adv-banned"
                        rows={4}
                        value={banned}
                        onChange={(e) => setBanned(e.target.value)}
                    />
                    <p className="text-sm text-muted-foreground">
                        {t('studio.policy.bannedHelp')}
                    </p>
                </div>
                <Button type="submit" variant="outline" disabled={busy}>
                    {t('studio.policy.save')}
                </Button>
            </form>

            <section
                aria-labelledby="adv-brand"
                className="space-y-3 rounded-reos-lg border border-border bg-card p-5"
            >
                <h2
                    id="adv-brand"
                    className="flex items-center gap-2 text-lg font-semibold"
                >
                    <Palette aria-hidden className="size-5" />
                    {t('studio.policy.brand')}
                </h2>
                <p className="text-base text-muted-foreground">
                    {t('studio.policy.brandBody')}
                </p>
                <div className="flex flex-wrap items-center gap-4">
                    {data.brand.logo ? (
                        <img
                            src={data.brand.logo}
                            alt=""
                            className="h-12 w-auto"
                        />
                    ) : null}
                    {data.brand.colour ? (
                        <span className="flex items-center gap-2 text-base">
                            <span
                                aria-hidden
                                className="inline-block size-8 rounded-reos-md border border-border"
                                style={{ backgroundColor: data.brand.colour }}
                            />
                            {data.brand.colour}
                        </span>
                    ) : (
                        <span className="text-base text-muted-foreground">
                            {t('studio.policy.noColour')}
                        </span>
                    )}
                    <a
                        href={settingsHref}
                        className="inline-flex min-h-12 items-center text-base font-medium text-primary underline-offset-4 hover:underline"
                    >
                        {t('studio.policy.brandLink')}
                    </a>
                </div>
            </section>
        </div>
    );
}
