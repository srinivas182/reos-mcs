import { Plus, X } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
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
import type { DateKind, DealFull, DealSide, DealStage } from './types';

export type DealValues = {
    property: string;
    city: string;
    side: DealSide;
    stage: DealStage;
    price: string;
    commission: string;
    parties: { name: string; role: string; email: string; phone: string }[];
    dates: { kind: DateKind; label: string; due_on: string; done: boolean }[];
};

const KINDS: DateKind[] = [
    'option_period',
    'financing',
    'appraisal',
    'inspection',
    'survey',
    'title_commitment',
    'closing',
    'other',
];
const SIDES: DealSide[] = ['buy', 'sell', 'lease', 'both'];
const STAGES: DealStage[] = ['active', 'under_contract', 'closed', 'cancelled'];

export function valuesOf(deal: DealFull | null): DealValues {
    return deal
        ? {
              property: deal.property,
              city: deal.city ?? '',
              side: deal.side,
              stage: deal.stage,
              price: deal.price === null ? '' : String(deal.price),
              commission: deal.commission ?? '',
              parties: deal.parties.map((p) => ({
                  name: p.name,
                  role: p.role,
                  email: p.email ?? '',
                  phone: p.phone ?? '',
              })),
              dates: deal.dates.map((d) => ({
                  kind: d.kind,
                  label: d.kind === 'other' ? d.name : '',
                  due_on: d.due_on,
                  done: d.done,
              })),
          }
        : {
              property: '',
              city: '',
              side: 'buy',
              stage: 'active',
              price: '',
              commission: '',
              parties: [],
              dates: [],
          };
}

/** The request body for a deal tracked in REOS. */
export function bodyOf(v: DealValues) {
    return {
        property: v.property,
        city: v.city || null,
        side: v.side,
        stage: v.stage,
        price: v.price === '' ? null : Number(v.price),
        commission: v.commission === '' ? null : Number(v.commission),
        parties: v.parties
            .filter((p) => p.name.trim() !== '')
            .map((p) => ({
                name: p.name,
                role: p.role || '—',
                email: p.email || null,
                phone: p.phone || null,
            })),
        dates: v.dates
            .filter((d) => d.due_on !== '')
            .map((d) => ({
                kind: d.kind,
                label: d.label || null,
                due_on: d.due_on,
                done: d.done,
            })),
    };
}

/** Add or edit a deal tracked in REOS (screen 14): each key date becomes a to-do on that day. */
export function DealFormDialog({
    open,
    initial,
    error,
    onOpenChange,
    onSave,
}: {
    open: boolean;
    initial: DealFull | null;
    error: string | null;
    onOpenChange: (open: boolean) => void;
    onSave: (values: DealValues) => void;
}) {
    const { t } = useTranslation();
    const [v, setV] = useState<DealValues>(() => valuesOf(initial));
    const set = <K extends keyof DealValues>(key: K, value: DealValues[K]) =>
        setV((all) => ({ ...all, [key]: value }));

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {initial ? t('deals.edit') : t('deals.add')}
                    </DialogTitle>
                    <DialogDescription>
                        {t('deals.description')}
                    </DialogDescription>
                </DialogHeader>
                <form
                    id="deal-form"
                    className="grid gap-4 sm:grid-cols-2"
                    onSubmit={(e) => {
                        e.preventDefault();
                        onSave(v);
                    }}
                >
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="deal-property">
                            {t('deals.fields.property')}
                        </Label>
                        <Input
                            id="deal-property"
                            required
                            value={v.property}
                            onChange={(e) => set('property', e.target.value)}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="deal-city">
                            {t('deals.fields.city')}
                        </Label>
                        <Input
                            id="deal-city"
                            value={v.city}
                            onChange={(e) => set('city', e.target.value)}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="deal-side">
                            {t('deals.fields.side')}
                        </Label>
                        <select
                            id="deal-side"
                            className={nativeSelect}
                            value={v.side}
                            onChange={(e) =>
                                set('side', e.target.value as DealSide)
                            }
                        >
                            {SIDES.map((s) => (
                                <option key={s} value={s}>
                                    {t(`deals.sides.${s}`)}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="deal-stage">
                            {t('deals.fields.stage')}
                        </Label>
                        <select
                            id="deal-stage"
                            className={nativeSelect}
                            value={v.stage}
                            onChange={(e) =>
                                set('stage', e.target.value as DealStage)
                            }
                        >
                            {STAGES.map((s) => (
                                <option key={s} value={s}>
                                    {t(`deals.stages.${s}`)}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="deal-price">
                            {t('deals.fields.price')}
                        </Label>
                        <Input
                            id="deal-price"
                            inputMode="numeric"
                            value={v.price}
                            onChange={(e) =>
                                set(
                                    'price',
                                    e.target.value.replace(/[^0-9]/g, ''),
                                )
                            }
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="deal-commission">
                            {t('deals.fields.commission')}
                        </Label>
                        <Input
                            id="deal-commission"
                            inputMode="decimal"
                            value={v.commission}
                            onChange={(e) =>
                                set(
                                    'commission',
                                    e.target.value.replace(/[^0-9.]/g, ''),
                                )
                            }
                        />
                    </div>

                    <fieldset className="space-y-3 sm:col-span-2">
                        <legend className="font-semibold">
                            {t('deals.fields.dates')}
                        </legend>
                        {v.dates.map((d, i) => (
                            <div
                                key={i}
                                className="grid items-end gap-2 sm:grid-cols-[1fr_1fr_auto]"
                            >
                                <label className="grid gap-2 text-sm font-medium">
                                    {t('deals.fields.kind')}
                                    <select
                                        className={nativeSelect}
                                        value={d.kind}
                                        onChange={(e) =>
                                            set(
                                                'dates',
                                                v.dates.map((x, j) =>
                                                    j === i
                                                        ? {
                                                              ...x,
                                                              kind: e.target
                                                                  .value as DateKind,
                                                          }
                                                        : x,
                                                ),
                                            )
                                        }
                                    >
                                        {KINDS.map((k) => (
                                            <option key={k} value={k}>
                                                {t(`deals.kinds.${k}`)}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                                <label className="grid gap-2 text-sm font-medium">
                                    {t('deals.fields.dueOn')}
                                    <Input
                                        type="date"
                                        value={d.due_on}
                                        onChange={(e) =>
                                            set(
                                                'dates',
                                                v.dates.map((x, j) =>
                                                    j === i
                                                        ? {
                                                              ...x,
                                                              due_on: e.target
                                                                  .value,
                                                          }
                                                        : x,
                                                ),
                                            )
                                        }
                                    />
                                </label>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    aria-label={t('deals.fields.remove')}
                                    onClick={() =>
                                        set(
                                            'dates',
                                            v.dates.filter((_, j) => j !== i),
                                        )
                                    }
                                >
                                    <X aria-hidden />
                                </Button>
                            </div>
                        ))}
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                set('dates', [
                                    ...v.dates,
                                    {
                                        kind: 'closing',
                                        label: '',
                                        due_on: '',
                                        done: false,
                                    },
                                ])
                            }
                        >
                            <Plus aria-hidden />
                            {t('deals.fields.addDate')}
                        </Button>
                    </fieldset>

                    <fieldset className="space-y-3 sm:col-span-2">
                        <legend className="font-semibold">
                            {t('deals.fields.parties')}
                        </legend>
                        {v.parties.map((p, i) => (
                            <div
                                key={i}
                                className="grid items-end gap-2 sm:grid-cols-[1fr_1fr_auto]"
                            >
                                <label className="grid gap-2 text-sm font-medium">
                                    {t('deals.fields.name')}
                                    <Input
                                        value={p.name}
                                        onChange={(e) =>
                                            set(
                                                'parties',
                                                v.parties.map((x, j) =>
                                                    j === i
                                                        ? {
                                                              ...x,
                                                              name: e.target
                                                                  .value,
                                                          }
                                                        : x,
                                                ),
                                            )
                                        }
                                    />
                                </label>
                                <label className="grid gap-2 text-sm font-medium">
                                    {t('deals.fields.role')}
                                    <Input
                                        value={p.role}
                                        onChange={(e) =>
                                            set(
                                                'parties',
                                                v.parties.map((x, j) =>
                                                    j === i
                                                        ? {
                                                              ...x,
                                                              role: e.target
                                                                  .value,
                                                          }
                                                        : x,
                                                ),
                                            )
                                        }
                                    />
                                </label>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    aria-label={t('deals.fields.remove')}
                                    onClick={() =>
                                        set(
                                            'parties',
                                            v.parties.filter((_, j) => j !== i),
                                        )
                                    }
                                >
                                    <X aria-hidden />
                                </Button>
                            </div>
                        ))}
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                set('parties', [
                                    ...v.parties,
                                    {
                                        name: '',
                                        role: '',
                                        email: '',
                                        phone: '',
                                    },
                                ])
                            }
                        >
                            <Plus aria-hidden />
                            {t('deals.fields.addParty')}
                        </Button>
                    </fieldset>
                </form>
                {error ? (
                    <p role="alert" className="text-body text-destructive">
                        {error}
                    </p>
                ) : null}
                <DialogFooter>
                    <Button
                        type="submit"
                        form="deal-form"
                        disabled={v.property.trim().length < 3}
                    >
                        {t('deals.save')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
