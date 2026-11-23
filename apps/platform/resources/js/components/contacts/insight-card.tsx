import { Sparkles } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { timeAgo } from '@/lib/time-ago';
import type { ContactInsight } from '@/types';

type Props = {
    insight: ContactInsight | null;
    busy: boolean;
    onAsk: () => void;
};

/**
 * The AI summary and next step (B8.3): free for agents, written from every
 * source and kept until the contact changes (a new note, task or sync
 * makes a new one). It suggests; the agent acts.
 */
export function InsightCard({ insight, busy, onAsk }: Props) {
    const { t, i18n } = useTranslation();

    return (
        <section
            aria-labelledby="insight-title"
            aria-busy={busy}
            className="space-y-3 rounded-reos-lg border border-border bg-card p-4"
        >
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h3
                    id="insight-title"
                    className="flex items-center gap-2 font-semibold"
                >
                    <Sparkles aria-hidden className="size-5 text-primary" />
                    {t('crm.hub.ai.summary')}
                </h3>
                <span className="text-sm text-muted-foreground">
                    {t('crm.hub.ai.free')}
                </span>
            </div>
            {insight === null ? (
                <>
                    <p className="text-body text-muted-foreground">
                        {t('crm.hub.ai.intro')}
                    </p>
                    <Button onClick={onAsk} disabled={busy}>
                        {busy ? <Spinner /> : <Sparkles aria-hidden />}
                        {busy
                            ? t('crm.hub.ai.writing')
                            : t('crm.hub.ai.summary')}
                    </Button>
                </>
            ) : (
                <>
                    <p className="text-body">{insight.summary}</p>
                    <div className="rounded-reos-md bg-reos-tone-info-bg p-3 text-reos-tone-info-fg">
                        <p className="text-sm font-medium">
                            {t('crm.hub.ai.nextStep')}
                        </p>
                        <p className="text-body font-semibold">
                            {insight.next_step}
                        </p>
                        <p className="text-body">
                            <span className="font-medium">
                                {t('crm.hub.ai.why')}:{' '}
                            </span>
                            {insight.why}
                        </p>
                    </div>
                    {insight.draft ? (
                        <div>
                            <p className="text-sm font-medium text-muted-foreground">
                                {t('crm.hub.ai.draft')}
                            </p>
                            <p className="text-body whitespace-pre-line">
                                {insight.draft}
                            </p>
                        </div>
                    ) : null}
                    <p className="text-sm text-muted-foreground">
                        {insight.engine === 'simulator'
                            ? t('crm.hub.ai.simulated')
                            : t('crm.hub.ai.written', {
                                  ago: timeAgo(
                                      insight.generated_at,
                                      i18n.language,
                                  ).toLocaleLowerCase(i18n.language),
                              })}
                    </p>
                </>
            )}
        </section>
    );
}
