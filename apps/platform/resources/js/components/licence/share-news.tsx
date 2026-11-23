import { PartyPopper } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

export type ShareChoice = {
    to_group: boolean;
    to_school: boolean;
    to_state: boolean;
    city: string | null;
    tip: string | null;
};

/**
 * Share your news (f05, A10.3b): who hears is the candidate's choice; only
 * a first name and city, and an optional tip, never scores.
 */
export function ShareNews({
    state,
    city: initialCity,
    onShare,
    onSkip,
}: {
    state: string;
    city: string | null;
    onShare: (choice: ShareChoice) => void;
    onSkip: () => void;
}) {
    const { t } = useTranslation();
    const [toGroup, setToGroup] = useState(true);
    const [toSchool, setToSchool] = useState(true);
    const [toState, setToState] = useState(true);
    const [city, setCity] = useState(initialCity ?? '');
    const [tip, setTip] = useState('');
    const switches = [
        {
            id: 'news-group',
            label: t('licence.news.group'),
            hint: t('licence.news.groupHint'),
            value: toGroup,
            set: setToGroup,
        },
        {
            id: 'news-school',
            label: t('licence.news.school'),
            hint: t('licence.news.schoolHint'),
            value: toSchool,
            set: setToSchool,
        },
        {
            id: 'news-state',
            label: t('licence.news.state', { state }),
            hint: t('licence.news.stateHint'),
            value: toState,
            set: setToState,
        },
    ];

    return (
        <section
            aria-labelledby="share-news"
            className="space-y-4 rounded-reos-lg border border-border bg-card p-5"
        >
            <h2
                id="share-news"
                className="flex items-center gap-2 text-lg font-semibold"
            >
                <PartyPopper aria-hidden className="size-5" />
                {t('licence.news.title')}
            </h2>
            <p className="text-base text-muted-foreground">
                {t('licence.news.body')}
            </p>
            <div className="space-y-1">
                {switches.map((s) => (
                    <label
                        key={s.id}
                        htmlFor={s.id}
                        className="flex min-h-12 items-start gap-3 text-base"
                    >
                        <Checkbox
                            id={s.id}
                            className="mt-1"
                            checked={s.value}
                            onCheckedChange={(on) => s.set(on === true)}
                        />
                        <span>
                            {s.label}
                            <span className="block text-sm text-muted-foreground">
                                {s.hint}
                            </span>
                        </span>
                    </label>
                ))}
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="news-city">{t('licence.news.city')}</Label>
                    <Input
                        id="news-city"
                        value={city}
                        maxLength={120}
                        onChange={(e) => setCity(e.target.value)}
                    />
                </div>
                <div className="grid gap-2 sm:col-span-2">
                    <Label htmlFor="news-tip">{t('licence.news.tip')}</Label>
                    <Textarea
                        id="news-tip"
                        rows={2}
                        maxLength={280}
                        placeholder={t('licence.news.tipPlaceholder')}
                        value={tip}
                        onChange={(e) => setTip(e.target.value)}
                    />
                </div>
            </div>
            <div className="flex flex-wrap gap-3">
                <Button
                    disabled={!toGroup && !toSchool && !toState}
                    onClick={() =>
                        onShare({
                            to_group: toGroup,
                            to_school: toSchool,
                            to_state: toState,
                            city: city.trim() || null,
                            tip: tip.trim() || null,
                        })
                    }
                >
                    {t('licence.news.share')}
                </Button>
                <Button variant="outline" onClick={onSkip}>
                    {t('licence.news.notNow')}
                </Button>
            </div>
        </section>
    );
}
