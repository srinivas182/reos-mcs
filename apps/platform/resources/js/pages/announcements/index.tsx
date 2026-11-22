import { Head, router } from '@inertiajs/react';
import { Megaphone, Plus } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { roleName } from '@/lib/roles';
import { store } from '@/routes/announcements';
import type { RoleName } from '@/types/auth';

type Sent = {
    id: string;
    title: string;
    body: string;
    author: string | null;
    audience: { roles?: string[]; teams?: number[]; states?: string[] };
    recipients: number;
    read: number;
    sent_at: string;
};

type Options = {
    roles: string[];
    teams: { id: number; name: string }[];
    states: string[];
};

function Choices<T extends string | number>({
    id,
    label,
    items,
    chosen,
    onChange,
}: {
    id: string;
    label: string;
    items: { value: T; label: string }[];
    chosen: T[];
    onChange: (next: T[]) => void;
}) {
    if (items.length === 0) {
        return null;
    }

    return (
        <fieldset className="grid gap-1">
            <legend className="text-sm font-medium">{label}</legend>
            <div className="grid gap-1 sm:grid-cols-2">
                {items.map((item) => (
                    <label
                        key={item.value}
                        htmlFor={`${id}-${item.value}`}
                        className="flex min-h-12 items-center gap-3 text-body"
                    >
                        <Checkbox
                            id={`${id}-${item.value}`}
                            checked={chosen.includes(item.value)}
                            onCheckedChange={(on) =>
                                onChange(
                                    on === true
                                        ? [...chosen, item.value]
                                        : chosen.filter(
                                              (v) => v !== item.value,
                                          ),
                                )
                            }
                        />
                        {item.label}
                    </label>
                ))}
            </div>
        </fieldset>
    );
}

/**
 * Announcements (B6.1b; association admin screen "Announcements", and the
 * brokerage and school admin screens): what the organization has sent with
 * "Read by 82 of 140", and New announcement to everyone or a group (roles,
 * teams, states). People see it on Messages (web) and Today (phone).
 */
export default function Announcements({
    organisation,
    sent,
    options,
}: {
    organisation: string;
    sent: Sent[];
    options: Options;
}) {
    const { t, i18n } = useTranslation();
    const [open, setOpen] = useState(false);
    const [title, setTitle] = useState('');
    const [body, setBody] = useState('');
    const [roles, setRoles] = useState<string[]>([]);
    const [teams, setTeams] = useState<number[]>([]);
    const [states, setStates] = useState<string[]>([]);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);
    const date = (iso: string) =>
        new Date(iso).toLocaleString(i18n.language, {
            dateStyle: 'medium',
            timeStyle: 'short',
        });

    const submit = () =>
        router.post(
            store().url,
            { title, body, roles, teams, states },
            {
                preserveScroll: true,
                onStart: () => setBusy(true),
                onFinish: () => setBusy(false),
                onError: setErrors,
                onSuccess: () => {
                    setOpen(false);
                    setTitle('');
                    setBody('');
                    setRoles([]);
                    setTeams([]);
                    setStates([]);
                    setErrors({});
                },
            },
        );

    const audience = (item: Sent) => {
        const parts = [
            ...(item.audience.roles ?? []).map((r) =>
                roleName(t, r as RoleName),
            ),
            ...(item.audience.teams ?? []).map(
                (id) =>
                    options.teams.find((team) => team.id === id)?.name ?? '',
            ),
            ...(item.audience.states ?? []),
        ].filter(Boolean);

        return parts.length > 0
            ? parts.join(', ')
            : t('announcements.everyone');
    };

    return (
        <>
            <Head title={t('announcements.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('announcements.title')}
                    description={t('announcements.purpose', { organisation })}
                    actions={
                        <Button onClick={() => setOpen(true)}>
                            <Plus aria-hidden />
                            {t('announcements.new')}
                        </Button>
                    }
                />
                <section className="rounded-reos-lg border border-border bg-card p-5">
                    {sent.length === 0 ? (
                        <EmptyState
                            icon={Megaphone}
                            title={t('announcements.empty')}
                            description={t('announcements.emptyBody')}
                            action={
                                <Button onClick={() => setOpen(true)}>
                                    {t('announcements.new')}
                                </Button>
                            }
                        />
                    ) : (
                        <ul className="divide-y divide-border">
                            {sent.map((item) => {
                                const percent =
                                    item.recipients > 0
                                        ? Math.round(
                                              (item.read * 100) /
                                                  item.recipients,
                                          )
                                        : 0;

                                return (
                                    <li
                                        key={item.id}
                                        className="space-y-2 py-4"
                                    >
                                        <div className="flex flex-wrap items-baseline justify-between gap-2">
                                            <h2 className="text-lg font-semibold">
                                                {item.title}
                                            </h2>
                                            <span className="text-sm text-muted-foreground">
                                                {[
                                                    date(item.sent_at),
                                                    item.author,
                                                ]
                                                    .filter(Boolean)
                                                    .join(' · ')}
                                            </span>
                                        </div>
                                        <p className="text-body whitespace-pre-wrap">
                                            {item.body}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {t('announcements.to', {
                                                audience: audience(item),
                                            })}
                                        </p>
                                        <div className="flex items-center gap-3">
                                            <div
                                                className="h-2 w-40 overflow-hidden rounded-full bg-muted"
                                                role="progressbar"
                                                aria-valuemin={0}
                                                aria-valuemax={item.recipients}
                                                aria-valuenow={item.read}
                                                aria-label={t(
                                                    'announcements.readBy',
                                                    {
                                                        read: item.read,
                                                        total: item.recipients,
                                                    },
                                                )}
                                            >
                                                <div
                                                    className="h-full bg-primary"
                                                    style={{
                                                        width: `${percent}%`,
                                                    }}
                                                />
                                            </div>
                                            <span className="text-body font-medium tabular-nums">
                                                {t('announcements.readBy', {
                                                    read: item.read,
                                                    total: item.recipients,
                                                })}
                                            </span>
                                        </div>
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </section>
            </div>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                    <DialogHeader>
                        <DialogTitle>{t('announcements.new')}</DialogTitle>
                        <DialogDescription>
                            {t('announcements.newDescription')}
                        </DialogDescription>
                    </DialogHeader>
                    <form
                        className="space-y-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            submit();
                        }}
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="announcement-title">
                                {t('announcements.fields.title')}
                            </Label>
                            <Input
                                id="announcement-title"
                                value={title}
                                maxLength={120}
                                required
                                onChange={(e) => setTitle(e.target.value)}
                            />
                            <InputError message={errors.title} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="announcement-body">
                                {t('announcements.fields.body')}
                            </Label>
                            <Textarea
                                id="announcement-body"
                                rows={5}
                                maxLength={4000}
                                required
                                value={body}
                                onChange={(e) => setBody(e.target.value)}
                            />
                            <InputError message={errors.body} />
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {t('announcements.audienceHint')}
                        </p>
                        <Choices
                            id="announcement-role"
                            label={t('announcements.fields.roles')}
                            items={options.roles.map((r) => ({
                                value: r,
                                label: roleName(t, r as RoleName),
                            }))}
                            chosen={roles}
                            onChange={setRoles}
                        />
                        <Choices
                            id="announcement-team"
                            label={t('announcements.fields.teams')}
                            items={options.teams.map((team) => ({
                                value: team.id,
                                label: team.name,
                            }))}
                            chosen={teams}
                            onChange={setTeams}
                        />
                        <Choices
                            id="announcement-state"
                            label={t('announcements.fields.states')}
                            items={options.states.map((s) => ({
                                value: s,
                                label: s,
                            }))}
                            chosen={states}
                            onChange={setStates}
                        />
                        <InputError message={errors.audience} />
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setOpen(false)}
                            >
                                {t('messages.cancel')}
                            </Button>
                            <Button
                                type="submit"
                                disabled={
                                    busy ||
                                    title.trim() === '' ||
                                    body.trim() === ''
                                }
                            >
                                {t('announcements.send')}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
