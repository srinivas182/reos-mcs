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
import { rollout } from '@/routes/features';
import { store as storeRelease } from '@/routes/releases';
import type { RoleName } from '@/types/auth';

export type RolloutStage =
    | 'off'
    | 'internal'
    | 'tenants'
    | 'states'
    | 'percent'
    | 'everyone';

export type FlagRow = {
    key: string;
    name: string;
    description: string | null;
    owner: string | null;
    tenant_feature: boolean;
    stage: RolloutStage;
    tenants: { id: number; name: string }[];
    states: string[];
    percent: number;
    roles: RoleName[] | null;
    error_threshold: number;
    stage_changed_at: string | null;
    killed_at: string | null;
    rolled_back_at: string | null;
    rolled_back_from: RolloutStage | null;
    uses: number;
    errors: number;
};

export type FlagOptions = {
    stages: RolloutStage[];
    roles: RoleName[];
    states: { code: string; name: string }[];
    tenants: { id: number; name: string; type: string }[];
    window_minutes: number;
};

const ORDER: RolloutStage[] = [
    'off',
    'internal',
    'tenants',
    'states',
    'percent',
    'everyone',
];

/** Whether a stage includes another (each step keeps the ones before it). */
export function includes(stage: RolloutStage, step: RolloutStage): boolean {
    return ORDER.indexOf(stage) >= ORDER.indexOf(step);
}

/** A labelled list of checkboxes (tenants, states, roles, flags). */
function Checklist<T extends string | number>({
    id,
    label,
    hint,
    items,
    selected,
    onChange,
    empty,
}: {
    id: string;
    label: string;
    hint?: string;
    items: { value: T; label: string }[];
    selected: T[];
    onChange: (next: T[]) => void;
    empty?: string;
}) {
    return (
        <fieldset className="grid gap-2">
            <legend className="text-sm font-medium">{label}</legend>
            {hint ? (
                <p className="text-sm text-muted-foreground">{hint}</p>
            ) : null}
            {items.length === 0 ? (
                <p className="text-body text-muted-foreground">{empty}</p>
            ) : (
                <div className="grid max-h-52 gap-1 overflow-y-auto rounded-reos-md border border-border p-2 sm:grid-cols-2">
                    {items.map((item) => {
                        const inputId = `${id}-${item.value}`;

                        return (
                            <label
                                key={item.value}
                                htmlFor={inputId}
                                className="flex min-h-12 items-center gap-3 rounded-reos-sm px-2 text-body hover:bg-accent"
                            >
                                <Checkbox
                                    id={inputId}
                                    checked={selected.includes(item.value)}
                                    onCheckedChange={(on) =>
                                        onChange(
                                            on === true
                                                ? [...selected, item.value]
                                                : selected.filter(
                                                      (v) => v !== item.value,
                                                  ),
                                        )
                                    }
                                />
                                {item.label}
                            </label>
                        );
                    })}
                </div>
            )}
        </fieldset>
    );
}

/**
 * Change rollout (screen 70, A6.2): the step (REOS staff → named tenants →
 * states → a share of tenants → everyone), who is in each step, which roles
 * see it and the error level that steps it back on its own.
 */
export function RolloutDialog({
    flag,
    options,
    onOpenChange,
}: {
    flag: FlagRow | null;
    options: FlagOptions;
    onOpenChange: (open: boolean) => void;
}) {
    const { t } = useTranslation();

    return (
        <Dialog open={flag !== null} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {t('features.dialog.title', { name: flag?.name ?? '' })}
                    </DialogTitle>
                    <DialogDescription>
                        {t('features.dialog.description')}
                    </DialogDescription>
                </DialogHeader>
                {flag ? (
                    <RolloutForm
                        key={flag.key}
                        flag={flag}
                        options={options}
                        onDone={() => onOpenChange(false)}
                    />
                ) : null}
            </DialogContent>
        </Dialog>
    );
}

function RolloutForm({
    flag,
    options,
    onDone,
}: {
    flag: FlagRow;
    options: FlagOptions;
    onDone: () => void;
}) {
    const { t } = useTranslation();
    const [stage, setStage] = useState<RolloutStage>(flag.stage);
    const [tenants, setTenants] = useState<number[]>(
        flag.tenants.map((x) => x.id),
    );
    const [states, setStates] = useState<string[]>(flag.states);
    const [percent, setPercent] = useState(String(flag.percent));
    const [roles, setRoles] = useState<RoleName[]>(flag.roles ?? []);
    const [threshold, setThreshold] = useState(String(flag.error_threshold));
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const submit = () => {
        router.put(
            rollout(flag.key).url,
            {
                stage,
                tenant_ids: tenants,
                states,
                percent: Number(percent),
                roles,
                error_threshold: Number(threshold),
            },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onError: setErrors,
                onSuccess: onDone,
            },
        );
    };

    return (
        <form
            className="space-y-5"
            onSubmit={(e) => {
                e.preventDefault();
                submit();
            }}
        >
            <fieldset className="grid gap-2">
                <legend className="text-sm font-medium">
                    {t('features.dialog.stage')}
                </legend>
                <div className="grid gap-2">
                    {options.stages.map((value) => (
                        <label
                            key={value}
                            className="flex min-h-12 cursor-pointer items-start gap-3 rounded-reos-md border border-border p-3 has-[:checked]:border-primary has-[:checked]:bg-accent"
                        >
                            <input
                                type="radio"
                                name="stage"
                                value={value}
                                checked={stage === value}
                                onChange={() => setStage(value)}
                                className="mt-1 size-5 accent-primary"
                            />
                            <span>
                                <span className="block font-medium">
                                    {t(`features.stages.${value}`)}
                                </span>
                                <span className="block text-sm text-muted-foreground">
                                    {t(`features.stageHints.${value}`)}
                                </span>
                            </span>
                        </label>
                    ))}
                </div>
                <InputError message={errors.stage} />
            </fieldset>

            {includes(stage, 'tenants') && stage !== 'everyone' ? (
                <div className="space-y-1">
                    <Checklist
                        id="rollout-tenant"
                        label={t('features.dialog.tenants')}
                        hint={t('features.dialog.tenantsHint')}
                        empty={t('features.dialog.noTenants')}
                        items={options.tenants.map((x) => ({
                            value: x.id,
                            label: x.name,
                        }))}
                        selected={tenants}
                        onChange={setTenants}
                    />
                    <InputError message={errors.tenant_ids} />
                </div>
            ) : null}

            {includes(stage, 'states') && stage !== 'everyone' ? (
                <div className="space-y-1">
                    <Checklist
                        id="rollout-state"
                        label={t('features.dialog.states')}
                        items={options.states.map((s) => ({
                            value: s.code,
                            label: s.name,
                        }))}
                        selected={states}
                        onChange={setStates}
                    />
                    <InputError message={errors.states} />
                </div>
            ) : null}

            {stage === 'percent' ? (
                <div className="grid gap-2">
                    <Label htmlFor="rollout-percent">
                        {t('features.dialog.percent')}
                    </Label>
                    <Input
                        id="rollout-percent"
                        type="number"
                        min={0}
                        max={100}
                        inputMode="numeric"
                        value={percent}
                        onChange={(e) => setPercent(e.target.value)}
                        aria-describedby="rollout-percent-hint"
                        className="max-w-40"
                    />
                    <p
                        id="rollout-percent-hint"
                        className="text-sm text-muted-foreground"
                    >
                        {t('features.dialog.percentHint')}
                    </p>
                    <InputError message={errors.percent} />
                </div>
            ) : null}

            <Checklist
                id="rollout-role"
                label={t('features.dialog.roles')}
                hint={t('features.dialog.rolesHint')}
                items={options.roles.map((role) => ({
                    value: role,
                    label: t(`roles.names.${role}`),
                }))}
                selected={roles}
                onChange={setRoles}
            />

            <div className="grid gap-2">
                <Label htmlFor="rollout-threshold">
                    {t('features.dialog.threshold')}
                </Label>
                <Input
                    id="rollout-threshold"
                    type="number"
                    min={1}
                    max={100}
                    inputMode="numeric"
                    value={threshold}
                    onChange={(e) => setThreshold(e.target.value)}
                    aria-describedby="rollout-threshold-hint"
                    className="max-w-40"
                />
                <p
                    id="rollout-threshold-hint"
                    className="text-sm text-muted-foreground"
                >
                    {t('features.dialog.thresholdHint', {
                        minutes: options.window_minutes,
                    })}
                </p>
                <InputError message={errors.error_threshold} />
            </div>

            <DialogFooter>
                <Button type="button" variant="outline" onClick={onDone}>
                    {t('features.dialog.cancel')}
                </Button>
                <Button type="submit" disabled={processing}>
                    {t('features.dialog.save')}
                </Button>
            </DialogFooter>
        </form>
    );
}

/**
 * New release (screen 70, A6.2): version and date, a plain-words note per
 * role ("What's new", shown once on the web and the phone) and the flags it
 * rolls out to everyone.
 */
export function ReleaseDialog({
    open,
    flags,
    roles,
    today,
    onOpenChange,
}: {
    open: boolean;
    flags: { key: string; name: string; stage: RolloutStage }[];
    roles: RoleName[];
    today: string;
    onOpenChange: (open: boolean) => void;
}) {
    const { t } = useTranslation();

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {t('features.releases.dialogTitle')}
                    </DialogTitle>
                    <DialogDescription>
                        {t('features.releases.dialogDescription')}
                    </DialogDescription>
                </DialogHeader>
                {open ? (
                    <ReleaseForm
                        flags={flags}
                        roles={roles}
                        today={today}
                        onDone={() => onOpenChange(false)}
                    />
                ) : null}
            </DialogContent>
        </Dialog>
    );
}

function ReleaseForm({
    flags,
    roles,
    today,
    onDone,
}: {
    flags: { key: string; name: string; stage: RolloutStage }[];
    roles: RoleName[];
    today: string;
    onDone: () => void;
}) {
    const { t } = useTranslation();
    const [version, setVersion] = useState('');
    const [date, setDate] = useState(today);
    const [notes, setNotes] = useState<Record<string, string>>({});
    const [chosen, setChosen] = useState<string[]>([]);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const submit = () => {
        router.post(
            storeRelease().url,
            { version, released_on: date, notes, flags: chosen },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onError: setErrors,
                onSuccess: onDone,
            },
        );
    };

    return (
        <form
            className="space-y-5"
            onSubmit={(e) => {
                e.preventDefault();
                submit();
            }}
        >
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="release-version">
                        {t('features.releases.version')}
                    </Label>
                    <Input
                        id="release-version"
                        value={version}
                        maxLength={32}
                        required
                        placeholder="6.2.0"
                        onChange={(e) => setVersion(e.target.value)}
                    />
                    <InputError message={errors.version} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="release-date">
                        {t('features.releases.date')}
                    </Label>
                    <Input
                        id="release-date"
                        type="date"
                        value={date}
                        required
                        onChange={(e) => setDate(e.target.value)}
                    />
                    <InputError message={errors.released_on} />
                </div>
            </div>

            <fieldset className="grid gap-3">
                <legend className="text-sm font-medium">
                    {t('features.releases.notes')}
                </legend>
                <p className="text-sm text-muted-foreground">
                    {t('features.releases.notesHint')}
                </p>
                {roles.map((role) => (
                    <div key={role} className="grid gap-2">
                        <Label htmlFor={`release-note-${role}`}>
                            {t('features.releases.noteFor', {
                                role: t(`roles.names.${role}`),
                            })}
                        </Label>
                        <Textarea
                            id={`release-note-${role}`}
                            rows={2}
                            maxLength={600}
                            value={notes[role] ?? ''}
                            onChange={(e) =>
                                setNotes({ ...notes, [role]: e.target.value })
                            }
                        />
                        <InputError message={errors[`notes.${role}`]} />
                    </div>
                ))}
                <InputError message={errors.notes} />
            </fieldset>

            <div className="space-y-1">
                <Checklist
                    id="release-flag"
                    label={t('features.releases.flags')}
                    items={flags
                        .filter((f) => f.stage !== 'everyone')
                        .map((f) => ({ value: f.key, label: f.name }))}
                    selected={chosen}
                    onChange={setChosen}
                    empty={t('features.releases.noFlags')}
                />
                <InputError message={errors.flags} />
            </div>

            <DialogFooter>
                <Button type="button" variant="outline" onClick={onDone}>
                    {t('features.dialog.cancel')}
                </Button>
                <Button type="submit" disabled={processing}>
                    {t('features.releases.publish')}
                </Button>
            </DialogFooter>
        </form>
    );
}
