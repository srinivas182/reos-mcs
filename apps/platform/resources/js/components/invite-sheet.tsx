import { Form, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { store } from '@/routes/invites';
import { request as requestSeats } from '@/routes/seats';
import type { InviteOptions, InviteResult } from '@/types';
import type { RoleName } from '@/types/auth';

const choice =
    'flex min-h-12 cursor-pointer items-center gap-3 rounded-reos-md border px-3 py-2';

/**
 * Invite people (A4.3a, screens 32 and 41): one person or several (pasted
 * lines or a CSV), by email or text, with the role, the team and the
 * language of the invite. Shows the seats left and, at the limit, a way to
 * ask for more.
 */
export function InviteSheet({
    open,
    onOpenChange,
    options,
    tenant,
    defaultRole,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    options: InviteOptions;
    tenant: string;
    defaultRole?: RoleName;
}) {
    const { t } = useTranslation();
    const [mode, setMode] = useState<'one' | 'many'>('one');
    const [role, setRole] = useState<RoleName>(
        defaultRole ?? options.roles[0]?.value ?? 'agent',
    );
    const [result, setResult] = useState<InviteResult | null>(null);
    const [asked, setAsked] = useState(false);
    const seats = options.seats;
    const payer =
        seats.payer === 'organisation'
            ? t('invites.sheet.payers.organisation', { tenant })
            : t('invites.sheet.payers.member');
    const teamsAllowed =
        options.teams.length > 0 && (role === 'agent' || role === 'broker');

    useEffect(
        () =>
            router.on('flash', (event) => {
                const flash = (event as CustomEvent).detail?.flash;

                if (flash?.inviteResult) {
                    setResult(flash.inviteResult as InviteResult);
                }
            }),
        [],
    );

    return (
        <Sheet
            open={open}
            onOpenChange={(next) => {
                onOpenChange(next);

                if (!next) {
                    setResult(null);
                }
            }}
        >
            <SheetContent className="w-full overflow-y-auto sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>{t('invites.sheet.title')}</SheetTitle>
                    <SheetDescription>
                        {t('invites.sheet.description')}
                    </SheetDescription>
                </SheetHeader>

                <Form
                    {...store.form()}
                    options={{ preserveScroll: true }}
                    resetOnSuccess={['contact', 'name', 'people', 'file']}
                    className="space-y-5 px-4 pb-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <fieldset>
                                <legend className="sr-only">
                                    {t('invites.sheet.mode')}
                                </legend>
                                <div className="grid grid-cols-2 gap-2">
                                    {(['one', 'many'] as const).map((value) => (
                                        <label
                                            key={value}
                                            className={cn(
                                                choice,
                                                'justify-center font-medium',
                                                mode === value
                                                    ? 'border-reos-action bg-reos-action-tint'
                                                    : 'border-border',
                                            )}
                                        >
                                            <input
                                                type="radio"
                                                name="mode"
                                                value={value}
                                                checked={mode === value}
                                                onChange={() => setMode(value)}
                                                className="size-4 accent-reos-action"
                                            />
                                            {t(`invites.sheet.${value}`)}
                                        </label>
                                    ))}
                                </div>
                            </fieldset>

                            {mode === 'one' ? (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="invite-contact">
                                            {t('invites.sheet.contact')}
                                        </Label>
                                        <Input
                                            id="invite-contact"
                                            name="contact"
                                            autoComplete="off"
                                            spellCheck={false}
                                            placeholder={t(
                                                'invites.sheet.contactPlaceholder',
                                            )}
                                            aria-invalid={Boolean(
                                                errors.contact,
                                            )}
                                        />
                                        <InputError message={errors.contact} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="invite-name">
                                            {t('invites.sheet.name')}
                                        </Label>
                                        <Input
                                            id="invite-name"
                                            name="name"
                                            autoComplete="off"
                                        />
                                    </div>
                                </>
                            ) : (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="invite-people">
                                            {t('invites.sheet.people')}
                                        </Label>
                                        <Textarea
                                            id="invite-people"
                                            name="people"
                                            rows={6}
                                            spellCheck={false}
                                            aria-describedby="invite-people-hint"
                                            aria-invalid={Boolean(
                                                errors.people,
                                            )}
                                        />
                                        <p
                                            id="invite-people-hint"
                                            className="text-sm text-muted-foreground"
                                        >
                                            {t('invites.sheet.peopleHint')}
                                        </p>
                                        <InputError message={errors.people} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="invite-file">
                                            {t('invites.sheet.file')}
                                        </Label>
                                        <Input
                                            id="invite-file"
                                            name="file"
                                            type="file"
                                            accept=".csv,text/csv,text/plain"
                                        />
                                        <InputError message={errors.file} />
                                    </div>
                                </>
                            )}

                            {!options.can_text ? (
                                <p className="text-sm text-muted-foreground">
                                    {t('invites.sheet.noText')}
                                </p>
                            ) : null}

                            <fieldset className="space-y-2">
                                <legend className="mb-2 text-sm font-medium">
                                    {t('invites.sheet.role')}
                                </legend>
                                {options.roles.map((option) => (
                                    <label
                                        key={option.value}
                                        className={cn(
                                            choice,
                                            role === option.value
                                                ? 'border-reos-action bg-reos-action-tint'
                                                : 'border-border',
                                        )}
                                    >
                                        <input
                                            type="radio"
                                            name="role"
                                            value={option.value}
                                            checked={role === option.value}
                                            onChange={() =>
                                                setRole(option.value)
                                            }
                                            className="size-4 accent-reos-action"
                                        />
                                        <span className="flex-1 font-medium">
                                            {option.label}
                                        </span>
                                        {option.seat ? (
                                            <span className="text-sm text-muted-foreground">
                                                {t('invites.sheet.seatNote')}
                                            </span>
                                        ) : null}
                                    </label>
                                ))}
                                <InputError message={errors.role} />
                            </fieldset>

                            {teamsAllowed ? (
                                <div className="grid gap-2">
                                    <Label htmlFor="invite-team">
                                        {t('invites.sheet.team')}
                                    </Label>
                                    <select
                                        id="invite-team"
                                        name="team_id"
                                        defaultValue={
                                            options.teams.length === 1 &&
                                            options.roles.length === 1
                                                ? String(options.teams[0].id)
                                                : ''
                                        }
                                        className="h-12 rounded-reos-md border border-input bg-card px-3 text-base text-foreground"
                                    >
                                        {options.roles.length > 1 ? (
                                            <option value="">
                                                {t('invites.sheet.noTeam')}
                                            </option>
                                        ) : null}
                                        {options.teams.map((team) => (
                                            <option
                                                key={team.id}
                                                value={team.id}
                                            >
                                                {team.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.team_id} />
                                </div>
                            ) : null}

                            <fieldset className="space-y-2">
                                <legend className="mb-2 text-sm font-medium">
                                    {t('invites.sheet.language')}
                                </legend>
                                <div className="grid grid-cols-2 gap-2">
                                    {(['en', 'es'] as const).map((value) => (
                                        <label
                                            key={value}
                                            className={cn(
                                                choice,
                                                'border-border',
                                            )}
                                        >
                                            <input
                                                type="radio"
                                                name="locale"
                                                value={value}
                                                defaultChecked={
                                                    options.locale === value
                                                }
                                                className="size-4 accent-reos-action"
                                            />
                                            {value === 'en'
                                                ? t('invites.sheet.english')
                                                : t('invites.sheet.spanish')}
                                        </label>
                                    ))}
                                </div>
                            </fieldset>

                            <p className="text-sm text-muted-foreground">
                                {seats.limit === null
                                    ? t('invites.sheet.seatsNoLimit', { payer })
                                    : t('invites.sheet.seats', {
                                          left: seats.left ?? 0,
                                          limit: seats.limit,
                                          payer,
                                      })}
                            </p>

                            {errors.seats ? (
                                <div
                                    role="alert"
                                    className="space-y-3 rounded-reos-md bg-reos-tone-warning-bg p-3 text-reos-tone-warning-fg"
                                >
                                    <p>{errors.seats}</p>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={asked}
                                        onClick={() =>
                                            router.post(
                                                requestSeats().url,
                                                {},
                                                {
                                                    preserveScroll: true,
                                                    onSuccess: () =>
                                                        setAsked(true),
                                                },
                                            )
                                        }
                                    >
                                        {t('invites.sheet.askSeats')}
                                    </Button>
                                </div>
                            ) : null}

                            {result && result.skipped.length > 0 ? (
                                <div className="space-y-2 rounded-reos-md border border-border p-3">
                                    <h3 className="text-sm font-semibold">
                                        {t('invites.sheet.skippedTitle')}
                                    </h3>
                                    <ul className="space-y-1 text-sm">
                                        {result.skipped.map((item, index) => (
                                            <li
                                                key={`${item.contact}-${index}`}
                                                className="flex flex-wrap justify-between gap-x-3"
                                            >
                                                <span className="break-all">
                                                    {item.contact}
                                                </span>
                                                <span className="text-muted-foreground">
                                                    {t(
                                                        `invites.sheet.reasons.${item.reason}`,
                                                    )}
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            ) : null}

                            <Button
                                type="submit"
                                className="w-full"
                                disabled={processing}
                            >
                                {processing
                                    ? t('invites.sheet.sending')
                                    : t('invites.sheet.send')}
                            </Button>
                        </>
                    )}
                </Form>
            </SheetContent>
        </Sheet>
    );
}
