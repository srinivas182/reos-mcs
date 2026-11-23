import { router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import type { RecruitCandidate } from '@/components/recruiting/types';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { offer as offerRoute } from '@/routes/recruiting';

/** Make or change an offer (A10.3b): office, the agent's split, fees, training, mentoring and a message. */
export function OfferDialog({
    candidate,
    onClose,
}: {
    candidate: RecruitCandidate | null;
    onClose: () => void;
}) {
    const { t } = useTranslation();
    const current = candidate?.offer;
    const [office, setOffice] = useState(
        current?.office_city ?? candidate?.city ?? '',
    );
    const [split, setSplit] = useState(String(current?.split_percent ?? 70));
    const [fee, setFee] = useState(
        String((current?.monthly_fee_cents ?? 0) / 100),
    );
    const [fees, setFees] = useState(current?.fees ?? '');
    const [training, setTraining] = useState(current?.training ?? '');
    const [mentoring, setMentoring] = useState(current?.mentoring ?? true);
    const [message, setMessage] = useState(current?.message ?? '');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);

    if (!candidate) {
        return null;
    }

    return (
        <Dialog open onOpenChange={(open) => (open ? null : onClose())}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>
                        {t('recruiting.form.title', {
                            name: candidate.first_name,
                        })}
                    </DialogTitle>
                </DialogHeader>
                <form
                    id="broker-offer"
                    className="space-y-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.put(
                            offerRoute(candidate.id).url,
                            {
                                office_city: office,
                                split_percent: Number(split),
                                monthly_fee_cents: Math.round(
                                    Number(fee) * 100,
                                ),
                                fees: fees || null,
                                training: training || null,
                                mentoring,
                                message: message || null,
                            },
                            {
                                preserveScroll: true,
                                onStart: () => setBusy(true),
                                onFinish: () => setBusy(false),
                                onError: setErrors,
                                onSuccess: onClose,
                            },
                        );
                    }}
                >
                    <div className="grid gap-4 sm:grid-cols-3">
                        <div className="grid gap-2">
                            <Label htmlFor="offer-office">
                                {t('recruiting.form.office')}
                            </Label>
                            <Input
                                id="offer-office"
                                value={office}
                                maxLength={120}
                                onChange={(e) => setOffice(e.target.value)}
                            />
                            <InputError message={errors.office_city} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="offer-split">
                                {t('recruiting.form.split')}
                            </Label>
                            <Input
                                id="offer-split"
                                type="number"
                                min={1}
                                max={100}
                                value={split}
                                onChange={(e) => setSplit(e.target.value)}
                            />
                            <InputError message={errors.split_percent} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="offer-fee">
                                {t('recruiting.form.fee')}
                            </Label>
                            <Input
                                id="offer-fee"
                                type="number"
                                min={0}
                                step={1}
                                value={fee}
                                onChange={(e) => setFee(e.target.value)}
                            />
                            <InputError message={errors.monthly_fee_cents} />
                        </div>
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="offer-fees">
                            {t('recruiting.form.fees')}
                        </Label>
                        <Input
                            id="offer-fees"
                            value={fees}
                            maxLength={300}
                            onChange={(e) => setFees(e.target.value)}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="offer-training">
                            {t('recruiting.form.training')}
                        </Label>
                        <Input
                            id="offer-training"
                            value={training}
                            maxLength={500}
                            onChange={(e) => setTraining(e.target.value)}
                        />
                    </div>
                    <label
                        htmlFor="offer-mentoring"
                        className="flex min-h-12 items-center gap-3 text-base"
                    >
                        <Checkbox
                            id="offer-mentoring"
                            checked={mentoring}
                            onCheckedChange={(on) => setMentoring(on === true)}
                        />
                        {t('recruiting.form.mentoring')}
                    </label>
                    <div className="grid gap-2">
                        <Label htmlFor="offer-message">
                            {t('recruiting.form.message')}
                        </Label>
                        <Textarea
                            id="offer-message"
                            rows={3}
                            maxLength={1000}
                            value={message}
                            onChange={(e) => setMessage(e.target.value)}
                        />
                    </div>
                    <InputError message={errors.offer} />
                </form>
                <DialogFooter>
                    <Button variant="outline" onClick={onClose}>
                        {t('common.cancel')}
                    </Button>
                    <Button
                        type="submit"
                        form="broker-offer"
                        disabled={busy || office.trim() === ''}
                    >
                        {t('recruiting.form.send')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
