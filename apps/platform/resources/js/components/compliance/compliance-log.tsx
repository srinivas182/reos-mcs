import { Download, ScrollText } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
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

export type LogChannel = 'post' | 'video' | 'page' | 'email' | 'text';
export type LogAction =
    | 'checked'
    | 'blocked'
    | 'fixed'
    | 'sent_to_broker'
    | 'approved'
    | 'sent_back';

export type LogRow = {
    id: number;
    at: string;
    agent: string;
    actor: string | null;
    channel: LogChannel;
    action: LogAction;
    subject: string | null;
    issues: { code: string; phrase: string | null; citation: string }[];
    note: string | null;
};

export type LogFilters = {
    agent: number | null;
    channel: LogChannel | null;
    action: LogAction | null;
    from: string | null;
    to: string | null;
};

const ALL = 'all';

/** Filters for the compliance log: agent, channel, what happened and dates. */
export function LogFiltersForm({
    filters,
    agents,
    channels,
    actions,
    exportHref,
    onApply,
}: {
    filters: LogFilters;
    agents: { id: number; name: string }[];
    channels: LogChannel[];
    actions: LogAction[];
    exportHref: string;
    onApply: (filters: LogFilters) => void;
}) {
    const { t } = useTranslation();
    const [v, setV] = useState<LogFilters>(filters);

    return (
        <form
            aria-label={t('compliance.log.title')}
            className="grid gap-3 rounded-reos-lg border border-border bg-card p-4 md:grid-cols-6"
            onSubmit={(e) => {
                e.preventDefault();
                onApply(v);
            }}
        >
            <div className="space-y-2">
                <Label htmlFor="log-agent">{t('compliance.log.agent')}</Label>
                <Select
                    value={v.agent === null ? ALL : String(v.agent)}
                    onValueChange={(x) =>
                        setV({ ...v, agent: x === ALL ? null : Number(x) })
                    }
                >
                    <SelectTrigger id="log-agent">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ALL}>
                            {t('compliance.log.all')}
                        </SelectItem>
                        {agents.map((a) => (
                            <SelectItem key={a.id} value={String(a.id)}>
                                {a.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
            <div className="space-y-2">
                <Label htmlFor="log-channel">
                    {t('compliance.log.channel')}
                </Label>
                <Select
                    value={v.channel ?? ALL}
                    onValueChange={(x) =>
                        setV({
                            ...v,
                            channel: x === ALL ? null : (x as LogChannel),
                        })
                    }
                >
                    <SelectTrigger id="log-channel">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ALL}>
                            {t('compliance.log.all')}
                        </SelectItem>
                        {channels.map((c) => (
                            <SelectItem key={c} value={c}>
                                {t(`compliance.log.channels.${c}`)}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
            <div className="space-y-2">
                <Label htmlFor="log-action">{t('compliance.log.action')}</Label>
                <Select
                    value={v.action ?? ALL}
                    onValueChange={(x) =>
                        setV({
                            ...v,
                            action: x === ALL ? null : (x as LogAction),
                        })
                    }
                >
                    <SelectTrigger id="log-action">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={ALL}>
                            {t('compliance.log.all')}
                        </SelectItem>
                        {actions.map((a) => (
                            <SelectItem key={a} value={a}>
                                {t(`compliance.log.actions.${a}`)}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
            <div className="space-y-2">
                <Label htmlFor="log-from">{t('compliance.log.from')}</Label>
                <Input
                    id="log-from"
                    type="date"
                    value={v.from ?? ''}
                    onChange={(e) =>
                        setV({ ...v, from: e.target.value || null })
                    }
                />
            </div>
            <div className="space-y-2">
                <Label htmlFor="log-to">{t('compliance.log.to')}</Label>
                <Input
                    id="log-to"
                    type="date"
                    value={v.to ?? ''}
                    onChange={(e) => setV({ ...v, to: e.target.value || null })}
                />
            </div>
            <div className="flex flex-wrap items-end gap-2">
                <Button type="submit">{t('compliance.log.apply')}</Button>
                <Button variant="outline" asChild>
                    <a href={exportHref}>
                        <Download aria-hidden />
                        {t('compliance.log.export')}
                    </a>
                </Button>
            </div>
        </form>
    );
}

/** The compliance log: newest first, with who, what, where and the rules involved. */
export function LogTable({ rows }: { rows: LogRow[] }) {
    const { t, i18n } = useTranslation();
    const when = (iso: string) =>
        new Intl.DateTimeFormat(i18n.language, {
            dateStyle: 'medium',
            timeStyle: 'short',
        }).format(new Date(iso));

    if (rows.length === 0) {
        return (
            <EmptyState
                icon={ScrollText}
                title={t('compliance.log.none')}
                description={t('compliance.log.noneHint')}
            />
        );
    }

    return (
        <div className="overflow-x-auto rounded-reos-lg border border-border bg-card">
            <table className="w-full text-left text-base">
                <thead className="border-b border-border text-sm text-muted-foreground">
                    <tr>
                        <th scope="col" className="px-4 py-3">
                            {t('compliance.log.when')}
                        </th>
                        <th scope="col" className="px-4 py-3">
                            {t('compliance.log.agent')}
                        </th>
                        <th scope="col" className="px-4 py-3">
                            {t('compliance.log.channel')}
                        </th>
                        <th scope="col" className="px-4 py-3">
                            {t('compliance.log.action')}
                        </th>
                        <th scope="col" className="px-4 py-3">
                            {t('compliance.log.rules')}
                        </th>
                        <th scope="col" className="px-4 py-3">
                            {t('compliance.log.by')}
                        </th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-border">
                    {rows.map((r) => (
                        <tr key={r.id}>
                            <td className="px-4 py-3 whitespace-nowrap">
                                {when(r.at)}
                            </td>
                            <td className="px-4 py-3">{r.agent}</td>
                            <td className="px-4 py-3">
                                {t(`compliance.log.channels.${r.channel}`)}
                            </td>
                            <td className="px-4 py-3">
                                {t(`compliance.log.actions.${r.action}`)}
                                {r.note ? (
                                    <span className="block text-sm text-muted-foreground">
                                        {r.note}
                                    </span>
                                ) : null}
                            </td>
                            <td className="px-4 py-3 text-sm">
                                {r.issues.map((i, n) => (
                                    <span
                                        key={`${i.code}-${n}`}
                                        className="block"
                                    >
                                        {i.phrase ? `“${i.phrase}” · ` : ''}
                                        {i.citation}
                                    </span>
                                ))}
                            </td>
                            <td className="px-4 py-3">{r.actor ?? ''}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
