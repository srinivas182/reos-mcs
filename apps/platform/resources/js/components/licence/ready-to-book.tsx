import { Link } from '@inertiajs/react';
import { CalendarCheck, ExternalLink, Luggage } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { dayText, type Journey } from '@/components/licence/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { readiness as readinessPage } from '@/routes/practice';
import { examDay } from '@/routes/licence';

/**
 * Ready to book (screen 15): both portions against the booking line and
 * the simulations passed; the testing vendor's site (a hand-off); and the
 * exam date from its confirmation, which sets the reminders and the plan.
 */
export function ReadyToBook({
    journey,
    onBook,
}: {
    journey: Journey;
    onBook: (
        examOn: string | null,
        time: string | null,
        centre: string | null,
    ) => void;
}) {
    const { t, i18n } = useTranslation();
    const { readiness, exam } = journey;
    const [date, setDate] = useState(exam.on ?? '');
    const [time, setTime] = useState(exam.time ?? '');
    const [centre, setCentre] = useState(exam.centre ?? '');
    const portions = [
        { key: 'national', value: readiness.national },
        { key: 'state', value: readiness.state },
    ] as const;

    return (
        <section
            aria-labelledby="ready-to-book"
            className="space-y-4 rounded-reos-lg border border-border bg-card p-5"
        >
            <h2 id="ready-to-book" className="text-lg font-semibold">
                {readiness.ready
                    ? t('licence.book.ready')
                    : t('licence.book.notYet')}
            </h2>
            <ul className="grid gap-3 sm:grid-cols-3">
                {portions.map(({ key, value }) => (
                    <li key={key} className="space-y-1">
                        <p className="text-sm text-muted-foreground">
                            {t(`licence.portions.${key}`)}
                        </p>
                        <p className="text-title font-semibold tabular-nums">
                            {value === null
                                ? t('licence.book.unknown')
                                : `${value}%`}
                        </p>
                    </li>
                ))}
                <li className="space-y-1">
                    <p className="text-sm text-muted-foreground">
                        {t('licence.book.simulations')}
                    </p>
                    <p className="text-title font-semibold tabular-nums">
                        {t('licence.book.simulationsValue', {
                            count: readiness.simulations,
                            needed: readiness.needed,
                        })}
                    </p>
                </li>
            </ul>
            <p className="text-base text-muted-foreground">
                {readiness.ready
                    ? t('licence.book.readyBody', { vendor: exam.vendor ?? '' })
                    : t('licence.book.notYetBody', {
                          line: readiness.line,
                          needed: readiness.needed,
                      })}
            </p>
            <div className="flex flex-wrap gap-3">
                {readiness.ready && exam.book_url ? (
                    <Button asChild>
                        <a
                            href={exam.book_url}
                            target="_blank"
                            rel="noreferrer"
                        >
                            <ExternalLink aria-hidden />
                            {t('licence.book.button', {
                                vendor: exam.vendor ?? '',
                            })}
                        </a>
                    </Button>
                ) : (
                    <Button asChild variant="outline">
                        <Link href={readinessPage().url}>
                            {t('licence.book.seeReadiness')}
                        </Link>
                    </Button>
                )}
            </div>

            <form
                className="space-y-3 border-t border-border pt-4"
                onSubmit={(e) => {
                    e.preventDefault();
                    onBook(
                        date === '' ? null : date,
                        time === '' ? null : time,
                        centre.trim() === '' ? null : centre.trim(),
                    );
                }}
            >
                <h3 className="flex items-center gap-2 text-base font-semibold">
                    <CalendarCheck aria-hidden className="size-5" />
                    {exam.on
                        ? t('licence.book.booked', {
                              date: dayText(exam.on, i18n.language),
                              count: exam.days_to ?? 0,
                          })
                        : t('licence.book.dateTitle')}
                </h3>
                <div className="grid gap-4 sm:grid-cols-3">
                    <div className="grid gap-2">
                        <Label htmlFor="exam-date">
                            {t('licence.book.date')}
                        </Label>
                        <Input
                            id="exam-date"
                            type="date"
                            value={date}
                            onChange={(e) => setDate(e.target.value)}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="exam-time">
                            {t('licence.book.time')}
                        </Label>
                        <Input
                            id="exam-time"
                            type="time"
                            value={time}
                            onChange={(e) => setTime(e.target.value)}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="exam-centre">
                            {t('licence.book.centre')}
                        </Label>
                        <Input
                            id="exam-centre"
                            value={centre}
                            maxLength={160}
                            onChange={(e) => setCentre(e.target.value)}
                        />
                    </div>
                </div>
                <p className="text-sm text-muted-foreground">
                    {t('licence.book.dateHint')}
                </p>
                <div className="flex flex-wrap gap-3">
                    <Button type="submit" disabled={date === ''}>
                        {t('licence.book.save')}
                    </Button>
                    {exam.on ? (
                        <Button asChild variant="outline">
                            <Link href={examDay().url}>
                                <Luggage aria-hidden />
                                {t('licence.kit.title')}
                            </Link>
                        </Button>
                    ) : null}
                </div>
            </form>
        </section>
    );
}
