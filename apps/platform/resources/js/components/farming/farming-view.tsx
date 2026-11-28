import { MapPin, Send, ShieldCheck } from 'lucide-react';
import { useId, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { IssueLine } from '@/components/compliance/message-check';
import type { ComplianceResult } from '@/components/compliance/types';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { deleteJson, getJson, postJson } from '@/lib/content';
import type { FarmArea, FarmDraft, FarmRoutes, FarmStats } from './types';

const blank = {
    name: '',
    city: '',
    postal_code: '',
    tag: '',
    mailing_address: '',
};

type StatKey =
    | 'active'
    | 'pending'
    | 'new'
    | 'sold'
    | 'medianPrice'
    | 'medianSold'
    | 'medianDays'
    | 'supply';

/** The figures as tiles; sale prices only when the MLS allows them. */
export function statTiles(
    stats: FarmStats,
    money: (n: number) => string,
): { key: StatKey; value: string }[] {
    const tiles: { key: StatKey; value: string }[] = [
        { key: 'active', value: String(stats.active) },
        { key: 'pending', value: String(stats.pending) },
        { key: 'new', value: String(stats.new_listings) },
        { key: 'sold', value: String(stats.sold) },
    ];
    if (stats.median_price !== null) {
        tiles.push({ key: 'medianPrice', value: money(stats.median_price) });
    }
    if (stats.median_sold_price !== null) {
        tiles.push({
            key: 'medianSold',
            value: money(stats.median_sold_price),
        });
    }
    if (stats.median_days !== null) {
        tiles.push({ key: 'medianDays', value: String(stats.median_days) });
    }
    if (stats.months_supply !== null) {
        tiles.push({ key: 'supply', value: stats.months_supply.toFixed(1) });
    }

    return tiles;
}

/**
 * Farming market updates (B14.3c): the areas an agent farms, and each
 * month's update from their MLS figures. The compliance engine checks it
 * as an advertisement; it goes to the contacts with the area's tag, with
 * the agent's postal address and an unsubscribe link.
 */
export function FarmingView({
    areas: initial,
    tags,
    routes,
}: {
    areas: FarmArea[];
    tags: string[];
    routes: FarmRoutes;
}) {
    const { t, i18n } = useTranslation();
    const ids = useId();
    const [areas, setAreas] = useState(initial);
    const [form, setForm] = useState(blank);
    const [draft, setDraft] = useState<FarmDraft | null>(null);
    const [subject, setSubject] = useState('');
    const [body, setBody] = useState('');
    const [check, setCheck] = useState<ComplianceResult | null>(null);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const money = (n: number) =>
        new Intl.NumberFormat(i18n.language, {
            style: 'currency',
            currency: 'USD',
            maximumFractionDigits: 0,
        }).format(n);
    const day = (iso: string) =>
        new Intl.DateTimeFormat(i18n.language, {
            month: 'short',
            day: 'numeric',
        }).format(new Date(iso));
    const set = (key: keyof typeof blank, value: string) =>
        setForm((now) => ({ ...now, [key]: value }));

    const run = async (work: () => Promise<void>) => {
        setBusy(true);
        setError(null);
        try {
            await work();
        } catch (e) {
            setError(e instanceof Error ? e.message : String(e));
        } finally {
            setBusy(false);
        }
    };

    const open = (area: FarmArea) =>
        run(async () => {
            const next = (
                await getJson<{ data: FarmDraft }>(routes.draft(area.id))
            ).data;
            setDraft(next);
            setSubject(next.subject);
            setBody(next.body);
            setCheck(next.check);
        });

    return (
        <div className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.3fr)]">
            <div className="space-y-6">
                <form
                    aria-labelledby={`${ids}-add`}
                    className="space-y-4 rounded-reos-lg border border-border bg-card p-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        void run(async () => {
                            const text = (s: string) =>
                                s.trim() === '' ? null : s.trim();
                            const made = (
                                await postJson<{ data: FarmArea }>(
                                    routes.create,
                                    {
                                        ...form,
                                        city: text(form.city),
                                        postal_code: text(form.postal_code),
                                    },
                                )
                            ).data;
                            setAreas((now) => [...now, made]);
                            setForm({
                                ...blank,
                                mailing_address: form.mailing_address,
                            });
                        });
                    }}
                >
                    <h2 id={`${ids}-add`} className="text-lg font-semibold">
                        {t('leads.farm.add')}
                    </h2>
                    <div className="space-y-2">
                        <Label htmlFor={`${ids}-name`}>
                            {t('leads.farm.name')}
                        </Label>
                        <Input
                            id={`${ids}-name`}
                            required
                            minLength={2}
                            maxLength={80}
                            placeholder={t('leads.farm.nameHint')}
                            value={form.name}
                            onChange={(e) => set('name', e.target.value)}
                        />
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor={`${ids}-city`}>
                                {t('leads.farm.city')}
                            </Label>
                            <Input
                                id={`${ids}-city`}
                                maxLength={80}
                                aria-describedby={`${ids}-place`}
                                value={form.city}
                                onChange={(e) => set('city', e.target.value)}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor={`${ids}-zip`}>
                                {t('leads.farm.postalCode')}
                            </Label>
                            <Input
                                id={`${ids}-zip`}
                                inputMode="numeric"
                                maxLength={5}
                                aria-describedby={`${ids}-place`}
                                value={form.postal_code}
                                onChange={(e) =>
                                    set('postal_code', e.target.value)
                                }
                            />
                        </div>
                        <p
                            id={`${ids}-place`}
                            className="text-sm text-muted-foreground sm:col-span-2"
                        >
                            {t('leads.farm.placeHint')}
                        </p>
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor={`${ids}-tag`}>
                            {t('leads.farm.tag')}
                        </Label>
                        <Input
                            id={`${ids}-tag`}
                            required
                            maxLength={60}
                            list={`${ids}-tags`}
                            aria-describedby={`${ids}-tag-hint`}
                            value={form.tag}
                            onChange={(e) => set('tag', e.target.value)}
                        />
                        <datalist id={`${ids}-tags`}>
                            {tags.map((tag) => (
                                <option key={tag} value={tag} />
                            ))}
                        </datalist>
                        <p
                            id={`${ids}-tag-hint`}
                            className="text-sm text-muted-foreground"
                        >
                            {t('leads.farm.tagHint')}
                        </p>
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor={`${ids}-address`}>
                            {t('leads.farm.address')}
                        </Label>
                        <Input
                            id={`${ids}-address`}
                            required
                            minLength={10}
                            maxLength={200}
                            autoComplete="street-address"
                            aria-describedby={`${ids}-address-hint`}
                            value={form.mailing_address}
                            onChange={(e) =>
                                set('mailing_address', e.target.value)
                            }
                        />
                        <p
                            id={`${ids}-address-hint`}
                            className="text-sm text-muted-foreground"
                        >
                            {t('leads.farm.addressHint')}
                        </p>
                    </div>
                    <Button
                        type="submit"
                        disabled={
                            busy ||
                            form.name.trim().length < 2 ||
                            (form.city.trim() === '' &&
                                form.postal_code.trim() === '')
                        }
                    >
                        <MapPin aria-hidden />
                        {t('leads.farm.add')}
                    </Button>
                </form>

                {areas.length === 0 ? (
                    <EmptyState
                        icon={MapPin}
                        title={t('leads.farm.none')}
                        description={t('leads.farm.noneHint')}
                    />
                ) : (
                    <ul className="space-y-2">
                        {areas.map((a) => (
                            <li key={a.id}>
                                <button
                                    type="button"
                                    aria-pressed={draft?.area.id === a.id}
                                    onClick={() => void open(a)}
                                    className="aria-pressed:border-reos-primary flex min-h-12 w-full flex-col items-start gap-1 rounded-reos-md border border-border p-3 text-left aria-pressed:bg-accent"
                                >
                                    <span className="text-base font-medium">
                                        {a.name}
                                    </span>
                                    <span className="text-sm text-muted-foreground">
                                        {[a.city, a.postal_code]
                                            .filter(Boolean)
                                            .join(' ')}{' '}
                                        · {a.tag} ·{' '}
                                        {t('leads.farm.recipients', {
                                            count: a.recipients,
                                        })}{' '}
                                        ·{' '}
                                        {a.last_sent_at
                                            ? t('leads.farm.lastSent', {
                                                  date: day(a.last_sent_at),
                                              })
                                            : t('leads.farm.neverSent')}
                                    </span>
                                </button>
                            </li>
                        ))}
                    </ul>
                )}
                <InputError message={error ?? undefined} />
            </div>

            {draft ? (
                <section
                    aria-labelledby={`${ids}-preview`}
                    className="space-y-4"
                >
                    <h2 id={`${ids}-preview`} className="text-lg font-semibold">
                        {t('leads.farm.preview')}: {draft.area.name}
                    </h2>
                    <dl className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        {statTiles(draft.stats, money).map((tile) => (
                            <div
                                key={tile.key}
                                className="rounded-reos-md border border-border bg-card p-3"
                            >
                                <dt className="text-sm text-muted-foreground">
                                    {t(`leads.farm.stats.${tile.key}`)}
                                </dt>
                                <dd className="text-xl font-semibold">
                                    {tile.value}
                                </dd>
                            </div>
                        ))}
                    </dl>
                    {draft.stats.median_sold_price === null ? (
                        <p className="text-sm text-muted-foreground">
                            {t('leads.farm.soldHidden')}
                        </p>
                    ) : null}
                    <div className="space-y-2">
                        <Label htmlFor={`${ids}-subject`}>
                            {t('leads.farm.subject')}
                        </Label>
                        <Input
                            id={`${ids}-subject`}
                            maxLength={160}
                            value={subject}
                            onChange={(e) => setSubject(e.target.value)}
                        />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor={`${ids}-body`}>
                            {t('leads.farm.body')}
                        </Label>
                        <Textarea
                            id={`${ids}-body`}
                            rows={12}
                            maxLength={5000}
                            value={body}
                            onChange={(e) => setBody(e.target.value)}
                        />
                        <p className="text-sm text-muted-foreground">
                            {draft.attribution}
                        </p>
                    </div>
                    <div
                        role="status"
                        className="space-y-2 rounded-reos-md border border-border p-3"
                    >
                        {check?.ok ? (
                            <p className="flex items-center gap-2 text-base">
                                <ShieldCheck aria-hidden className="size-5" />
                                {t('leads.farm.checkOk')}
                            </p>
                        ) : (
                            <p className="text-base font-medium">
                                {t('leads.farm.checkIssues')}
                            </p>
                        )}
                        {check && check.issues.length > 0 ? (
                            <ul className="list-disc space-y-1 pl-5">
                                {check.issues.map((issue, i) => (
                                    <IssueLine
                                        key={`${issue.code}-${i}`}
                                        issue={issue}
                                    />
                                ))}
                            </ul>
                        ) : null}
                        <Button
                            variant="outline"
                            disabled={busy}
                            onClick={() =>
                                void run(async () => {
                                    setCheck(
                                        (
                                            await postJson<{
                                                data: ComplianceResult;
                                            }>(routes.check, {
                                                channel: 'newsletter',
                                                text: `${subject}\n\n${body}`,
                                            })
                                        ).data,
                                    );
                                })
                            }
                        >
                            {busy
                                ? t('compliance.checking')
                                : t('compliance.check')}
                        </Button>
                    </div>
                    {!draft.can_send && draft.next_send_at ? (
                        <p className="text-base text-muted-foreground">
                            {t('leads.farm.nextSend', {
                                date: day(draft.next_send_at),
                            })}
                        </p>
                    ) : null}
                    <div className="flex flex-wrap gap-2">
                        <Button
                            disabled={
                                busy ||
                                !draft.can_send ||
                                check?.ok !== true ||
                                draft.area.recipients === 0
                            }
                            onClick={() =>
                                void run(async () => {
                                    const next = (
                                        await postJson<{ data: FarmDraft }>(
                                            routes.send(draft.area.id),
                                            { subject, body },
                                        )
                                    ).data;
                                    setDraft(next);
                                    setAreas((now) =>
                                        now.map((a) =>
                                            a.id === next.area.id
                                                ? next.area
                                                : a,
                                        ),
                                    );
                                    toast.success(t('leads.farm.sent'));
                                })
                            }
                        >
                            <Send aria-hidden />
                            {t('leads.farm.send', {
                                count: draft.area.recipients,
                            })}
                        </Button>
                        <Button
                            variant="ghost"
                            disabled={busy}
                            onClick={() =>
                                void run(async () => {
                                    await deleteJson(
                                        routes.remove(draft.area.id),
                                    );
                                    setAreas((now) =>
                                        now.filter(
                                            (a) => a.id !== draft.area.id,
                                        ),
                                    );
                                    setDraft(null);
                                    toast.success(t('leads.farm.removed'));
                                })
                            }
                        >
                            {t('leads.farm.remove')}
                        </Button>
                    </div>
                </section>
            ) : null}
        </div>
    );
}
