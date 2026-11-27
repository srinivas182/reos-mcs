import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { ChallengeMetric } from './types';

export type ChallengeValues = {
    title: string;
    metric: ChallengeMetric;
    starts_on: string;
    ends_on: string;
};

/** The broker's new-challenge form: a name, what counts, and up to three months of dates. */
export function ChallengeForm({
    metrics,
    busy = false,
    onCreate,
}: {
    metrics: ChallengeMetric[];
    busy?: boolean;
    onCreate: (values: ChallengeValues, reset: () => void) => void;
}) {
    const { t } = useTranslation();
    const blank: ChallengeValues = {
        title: '',
        metric: metrics[0] ?? 'conversations',
        starts_on: '',
        ends_on: '',
    };
    const [v, setV] = useState<ChallengeValues>(blank);
    const ready =
        v.title.trim().length >= 3 &&
        v.starts_on !== '' &&
        v.ends_on !== '' &&
        v.ends_on >= v.starts_on;

    return (
        <form
            aria-labelledby="new-challenge"
            className="space-y-4 rounded-reos-lg border border-border bg-card p-5"
            onSubmit={(e) => {
                e.preventDefault();
                onCreate({ ...v, title: v.title.trim() }, () => setV(blank));
            }}
        >
            <h2 id="new-challenge" className="text-lg font-semibold">
                {t('pods.challenges.broker.new')}
            </h2>
            <div className="grid gap-4 md:grid-cols-2">
                <div className="space-y-2">
                    <Label htmlFor="challenge-title">
                        {t('pods.challenges.broker.name')}
                    </Label>
                    <Input
                        id="challenge-title"
                        value={v.title}
                        maxLength={120}
                        onChange={(e) => setV({ ...v, title: e.target.value })}
                    />
                </div>
                <div className="space-y-2">
                    <Label htmlFor="challenge-metric">
                        {t('pods.challenges.broker.metric')}
                    </Label>
                    <Select
                        value={v.metric}
                        onValueChange={(m) =>
                            setV({ ...v, metric: m as ChallengeMetric })
                        }
                    >
                        <SelectTrigger id="challenge-metric">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {metrics.map((m) => (
                                <SelectItem key={m} value={m}>
                                    {t(`pods.challenges.metrics.${m}`)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
                <div className="space-y-2">
                    <Label htmlFor="challenge-starts">
                        {t('pods.challenges.broker.starts')}
                    </Label>
                    <Input
                        id="challenge-starts"
                        type="date"
                        value={v.starts_on}
                        onChange={(e) =>
                            setV({ ...v, starts_on: e.target.value })
                        }
                    />
                </div>
                <div className="space-y-2">
                    <Label htmlFor="challenge-ends">
                        {t('pods.challenges.broker.ends')}
                    </Label>
                    <Input
                        id="challenge-ends"
                        type="date"
                        value={v.ends_on}
                        min={v.starts_on || undefined}
                        onChange={(e) =>
                            setV({ ...v, ends_on: e.target.value })
                        }
                    />
                </div>
            </div>
            <Button type="submit" disabled={busy || !ready}>
                {t('pods.challenges.broker.create')}
            </Button>
        </form>
    );
}
