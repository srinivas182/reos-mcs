import { router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { refundPolicy } from '@/routes/gateways';

export type RefundPolicy = {
    mode: 'full_and_partial' | 'full_only' | 'none';
    window_days: number;
    max_progress_percent: number;
};

const MODES = ['full_and_partial', 'full_only', 'none'] as const;

/**
 * The refund policy (screen 66, B5.3): whether REOS refunds in full and in
 * part, in full only, or not at all, the window in days and how much of a
 * course may be done (checked once the course player tracks progress).
 */
export function RefundPolicyCard({ policy }: { policy: RefundPolicy }) {
    const { t } = useTranslation();
    const [draft, setDraft] = useState(policy);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    return (
        <form
            aria-labelledby="refund-policy"
            className="space-y-3 rounded-reos-md border border-border p-4"
            onSubmit={(e) => {
                e.preventDefault();
                router.put(refundPolicy().url, draft, {
                    preserveScroll: true,
                    onStart: () => setProcessing(true),
                    onFinish: () => setProcessing(false),
                    onError: setErrors,
                });
            }}
        >
            <h3 id="refund-policy" className="font-semibold">
                {t('billing.refunds.policyTitle')}
            </h3>
            <fieldset className="grid gap-2 sm:grid-cols-3">
                <legend className="sr-only">
                    {t('billing.refunds.policyTitle')}
                </legend>
                {MODES.map((mode) => (
                    <label
                        key={mode}
                        className="flex min-h-12 items-center gap-3 rounded-reos-md border border-input px-3"
                    >
                        <input
                            type="radio"
                            name="refund-mode"
                            value={mode}
                            checked={draft.mode === mode}
                            onChange={() => setDraft({ ...draft, mode })}
                            className="size-5 accent-primary"
                        />
                        {t(`billing.refunds.modes.${mode}`)}
                    </label>
                ))}
            </fieldset>
            {draft.mode !== 'none' ? (
                <div className="grid gap-3 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="refund-window">
                            {t('billing.refunds.windowDays')}
                        </Label>
                        <Input
                            id="refund-window"
                            type="number"
                            min={0}
                            max={365}
                            value={draft.window_days}
                            onChange={(e) =>
                                setDraft({
                                    ...draft,
                                    window_days: Number(e.target.value),
                                })
                            }
                        />
                        <InputError message={errors.window_days} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="refund-progress">
                            {t('billing.refunds.maxProgress')}
                        </Label>
                        <Input
                            id="refund-progress"
                            type="number"
                            min={0}
                            max={100}
                            value={draft.max_progress_percent}
                            onChange={(e) =>
                                setDraft({
                                    ...draft,
                                    max_progress_percent: Number(
                                        e.target.value,
                                    ),
                                })
                            }
                            aria-describedby="refund-progress-help"
                        />
                        <p
                            id="refund-progress-help"
                            className="text-sm text-muted-foreground"
                        >
                            {t('billing.refunds.progressNote')}
                        </p>
                        <InputError message={errors.max_progress_percent} />
                    </div>
                </div>
            ) : (
                <p className="text-sm text-muted-foreground">
                    {t('billing.refunds.noneHelp')}
                </p>
            )}
            <Button type="submit" variant="outline" disabled={processing}>
                {t('billing.refunds.savePolicy')}
            </Button>
        </form>
    );
}
