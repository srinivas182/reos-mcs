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
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { report } from '@/routes/messages';

const REASONS = ['spam', 'harassment', 'inappropriate', 'other'] as const;

/**
 * Report a conversation (B6.1b): why, optional details, who (in a group),
 * and whether to block them too. A copy of their recent messages goes to
 * the organization's admins and REOS.
 */
export function ReportDialog({
    open,
    conversation,
    people,
    group,
    onOpenChange,
}: {
    open: boolean;
    conversation: string;
    people: { id: number; name: string }[];
    group: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { t } = useTranslation();
    const [reason, setReason] =
        useState<(typeof REASONS)[number]>('harassment');
    const [details, setDetails] = useState('');
    const [who, setWho] = useState<number | null>(
        group ? (people[0]?.id ?? null) : null,
    );
    const [block, setBlock] = useState(true);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);

    const submit = () =>
        router.post(
            report(conversation).url,
            { reason, details: details || null, user: who, block },
            {
                preserveScroll: true,
                onStart: () => setBusy(true),
                onFinish: () => setBusy(false),
                onError: setErrors,
                onSuccess: () => onOpenChange(false),
            },
        );

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{t('messages.report.title')}</DialogTitle>
                    <DialogDescription>
                        {t('messages.report.description')}
                    </DialogDescription>
                </DialogHeader>
                <form
                    className="space-y-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        submit();
                    }}
                >
                    {group ? (
                        <fieldset className="grid gap-2">
                            <legend className="text-sm font-medium">
                                {t('messages.report.who')}
                            </legend>
                            {people.map((person) => (
                                <label
                                    key={person.id}
                                    className="flex min-h-12 items-center gap-3 text-body"
                                >
                                    <input
                                        type="radio"
                                        name="report-who"
                                        checked={who === person.id}
                                        onChange={() => setWho(person.id)}
                                        className="size-5 accent-primary"
                                    />
                                    {person.name}
                                </label>
                            ))}
                        </fieldset>
                    ) : null}
                    <fieldset className="grid gap-2">
                        <legend className="text-sm font-medium">
                            {t('messages.report.why')}
                        </legend>
                        {REASONS.map((value) => (
                            <label
                                key={value}
                                className="flex min-h-12 items-center gap-3 text-body"
                            >
                                <input
                                    type="radio"
                                    name="report-reason"
                                    checked={reason === value}
                                    onChange={() => setReason(value)}
                                    className="size-5 accent-primary"
                                />
                                {t(`messages.report.reasons.${value}`)}
                            </label>
                        ))}
                        <InputError message={errors.reason} />
                    </fieldset>
                    <div className="grid gap-2">
                        <Label htmlFor="report-details">
                            {t('messages.report.details')}
                        </Label>
                        <Textarea
                            id="report-details"
                            rows={3}
                            maxLength={1000}
                            value={details}
                            onChange={(e) => setDetails(e.target.value)}
                        />
                    </div>
                    <label
                        htmlFor="report-block"
                        className="flex min-h-12 items-center gap-3 text-body"
                    >
                        <Checkbox
                            id="report-block"
                            checked={block}
                            onCheckedChange={(on) => setBlock(on === true)}
                        />
                        {t('messages.report.alsoBlock')}
                    </label>
                    <InputError message={errors.user} />
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            {t('messages.cancel')}
                        </Button>
                        <Button
                            type="submit"
                            variant="destructive"
                            disabled={busy}
                        >
                            {t('messages.report.send')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
