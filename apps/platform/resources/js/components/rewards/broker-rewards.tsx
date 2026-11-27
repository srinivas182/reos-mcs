import { Gift } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
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
import type { Campaign, GoalKey, Mode, ModeTargets } from './types';

/** Daily targets for one mode (0 turns a goal off). */
export function ModeTargetsForm({
    mode,
    value,
    goals,
    busy = false,
    onSave,
}: {
    mode: Mode;
    value: ModeTargets[Mode];
    goals: GoalKey[];
    busy?: boolean;
    onSave: (mode: Mode, targets: Record<GoalKey, number>) => void;
}) {
    const { t } = useTranslation();
    const [targets, setTargets] = useState(value.targets);

    return (
        <form
            aria-labelledby={`mode-${mode}`}
            className="space-y-3 rounded-reos-md border border-border bg-card p-4"
            onSubmit={(e) => {
                e.preventDefault();
                onSave(mode, targets);
            }}
        >
            <div className="flex items-center justify-between gap-2">
                <h3 id={`mode-${mode}`} className="text-base font-semibold">
                    {t(`rewards.broker.modes.${mode}`)}
                </h3>
                <Badge variant="outline">
                    {value.own
                        ? t('rewards.broker.own')
                        : t('rewards.broker.reos')}
                </Badge>
            </div>
            <div className="grid grid-cols-2 gap-3">
                {goals.map((g) => (
                    <div key={g} className="space-y-2">
                        <Label htmlFor={`${mode}-${g}`}>
                            {t(`rewards.goals.${g}`)}
                        </Label>
                        <Input
                            id={`${mode}-${g}`}
                            type="number"
                            min={0}
                            max={50}
                            value={targets[g]}
                            onChange={(e) =>
                                setTargets({
                                    ...targets,
                                    [g]: Number(e.target.value),
                                })
                            }
                        />
                    </div>
                ))}
            </div>
            <Button type="submit" disabled={busy}>
                {t('rewards.broker.save')}
            </Button>
        </form>
    );
}

export type CampaignValues = {
    title: string;
    goal: GoalKey | 'all';
    multiplier: number;
    starts_on: string;
    ends_on: string;
    budget: number;
};

/** A new bonus campaign the brokerage funds. */
export function CampaignForm({
    goals,
    multipliers,
    busy = false,
    onCreate,
}: {
    goals: GoalKey[];
    multipliers: number[];
    busy?: boolean;
    onCreate: (values: CampaignValues, reset: () => void) => void;
}) {
    const { t } = useTranslation();
    const blank: CampaignValues = {
        title: '',
        goal: 'ce_lesson',
        multiplier: multipliers[0] ?? 2,
        starts_on: '',
        ends_on: '',
        budget: 10000,
    };
    const [v, setV] = useState<CampaignValues>(blank);
    const ready =
        v.title.trim().length >= 3 &&
        v.starts_on !== '' &&
        v.ends_on >= v.starts_on &&
        v.budget >= 100;

    return (
        <form
            aria-labelledby="new-campaign"
            className="space-y-4 rounded-reos-lg border border-border bg-card p-5"
            onSubmit={(e) => {
                e.preventDefault();
                onCreate({ ...v, title: v.title.trim() }, () => setV(blank));
            }}
        >
            <h3 id="new-campaign" className="text-base font-semibold">
                {t('rewards.campaignsAdmin.new')}
            </h3>
            <div className="grid gap-4 md:grid-cols-2">
                <div className="space-y-2">
                    <Label htmlFor="campaign-title">
                        {t('rewards.campaignsAdmin.name')}
                    </Label>
                    <Input
                        id="campaign-title"
                        value={v.title}
                        maxLength={120}
                        onChange={(e) => setV({ ...v, title: e.target.value })}
                    />
                </div>
                <div className="space-y-2">
                    <Label htmlFor="campaign-goal">
                        {t('rewards.campaignsAdmin.goal')}
                    </Label>
                    <Select
                        value={v.goal}
                        onValueChange={(g) =>
                            setV({ ...v, goal: g as CampaignValues['goal'] })
                        }
                    >
                        <SelectTrigger id="campaign-goal">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {goals.map((g) => (
                                <SelectItem key={g} value={g}>
                                    {t(`rewards.goals.${g}`)}
                                </SelectItem>
                            ))}
                            <SelectItem value="all">
                                {t('rewards.allGoals')}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div className="space-y-2">
                    <Label htmlFor="campaign-multiplier">
                        {t('rewards.campaignsAdmin.multiplier')}
                    </Label>
                    <Select
                        value={String(v.multiplier)}
                        onValueChange={(m) =>
                            setV({ ...v, multiplier: Number(m) })
                        }
                    >
                        <SelectTrigger id="campaign-multiplier">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {multipliers.map((m) => (
                                <SelectItem key={m} value={String(m)}>
                                    {m === 3
                                        ? t('rewards.campaignsAdmin.triple')
                                        : t('rewards.campaignsAdmin.double')}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
                <div className="space-y-2">
                    <Label htmlFor="campaign-budget">
                        {t('rewards.campaignsAdmin.budget')}
                    </Label>
                    <Input
                        id="campaign-budget"
                        type="number"
                        min={100}
                        step={100}
                        value={v.budget}
                        aria-describedby="campaign-budget-hint"
                        onChange={(e) =>
                            setV({ ...v, budget: Number(e.target.value) })
                        }
                    />
                    <p
                        id="campaign-budget-hint"
                        className="text-sm text-muted-foreground"
                    >
                        {t('rewards.campaignsAdmin.budgetHint')}
                    </p>
                </div>
                <div className="space-y-2">
                    <Label htmlFor="campaign-starts">
                        {t('rewards.campaignsAdmin.starts')}
                    </Label>
                    <Input
                        id="campaign-starts"
                        type="date"
                        value={v.starts_on}
                        onChange={(e) =>
                            setV({ ...v, starts_on: e.target.value })
                        }
                    />
                </div>
                <div className="space-y-2">
                    <Label htmlFor="campaign-ends">
                        {t('rewards.campaignsAdmin.ends')}
                    </Label>
                    <Input
                        id="campaign-ends"
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
                {t('rewards.campaignsAdmin.create')}
            </Button>
        </form>
    );
}

/** The brokerage's campaigns with budget used and End now. */
export function CampaignList({
    campaigns,
    busy = false,
    onEnd,
}: {
    campaigns: Campaign[];
    busy?: boolean;
    onEnd: (c: Campaign) => void;
}) {
    const { t, i18n } = useTranslation();
    const day = (d: string) =>
        new Intl.DateTimeFormat(i18n.language, {
            month: 'long',
            day: 'numeric',
            timeZone: 'UTC',
        }).format(new Date(`${d}T12:00:00Z`));
    const today = new Date().toISOString().slice(0, 10);

    if (campaigns.length === 0) {
        return (
            <EmptyState
                icon={Gift}
                title={t('rewards.campaignsAdmin.none')}
                description={t('rewards.campaignsAdmin.noneHint')}
            />
        );
    }

    return (
        <ul className="space-y-2">
            {campaigns.map((c) => (
                <li
                    key={c.id}
                    className="flex flex-wrap items-center justify-between gap-3 rounded-reos-md border border-border bg-card p-4"
                >
                    <div className="space-y-1">
                        <p className="text-base font-semibold">{c.title}</p>
                        <p className="text-sm text-muted-foreground">
                            {`${c.multiplier}× · ${c.goal === 'all' ? t('rewards.allGoals') : t(`rewards.goals.${c.goal as 'post'}`)} · ${day(c.starts_on)} – ${day(c.ends_on)}`}
                        </p>
                        <p className="text-sm">
                            {t('rewards.campaignsAdmin.used', {
                                spent: c.spent.toLocaleString(i18n.language),
                                budget: c.budget.toLocaleString(i18n.language),
                            })}
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Badge variant="outline">
                            {c.active
                                ? t('rewards.campaignsAdmin.active')
                                : c.starts_on > today
                                  ? t('rewards.campaignsAdmin.scheduled', {
                                        date: day(c.starts_on),
                                    })
                                  : t('rewards.campaignsAdmin.ended')}
                        </Badge>
                        {!c.closed ? (
                            <Button
                                variant="outline"
                                disabled={busy}
                                onClick={() => onEnd(c)}
                            >
                                {t('rewards.campaignsAdmin.end')}
                            </Button>
                        ) : null}
                    </div>
                </li>
            ))}
        </ul>
    );
}
