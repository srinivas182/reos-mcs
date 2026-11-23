import { Head, router } from '@inertiajs/react';
import { CalendarCheck } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useSlotText } from '@/components/bookings/instructor-card';
import type { Booking, InstructorOffer } from '@/components/bookings/types';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { PillGroup } from '@/components/pill-group';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { money } from '@/lib/billing';
import { postJson } from '@/lib/content';
import { index, store } from '@/routes/bookings';

/**
 * One instructor (candidate screen 18, A10.2a): profile, length, a free
 * time and the topic. Included sessions are booked at once; paid ones go to
 * checkout, with the cancellation rules shown before paying.
 */
export default function InstructorPage({
    instructor,
    slots,
}: {
    instructor: InstructorOffer;
    slots: { '30': string[]; '60': string[] };
}) {
    const { t, i18n } = useTranslation();
    const slotText = useSlotText();
    const [minutes, setMinutes] = useState<'30' | '60'>('60');
    const [slot, setSlot] = useState<string | null>(null);
    const [topic, setTopic] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const price = minutes === '30' ? instructor.price_30 : instructor.price_60;
    const shown = slots[minutes].slice(0, 24);

    const book = async () => {
        if (!slot) {
            return;
        }
        setBusy(true);
        setError(null);
        try {
            const result = await postJson<{
                data: Booking;
                checkout_url: string | null;
            }>(store().url, {
                instructor: instructor.user_id,
                starts_at: slot,
                minutes: Number(minutes),
                topic,
            });
            // Checkout (a paid session) or back to your sessions; both are REOS pages.
            router.visit(result.checkout_url ?? index().url);
        } catch (e) {
            setError(e instanceof Error ? e.message : String(e));
            setBusy(false);
        }
    };

    return (
        <>
            <Head title={instructor.name} />
            <div className="max-w-3xl space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={instructor.name}
                    description={[
                        instructor.school,
                        instructor.topics.join(' · '),
                    ]
                        .filter(Boolean)
                        .join(' · ')}
                />
                {instructor.bio ? (
                    <p className="text-body">{instructor.bio}</p>
                ) : null}

                <PillGroup
                    label={t('bookings.length')}
                    value={minutes}
                    options={(['30', '60'] as const).map((m) => ({
                        value: m,
                        label: `${t('bookings.minutes', { count: Number(m) })} · ${
                            (m === '30'
                                ? instructor.price_30
                                : instructor.price_60) === 0
                                ? t('bookings.included')
                                : money(
                                      m === '30'
                                          ? instructor.price_30
                                          : instructor.price_60,
                                      i18n.language,
                                  )
                        }`,
                    }))}
                    onChange={(m) => {
                        setMinutes(m);
                        setSlot(null);
                    }}
                />

                <fieldset className="space-y-2">
                    <legend className="font-semibold">
                        {t('bookings.pickTime')}
                    </legend>
                    {shown.length === 0 ? (
                        <p className="text-muted-foreground">
                            {t('bookings.noTimes')}
                        </p>
                    ) : (
                        <div className="flex flex-wrap gap-2">
                            {shown.map((s) => (
                                <Button
                                    key={s}
                                    variant={slot === s ? 'default' : 'outline'}
                                    aria-pressed={slot === s}
                                    onClick={() => setSlot(s)}
                                >
                                    {slotText(s)}
                                </Button>
                            ))}
                        </div>
                    )}
                </fieldset>

                <div className="grid gap-2">
                    <Label htmlFor="booking-topic">{t('bookings.topic')}</Label>
                    <Input
                        id="booking-topic"
                        value={topic}
                        maxLength={200}
                        placeholder={t('bookings.topicPlaceholder')}
                        onChange={(e) => setTopic(e.target.value)}
                    />
                </div>

                <p className="text-sm text-muted-foreground">
                    {t('bookings.rules')}
                </p>
                {error ? <InputError message={error} /> : null}
                <Button
                    disabled={busy || slot === null || topic.trim().length < 3}
                    onClick={() => void book()}
                >
                    <CalendarCheck aria-hidden />
                    {price === 0
                        ? t('bookings.book')
                        : t('bookings.bookAndPay', {
                              price: money(price, i18n.language),
                          })}
                </Button>
            </div>
        </>
    );
}
