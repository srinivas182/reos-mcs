import { Form, Link, router } from '@inertiajs/react';
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
import { nativeSelect } from '@/lib/tenants';
import { checklist, prepare } from '@/routes/states';
import { show } from '@/routes/states/rules';

export type ChecklistItem =
    | 'rules'
    | 'approvals'
    | 'advertising'
    | 'plans'
    | 'integrations'
    | 'legal';

export type LaunchState = {
    code: string;
    name: string;
    status: string;
    regulator: string | null;
    exam_vendor: string | null;
    qualifying_hours: number | null;
    ce_hours: number | null;
    checklist: Record<string, boolean>;
};

/**
 * The launch checklist for a state being prepared (screen 61, A5.1). Each
 * item is ticked by a super admin (audited); "Live" stays closed until every
 * item is done.
 */
export function LaunchChecklist({
    state,
    items,
}: {
    state: LaunchState;
    items: ChecklistItem[];
}) {
    const { t } = useTranslation();
    const done = items.filter((item) => state.checklist[item]).length;

    return (
        <section
            aria-labelledby="launch-checklist"
            className="space-y-3 rounded-reos-lg border border-border bg-card p-5"
        >
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <h2 id="launch-checklist" className="font-semibold">
                    {t('states.checklist.title', { state: state.name })}
                </h2>
                <p className="text-sm text-muted-foreground" aria-live="polite">
                    {t('states.checklist.progress', {
                        done,
                        total: items.length,
                    })}
                </p>
            </div>
            <ul className="space-y-1">
                {items.map((item) => (
                    <li key={item}>
                        <label className="flex min-h-12 items-center gap-3 text-body">
                            <Checkbox
                                checked={state.checklist[item] ?? false}
                                onCheckedChange={(on) =>
                                    router.put(
                                        checklist(state.code).url,
                                        { item, done: on === true },
                                        { preserveScroll: true },
                                    )
                                }
                            />
                            {t(`states.checklist.items.${item}`, {
                                state: state.name,
                            })}
                        </label>
                    </li>
                ))}
            </ul>
            {done < items.length ? (
                <p className="text-body text-muted-foreground">
                    {t('states.checklist.beforeLive')}
                </p>
            ) : null}
            <Link
                href={show(state.code)}
                className="inline-flex min-h-12 items-center font-medium text-primary underline-offset-4 hover:underline"
            >
                {t('states.checklist.openRules', { state: state.name })}
            </Link>
        </section>
    );
}

/** "Add state" (screen 61): start preparing a state with its regulator, exam vendor and hours. */
export function AddStateDialog({
    open,
    onOpenChange,
    states,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    states: LaunchState[];
}) {
    const { t } = useTranslation();
    const [code, setCode] = useState('');
    const state = states.find((s) => s.code === code);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{t('states.add.title')}</DialogTitle>
                    <DialogDescription>
                        {t('states.add.intro')}
                    </DialogDescription>
                </DialogHeader>
                <div className="grid gap-2">
                    <Label htmlFor="add-state">{t('states.add.state')}</Label>
                    <select
                        id="add-state"
                        className={nativeSelect}
                        value={code}
                        onChange={(e) => setCode(e.target.value)}
                    >
                        <option value="">{t('states.add.choose')}</option>
                        {states.map((s) => (
                            <option key={s.code} value={s.code}>
                                {s.name}
                            </option>
                        ))}
                    </select>
                </div>
                {state ? (
                    <Form
                        key={state.code}
                        {...prepare.form(state.code)}
                        options={{ preserveScroll: true }}
                        onSuccess={() => {
                            setCode('');
                            onOpenChange(false);
                        }}
                        className="space-y-4"
                    >
                        {({ errors, processing }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="add-regulator">
                                        {t('states.columns.regulator')}
                                    </Label>
                                    <Input
                                        id="add-regulator"
                                        name="regulator"
                                        required
                                        maxLength={120}
                                        defaultValue={state.regulator ?? ''}
                                    />
                                    <InputError message={errors.regulator} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="add-vendor">
                                        {t('states.columns.examVendor')}
                                    </Label>
                                    <Input
                                        id="add-vendor"
                                        name="exam_vendor"
                                        maxLength={120}
                                        defaultValue={state.exam_vendor ?? ''}
                                    />
                                </div>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="add-qualifying">
                                            {t(
                                                'states.columns.qualifyingHours',
                                            )}
                                        </Label>
                                        <Input
                                            id="add-qualifying"
                                            name="qualifying_hours"
                                            type="number"
                                            min={0}
                                            max={2000}
                                            defaultValue={
                                                state.qualifying_hours ?? ''
                                            }
                                        />
                                        <InputError
                                            message={errors.qualifying_hours}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="add-ce">
                                            {t('states.columns.ceHours')}
                                        </Label>
                                        <Input
                                            id="add-ce"
                                            name="ce_hours"
                                            type="number"
                                            min={0}
                                            max={200}
                                            defaultValue={state.ce_hours ?? ''}
                                        />
                                        <InputError message={errors.ce_hours} />
                                    </div>
                                </div>
                                <DialogFooter>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => onOpenChange(false)}
                                    >
                                        {t('common.cancel')}
                                    </Button>
                                    <Button type="submit" disabled={processing}>
                                        {t('states.add.submit')}
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                ) : null}
            </DialogContent>
        </Dialog>
    );
}
