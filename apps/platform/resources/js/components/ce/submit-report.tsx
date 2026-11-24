import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Reporting } from './reporting-types';

export type SubmitValues = {
    from_on: string;
    to_on: string;
    method: 'file' | 'reos';
    authorised: boolean;
};

/**
 * Submit a CE report for a date range (A11.3, screen 51): TREC's upload
 * file, or REOS submits with the school's authority.
 */
export function SubmitReport({
    reos,
    today,
    onSubmit,
}: {
    reos: Reporting['reos'];
    today: string;
    onSubmit: (values: SubmitValues) => void;
}) {
    const { t } = useTranslation();
    const [values, setValues] = useState<SubmitValues>({
        from_on: `${today.slice(0, 8)}01`,
        to_on: today,
        method: 'file',
        authorised: false,
    });
    const canReos = reos !== 'unavailable';

    return (
        <form
            aria-labelledby="submit-report"
            className="space-y-4 rounded-reos-lg border border-border bg-card p-5"
            onSubmit={(e) => {
                e.preventDefault();
                onSubmit(values);
            }}
        >
            <h2 id="submit-report" className="text-lg font-semibold">
                {t('ce.reports.submit.title')}
            </h2>
            <div className="flex flex-wrap gap-4">
                <div className="space-y-2">
                    <Label htmlFor="report-from" className="text-base">
                        {t('ce.reports.submit.from')}
                    </Label>
                    <Input
                        id="report-from"
                        type="date"
                        max={values.to_on}
                        value={values.from_on}
                        onChange={(e) =>
                            setValues((v) => ({
                                ...v,
                                from_on: e.target.value,
                            }))
                        }
                    />
                </div>
                <div className="space-y-2">
                    <Label htmlFor="report-to" className="text-base">
                        {t('ce.reports.submit.to')}
                    </Label>
                    <Input
                        id="report-to"
                        type="date"
                        min={values.from_on}
                        max={today}
                        value={values.to_on}
                        onChange={(e) =>
                            setValues((v) => ({ ...v, to_on: e.target.value }))
                        }
                    />
                </div>
            </div>
            <fieldset className="space-y-3">
                <legend className="text-base font-medium">
                    {t('ce.reports.submit.method')}
                </legend>
                <label className="flex min-h-12 items-start gap-3 text-base">
                    <input
                        type="radio"
                        name="method"
                        className="mt-1 size-5"
                        checked={values.method === 'file'}
                        onChange={() =>
                            setValues((v) => ({ ...v, method: 'file' }))
                        }
                    />
                    <span>
                        <span className="font-semibold">
                            {t('ce.reports.submit.file')}
                        </span>
                        <span className="block text-muted-foreground">
                            {t('ce.reports.submit.fileHint')}
                        </span>
                    </span>
                </label>
                <label className="flex min-h-12 items-start gap-3 text-base">
                    <input
                        type="radio"
                        name="method"
                        className="mt-1 size-5"
                        disabled={!canReos}
                        checked={values.method === 'reos'}
                        onChange={() =>
                            setValues((v) => ({ ...v, method: 'reos' }))
                        }
                    />
                    <span>
                        <span className="font-semibold">
                            {t('ce.reports.submit.reos')}
                        </span>
                        <span className="block text-muted-foreground">
                            {reos === 'unavailable'
                                ? t('ce.reports.submit.reosUnavailable')
                                : t('ce.reports.submit.reosHint')}
                            {reos === 'simulated'
                                ? ` ${t('ce.reports.submit.reosSimulated')}`
                                : null}
                        </span>
                    </span>
                </label>
            </fieldset>
            {values.method === 'reos' ? (
                <div className="flex items-start gap-3">
                    <Checkbox
                        id="report-authorise"
                        checked={values.authorised}
                        onCheckedChange={(on) =>
                            setValues((v) => ({
                                ...v,
                                authorised: on === true,
                            }))
                        }
                    />
                    <Label
                        htmlFor="report-authorise"
                        className="text-base font-normal"
                    >
                        {t('ce.reports.submit.authorise')}
                    </Label>
                </div>
            ) : null}
            <p className="text-base text-muted-foreground">
                {t('ce.reports.logged')}
            </p>
            <Button
                type="submit"
                disabled={values.method === 'reos' && !values.authorised}
            >
                {t('ce.reports.submit.action')}
            </Button>
        </form>
    );
}
