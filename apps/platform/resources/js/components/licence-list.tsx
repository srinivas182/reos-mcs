import { Form, router } from '@inertiajs/react';
import { BadgeCheck, Clock, IdCard, Plus } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { CeRequirementList } from '@/components/ce-requirement';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
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
import { nativeSelect } from '@/lib/tenants';
import { undoToast } from '@/lib/undo-toast';
import { cn } from '@/lib/utils';
import { destroy, restore, store, update } from '@/routes/licences';
import type { BrokerRole, Licence, LicenceState } from '@/types';

const ROLES: BrokerRole[] = [
    'sponsoring_broker',
    'designated_broker',
    'delegated_supervisor',
];

const tone: Record<Licence['status'], string> = {
    active: 'bg-reos-tone-success-bg text-reos-tone-success-fg',
    inactive: 'bg-reos-tone-warning-bg text-reos-tone-warning-fg',
    expired: 'bg-reos-tone-danger-bg text-reos-tone-danger-fg',
    pending_review: 'bg-reos-tone-info-bg text-reos-tone-info-fg',
    rejected: 'bg-reos-tone-danger-bg text-reos-tone-danger-fg',
};

type Props = {
    licences: Licence[];
    states: LicenceState[];
};

/**
 * Licences in every state (web journey screen 23, A5.1): state, type,
 * number, status, renewal date, sponsoring broker and how it was checked.
 * Licences in states that aren't live are tracked for renewal only. Add a
 * state licence; removing one offers Undo.
 */
export function LicenceList({ licences, states }: Props) {
    const { t, i18n } = useTranslation();
    const [adding, setAdding] = useState(false);
    const date = (value: string | null) =>
        value
            ? new Intl.DateTimeFormat(i18n.language, {
                  dateStyle: 'medium',
                  timeZone: 'UTC',
              }).format(new Date(`${value}T00:00:00Z`))
            : null;

    const remove = (licence: Licence) =>
        router.delete(destroy(licence.id).url, {
            preserveScroll: true,
            onSuccess: () =>
                undoToast({
                    message: t('licences.removed', {
                        state: licence.state.name,
                    }),
                    onUndo: () =>
                        router.post(
                            restore(licence.id).url,
                            {},
                            { preserveScroll: true },
                        ),
                }),
        });

    const setRole = (licence: Licence, role: string) =>
        router.patch(
            update(licence.id).url,
            { broker_role: role === '' ? null : role },
            { preserveScroll: true },
        );

    return (
        <div className="space-y-4">
            {licences.length === 0 ? (
                <EmptyState
                    icon={IdCard}
                    title={t('licences.emptyTitle')}
                    description={t('licences.emptyDescription')}
                    action={
                        <Button onClick={() => setAdding(true)}>
                            <Plus aria-hidden />
                            {t('licences.add')}
                        </Button>
                    }
                />
            ) : (
                <ul className="divide-y divide-border rounded-reos-md border border-border bg-card">
                    {licences.map((licence) => (
                        <li key={licence.id} className="space-y-3 p-4">
                            <div className="flex flex-wrap items-start gap-4">
                                <span
                                    aria-hidden
                                    className="flex size-12 shrink-0 items-center justify-center rounded-reos-md bg-primary text-sm font-bold text-primary-foreground"
                                >
                                    {licence.state.code}
                                </span>
                                <div className="min-w-0 flex-1">
                                    <p className="font-semibold text-foreground">
                                        {t('licences.title', {
                                            state: licence.state.name,
                                            type: t(
                                                `licences.types.${licence.type}`,
                                            ),
                                        })}
                                    </p>
                                    <p className="text-body text-muted-foreground">
                                        #{licence.number}
                                        {licence.verified_by
                                            ? ` · ${t(`licences.verifiedBy.${licence.verified_by === 'reviewer' ? 'reviewer' : 'regulator'}`, { date: date(licence.verified_at?.slice(0, 10) ?? null) })}`
                                            : ''}
                                    </p>
                                </div>
                                <span
                                    className={cn(
                                        'rounded-reos-pill px-3 py-1 text-sm font-semibold',
                                        licence.tracked_only
                                            ? 'bg-reos-tone-neutral-bg text-reos-tone-neutral-fg'
                                            : tone[licence.status],
                                    )}
                                >
                                    {licence.tracked_only
                                        ? t('licences.comingSoon')
                                        : t(
                                              `licences.statuses.${licence.status}`,
                                          )}
                                </span>
                            </div>

                            <dl className="grid gap-x-6 gap-y-1 text-body sm:grid-cols-2">
                                {licence.sponsor_name ? (
                                    <div className="flex justify-between gap-4">
                                        <dt className="text-muted-foreground">
                                            {t('licences.sponsor')}
                                        </dt>
                                        <dd className="font-medium">
                                            {licence.sponsor_name}
                                        </dd>
                                    </div>
                                ) : null}
                                {licence.ce && !licence.tracked_only ? (
                                    <div className="flex justify-between gap-4">
                                        <dt className="text-muted-foreground">
                                            {t('licences.ceThisCycle')}
                                        </dt>
                                        <dd className="font-medium">
                                            {t('ce.tile.hours', {
                                                done: licence.ce.hours_done,
                                                required:
                                                    licence.ce.hours_required,
                                            })}
                                        </dd>
                                    </div>
                                ) : null}
                                {licence.expires_on ? (
                                    <div className="flex justify-between gap-4">
                                        <dt className="text-muted-foreground">
                                            {t('licences.renewal')}
                                        </dt>
                                        <dd className="font-medium">
                                            {date(licence.expires_on)}
                                        </dd>
                                    </div>
                                ) : null}
                            </dl>

                            {licence.ce && !licence.tracked_only ? (
                                <details className="rounded-reos-md border border-border px-4 py-2">
                                    <summary className="flex min-h-12 cursor-pointer items-center font-medium text-primary">
                                        {t('ce.whatYouNeed')}
                                    </summary>
                                    <div className="pb-2">
                                        <CeRequirementList ce={licence.ce} />
                                    </div>
                                </details>
                            ) : null}
                            {licence.tracked_only ? (
                                <p className="text-body text-muted-foreground">
                                    {t('licences.trackedOnly', {
                                        state: licence.state.name,
                                    })}
                                </p>
                            ) : null}
                            {licence.status === 'pending_review' ||
                            licence.status === 'rejected' ? (
                                <p className="flex items-start gap-2 text-body text-muted-foreground">
                                    <Clock
                                        aria-hidden
                                        className="mt-1 size-4 shrink-0"
                                    />
                                    {licence.status === 'pending_review'
                                        ? t('licences.waiting', {
                                              note: licence.review_note ?? '',
                                          })
                                        : t('licences.rejectedNote', {
                                              note: licence.review_note ?? '',
                                          })}
                                </p>
                            ) : null}

                            <div className="flex flex-wrap items-end gap-3">
                                {licence.tracked_only ? null : (
                                    <div className="grid min-w-64 flex-1 gap-2 sm:max-w-sm">
                                        <Label
                                            htmlFor={`licence-role-${licence.id}`}
                                        >
                                            {t('licences.role')}
                                        </Label>
                                        <select
                                            id={`licence-role-${licence.id}`}
                                            className={nativeSelect}
                                            value={licence.broker_role ?? ''}
                                            onChange={(e) =>
                                                setRole(licence, e.target.value)
                                            }
                                        >
                                            <option value="">
                                                {t('licences.roles.none')}
                                            </option>
                                            {ROLES.filter(
                                                (role) =>
                                                    licence.type === 'broker' ||
                                                    role ===
                                                        'delegated_supervisor',
                                            ).map((role) => (
                                                <option key={role} value={role}>
                                                    {t(
                                                        `licences.roles.${role}`,
                                                    )}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                )}
                                <Button
                                    variant="outline"
                                    onClick={() => remove(licence)}
                                >
                                    {t('licences.remove', {
                                        state: licence.state.name,
                                    })}
                                </Button>
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            {licences.length > 0 ? (
                <Button variant="outline" onClick={() => setAdding(true)}>
                    <Plus aria-hidden />
                    {t('licences.add')}
                </Button>
            ) : null}

            <AddLicenceDialog
                open={adding}
                onOpenChange={setAdding}
                states={states}
            />
        </div>
    );
}

/**
 * "Add a state licence" (agent journey screen 149 on the web): the state and
 * licence number, checked with the state's regulator. A number the
 * regulator doesn't have can be sent to a reviewer instead.
 */
export function AddLicenceDialog({
    open,
    onOpenChange,
    states,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    states: LicenceState[];
}) {
    const { t } = useTranslation();
    const [review, setReview] = useState(false);
    const live = states.filter((s) => s.live);
    const later = states.filter((s) => !s.live);

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setReview(false);
                onOpenChange(next);
            }}
        >
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{t('licences.addTitle')}</DialogTitle>
                    <DialogDescription>
                        {t('licences.addIntro')}
                    </DialogDescription>
                </DialogHeader>
                <Form
                    {...store.form()}
                    options={{ preserveScroll: true }}
                    onSuccess={() => onOpenChange(false)}
                    className="space-y-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="licence-state">
                                    {t('licences.state')}
                                </Label>
                                <select
                                    id="licence-state"
                                    name="state"
                                    required
                                    className={nativeSelect}
                                    defaultValue={live[0]?.code ?? ''}
                                >
                                    <optgroup label={t('licences.liveStates')}>
                                        {live.map((s) => (
                                            <option key={s.code} value={s.code}>
                                                {s.name}
                                            </option>
                                        ))}
                                    </optgroup>
                                    <optgroup label={t('licences.laterStates')}>
                                        {later.map((s) => (
                                            <option key={s.code} value={s.code}>
                                                {s.name}
                                            </option>
                                        ))}
                                    </optgroup>
                                </select>
                                <InputError message={errors.state} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="licence-number">
                                    {t('licences.number')}
                                </Label>
                                <Input
                                    id="licence-number"
                                    name="number"
                                    required
                                    maxLength={40}
                                    autoComplete="off"
                                    inputMode="text"
                                />
                                <InputError message={errors.number} />
                            </div>
                            {errors.number ? (
                                <label className="flex min-h-12 items-center gap-3 rounded-reos-md border border-input px-3">
                                    <Checkbox
                                        checked={review}
                                        onCheckedChange={(on) =>
                                            setReview(on === true)
                                        }
                                    />
                                    {t('licences.askReview')}
                                </label>
                            ) : null}
                            {review ? (
                                <input type="hidden" name="review" value="1" />
                            ) : null}
                            <p className="flex items-start gap-2 text-body text-muted-foreground">
                                <BadgeCheck
                                    aria-hidden
                                    className="mt-1 size-4 shrink-0"
                                />
                                {t('licences.howChecked')}
                            </p>
                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => onOpenChange(false)}
                                >
                                    {t('common.cancel')}
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {t('licences.addButton')}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
