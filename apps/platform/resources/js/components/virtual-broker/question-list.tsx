import { MessagesSquare } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { BrokerQuestion } from './types';

/**
 * The conversation list (web screen 16; broker journey screen 11): each
 * question with its status, and for brokers the agent, age and why.
 */
export function QuestionList({
    questions,
    selected,
    onSelect,
    showAgent = false,
    emptyTitle,
    emptyBody,
}: {
    questions: BrokerQuestion[];
    selected: string | null;
    onSelect: (id: string) => void;
    showAgent?: boolean;
    emptyTitle: string;
    emptyBody: string;
}) {
    const { t, i18n } = useTranslation();

    if (questions.length === 0) {
        return (
            <EmptyState
                icon={MessagesSquare}
                title={emptyTitle}
                description={emptyBody}
            />
        );
    }

    const when = (value: string | null) =>
        value
            ? new Intl.DateTimeFormat(i18n.language, {
                  dateStyle: 'medium',
                  timeStyle: 'short',
              }).format(new Date(value))
            : '';

    return (
        <ul className="space-y-2">
            {questions.map((q) => (
                <li key={q.id}>
                    <button
                        type="button"
                        aria-current={selected === q.id ? 'true' : undefined}
                        onClick={() => onSelect(q.id)}
                        className={cn(
                            'flex min-h-12 w-full flex-col items-start gap-1 rounded-reos-md border border-border bg-card p-3 text-left hover:bg-accent focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none',
                            selected === q.id && 'border-primary',
                        )}
                    >
                        <span className="line-clamp-2 text-base font-medium">
                            {q.question}
                        </span>
                        <span className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                            <Badge
                                variant={
                                    q.status === 'waiting'
                                        ? 'default'
                                        : 'outline'
                                }
                            >
                                {t(`virtualBroker.status.${q.status}`)}
                            </Badge>
                            {showAgent ? <span>{q.agent}</span> : null}
                            {showAgent && q.reason ? (
                                <span>
                                    · {t(`virtualBroker.reasons.${q.reason}`)}
                                </span>
                            ) : null}
                            <span>· {when(q.created_at)}</span>
                        </span>
                    </button>
                </li>
            ))}
        </ul>
    );
}
