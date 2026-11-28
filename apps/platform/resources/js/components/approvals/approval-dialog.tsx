import { router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
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
import { Textarea } from '@/components/ui/textarea';
import { nativeSelect } from '@/lib/tenants';
import { defaults, store, update } from '@/routes/school/approvals';
import type {
    ApprovalOptions,
    ApprovalRow,
    ApprovalStatus,
    Delivery,
    SeatTime,
} from './types';

export type DialogMode =
    | { kind: 'add'; courseId?: number }
    | { kind: 'edit' | 'renew'; approval: ApprovalRow };

const SEAT_FIELDS = [
    'max_hours_per_day',
    'checkin_minutes',
    'presence_percent',
    'makeup_min_percent',
    'makeup_days',
] as const;

type Draft = {
    course_id: string;
    state_code: string;
    delivery: Delivery;
    number: string;
    hours: string;
    approved_on: string;
    expires_on: string;
    status: ApprovalStatus;
    note: string;
    records_live: boolean;
    audio_allowed: boolean;
} & Record<(typeof SEAT_FIELDS)[number], string>;

const text = (value: number | string | null | undefined): string =>
    value === null || value === undefined ? '' : String(value);

/**
 * Add approval, Add state, Edit and Renew (screen 50, A7.1): the approval as
 * the regulator issued it, with seat-time and attendance rules that start
 * from the state's rules (screen 62).
 */
export function ApprovalDialog({
    mode,
    options,
    onClose,
}: {
    mode: DialogMode | null;
    options: ApprovalOptions;
    onClose: () => void;
}) {
    const { t } = useTranslation();
    const approval = mode && mode.kind !== 'add' ? mode.approval : null;
    const title =
        mode === null
            ? ''
            : mode.kind === 'add'
              ? t('approvals.form.addTitle')
              : t(
                    mode.kind === 'renew'
                        ? 'approvals.form.renewTitle'
                        : 'approvals.form.editTitle',
                    {
                        course: mode.approval.course.title,
                        state: mode.approval.state_code,
                    },
                );

    return (
        <Dialog
            open={mode !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>
                        {t('approvals.form.intro')}
                    </DialogDescription>
                </DialogHeader>
                {mode ? (
                    <ApprovalForm
                        key={approval ? `${mode.kind}-${approval.id}` : 'add'}
                        mode={mode}
                        options={options}
                        onDone={onClose}
                    />
                ) : null}
            </DialogContent>
        </Dialog>
    );
}

function ApprovalForm({
    mode,
    options,
    onDone,
}: {
    mode: DialogMode;
    options: ApprovalOptions;
    onDone: () => void;
}) {
    const { t } = useTranslation();
    const approval = mode.kind === 'add' ? null : mode.approval;
    const firstCourse =
        options.courses.find(
            (c) => mode.kind === 'add' && c.id === mode.courseId,
        ) ?? options.courses[0];
    const seat = (from: SeatTime | undefined) =>
        Object.fromEntries(
            SEAT_FIELDS.map((key) => [key, text(from?.[key])]),
        ) as Record<(typeof SEAT_FIELDS)[number], string>;
    const [draft, setDraft] = useState<Draft>(() => ({
        course_id: text(approval?.course.id ?? firstCourse?.id),
        state_code: approval?.state_code ?? 'TX',
        delivery: approval?.delivery ?? firstCourse?.delivery ?? 'self_paced',
        number: approval?.number ?? '',
        hours: text(approval?.hours ?? firstCourse?.hours),
        approved_on: approval?.approved_on ?? '',
        expires_on: mode.kind === 'renew' ? '' : (approval?.expires_on ?? ''),
        status: approval?.status ?? 'approved',
        note: approval?.note ?? '',
        records_live: approval?.records_live ?? false,
        audio_allowed: approval?.audio_allowed ?? false,
        ...seat(approval ?? options.defaults.TX),
    }));
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const set = (patch: Partial<Draft>) =>
        setDraft((d) => ({ ...d, ...patch }));

    const pickState = (code: string) => {
        set({ state_code: code });
        const known = options.defaults[code];

        if (known) {
            set(seat(known));

            return;
        }

        void fetch(defaults({ query: { state: code } }).url, {
            headers: { Accept: 'application/json' },
        })
            .then((r) => (r.ok ? (r.json() as Promise<SeatTime>) : null))
            .then((rules) => rules && set(seat(rules)));
    };

    const submit = () => {
        const numbers = Object.fromEntries(
            ['hours', ...SEAT_FIELDS].map((key) => [
                key,
                draft[key as keyof Draft] === ''
                    ? null
                    : Number(draft[key as keyof Draft]),
            ]),
        );
        const payload = {
            number: draft.number,
            approved_on: draft.approved_on,
            expires_on: draft.expires_on,
            status: draft.status,
            note: draft.note,
            records_live: draft.records_live,
            audio_allowed: draft.audio_allowed,
            ...numbers,
        };
        const callbacks = {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onError: setErrors,
            onSuccess: onDone,
        };

        if (approval) {
            router.patch(update(approval.id).url, payload, callbacks);
        } else {
            router.post(
                store().url,
                {
                    ...payload,
                    course_id: Number(draft.course_id),
                    state_code: draft.state_code,
                    delivery: draft.delivery,
                },
                callbacks,
            );
        }
    };

    const input = (
        key: Exclude<keyof Draft, 'records_live' | 'audio_allowed'>,
        label: string,
        type: 'text' | 'number' | 'date' = 'text',
    ) => (
        <div className="grid gap-2">
            <Label htmlFor={`approval-${key}`}>{label}</Label>
            <Input
                id={`approval-${key}`}
                type={type}
                value={draft[key]}
                min={type === 'number' ? 0 : undefined}
                step={type === 'number' ? 'any' : undefined}
                maxLength={type === 'text' ? 60 : undefined}
                onChange={(e) =>
                    set({ [key]: e.target.value } as Partial<Draft>)
                }
            />
            <InputError message={errors[key]} />
        </div>
    );

    return (
        <form
            className="space-y-4"
            onSubmit={(e) => {
                e.preventDefault();
                submit();
            }}
        >
            {approval === null ? (
                <div className="grid gap-4 sm:grid-cols-3">
                    <div className="grid gap-2 sm:col-span-3">
                        <Label htmlFor="approval-course">
                            {t('approvals.form.course')}
                        </Label>
                        <select
                            id="approval-course"
                            className={nativeSelect}
                            value={draft.course_id}
                            onChange={(e) => {
                                const course = options.courses.find(
                                    (c) => String(c.id) === e.target.value,
                                );
                                set({
                                    course_id: e.target.value,
                                    delivery:
                                        course?.delivery ?? draft.delivery,
                                    hours: text(course?.hours),
                                });
                            }}
                        >
                            {options.courses.map((course) => (
                                <option key={course.id} value={course.id}>
                                    {course.title}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.course_id} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="approval-state">
                            {t('approvals.form.state')}
                        </Label>
                        <select
                            id="approval-state"
                            className={nativeSelect}
                            value={draft.state_code}
                            onChange={(e) => pickState(e.target.value)}
                        >
                            {options.states.map((state) => (
                                <option key={state.code} value={state.code}>
                                    {state.name}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.state_code} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="approval-delivery">
                            {t('approvals.form.delivery')}
                        </Label>
                        <select
                            id="approval-delivery"
                            className={nativeSelect}
                            value={draft.delivery}
                            onChange={(e) =>
                                set({ delivery: e.target.value as Delivery })
                            }
                        >
                            {options.deliveries.map((d) => (
                                <option key={d} value={d}>
                                    {t(`approvals.deliveries.${d}`)}
                                </option>
                            ))}
                        </select>
                    </div>
                </div>
            ) : null}

            <div className="grid gap-4 sm:grid-cols-2">
                {input('number', t('approvals.form.number'))}
                {input('hours', t('approvals.form.hours'), 'number')}
                {input('approved_on', t('approvals.form.approvedOn'), 'date')}
                {input('expires_on', t('approvals.form.expiresOn'), 'date')}
            </div>

            <div className="grid gap-2">
                <Label htmlFor="approval-status">
                    {t('approvals.form.status')}
                </Label>
                <select
                    id="approval-status"
                    className={nativeSelect}
                    value={draft.status}
                    onChange={(e) =>
                        set({ status: e.target.value as ApprovalStatus })
                    }
                >
                    {(['approved', 'pending', 'withdrawn'] as const).map(
                        (s) => (
                            <option key={s} value={s}>
                                {t(`approvals.statuses.${s}`)}
                            </option>
                        ),
                    )}
                </select>
                <InputError message={errors.status} />
            </div>

            <fieldset className="space-y-3 rounded-reos-md border border-border p-4">
                <legend className="px-1 font-semibold">
                    {t('approvals.form.seatTime')}
                </legend>
                <p className="text-sm text-muted-foreground">
                    {t('approvals.form.seatTimeHint')}
                </p>
                <div className="grid gap-4 sm:grid-cols-2">
                    {input(
                        'max_hours_per_day',
                        t('approvals.form.maxHoursPerDay'),
                        'number',
                    )}
                    {input(
                        'checkin_minutes',
                        t('approvals.form.checkinMinutes'),
                        'number',
                    )}
                    {input(
                        'presence_percent',
                        t('approvals.form.presencePercent'),
                        'number',
                    )}
                    {input(
                        'makeup_min_percent',
                        t('approvals.form.makeupMinPercent'),
                        'number',
                    )}
                    {input(
                        'makeup_days',
                        t('approvals.form.makeupDays'),
                        'number',
                    )}
                </div>
                {/* Live delivery (A10.2c): whether live classes may be recorded for the course. */}
                {draft.delivery === 'live_online' ||
                draft.delivery === 'classroom' ? (
                    <label
                        htmlFor="approval-records-live"
                        className="flex min-h-12 items-start gap-3 text-base"
                    >
                        <Checkbox
                            id="approval-records-live"
                            className="mt-1"
                            checked={draft.records_live}
                            onCheckedChange={(on) =>
                                set({ records_live: on === true })
                            }
                        />
                        <span>
                            {t('approvals.form.recordsLive')}
                            <span className="block text-sm text-muted-foreground">
                                {t('approvals.form.recordsLiveHint')}
                            </span>
                        </span>
                    </label>
                ) : null}
                {/* Audio-only study (A14.2): the school confirms the regulator accepts it as seat time. */}
                <label
                    htmlFor="approval-audio-allowed"
                    className="flex min-h-12 items-start gap-3 text-base"
                >
                    <Checkbox
                        id="approval-audio-allowed"
                        className="mt-1"
                        checked={draft.audio_allowed}
                        onCheckedChange={(on) =>
                            set({ audio_allowed: on === true })
                        }
                    />
                    <span>
                        {t('approvals.form.audioAllowed')}
                        <span className="block text-sm text-muted-foreground">
                            {t('approvals.form.audioAllowedHint')}
                        </span>
                    </span>
                </label>
            </fieldset>

            <div className="grid gap-2">
                <Label htmlFor="approval-note">
                    {t('approvals.form.note')}
                </Label>
                <Textarea
                    id="approval-note"
                    value={draft.note}
                    maxLength={500}
                    onChange={(e) => set({ note: e.target.value })}
                />
                <InputError message={errors.note} />
            </div>

            <DialogFooter>
                <Button type="button" variant="outline" onClick={onDone}>
                    {t('approvals.form.cancel')}
                </Button>
                <Button type="submit" disabled={processing}>
                    {t('approvals.form.save')}
                </Button>
            </DialogFooter>
        </form>
    );
}
