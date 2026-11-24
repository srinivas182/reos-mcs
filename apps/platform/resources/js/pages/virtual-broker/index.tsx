import { Head } from '@inertiajs/react';
import { BookOpen } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/page-header';
import { AnswerCard } from '@/components/virtual-broker/answer-card';
import { AskForm } from '@/components/virtual-broker/ask-form';
import { QuestionList } from '@/components/virtual-broker/question-list';
import type {
    AgentVirtualBroker,
    BrokerQuestion,
} from '@/components/virtual-broker/types';
import { postJson } from '@/lib/content';
import { index as deals } from '@/routes/deals';
import { escalate, store } from '@/routes/virtual-broker/questions';

/**
 * The virtual broker (A12.1; agent web screen 16): ask by text, answers
 * with sources and a confidence level, escalated questions with their
 * status, and the broker's answer labelled. Money and legal questions go
 * to the broker automatically.
 */
export default function VirtualBrokerPage({
    broker,
    open,
}: {
    broker: AgentVirtualBroker;
    open: string | null;
}) {
    const { t } = useTranslation();
    const [questions, setQuestions] = useState(broker.questions);
    const [selected, setSelected] = useState<string | null>(
        open ?? broker.questions[0]?.id ?? null,
    );
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const current = questions.find((q) => q.id === selected) ?? null;
    const put = (q: BrokerQuestion) =>
        setQuestions((list) => [q, ...list.filter((x) => x.id !== q.id)]);

    const ask = async (question: string) => {
        setBusy(true);
        setError(null);
        try {
            const { data } = await postJson<{ data: BrokerQuestion }>(
                store().url,
                { question },
            );
            put(data);
            setSelected(data.id);
        } catch (e) {
            setError(e instanceof Error ? e.message : String(e));
            throw e;
        } finally {
            setBusy(false);
        }
    };

    return (
        <>
            <Head title={t('virtualBroker.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('virtualBroker.title')}
                    description={t('virtualBroker.purpose', {
                        brokerage: broker.brokerage,
                    })}
                />
                {!broker.manual ? (
                    <p className="flex items-center gap-2 rounded-reos-md border border-border bg-muted/40 p-3 text-base">
                        <BookOpen aria-hidden className="size-5" />
                        {t('virtualBroker.noManual', {
                            brokerage: broker.brokerage,
                        })}
                    </p>
                ) : null}
                <AskForm
                    brokerage={broker.brokerage}
                    onAsk={(q) => ask(q).catch(() => undefined)}
                    busy={busy}
                    error={error}
                />
                <div className="grid gap-6 lg:grid-cols-[2fr_3fr]">
                    <section aria-labelledby="vb-list" className="space-y-3">
                        <h2 id="vb-list" className="text-lg font-semibold">
                            {t('virtualBroker.conversations')}
                        </h2>
                        <QuestionList
                            questions={questions}
                            selected={selected}
                            onSelect={setSelected}
                            emptyTitle={t('virtualBroker.empty')}
                            emptyBody={t('virtualBroker.emptyBody')}
                        />
                    </section>
                    {current ? (
                        <AnswerCard
                            question={current}
                            busy={busy}
                            dealHref={(uuid) => `${deals().url}?deal=${uuid}`}
                            onEscalate={() => {
                                setBusy(true);
                                void postJson<{ data: BrokerQuestion }>(
                                    escalate(current.id).url,
                                )
                                    .then(({ data }) => put(data))
                                    .catch((e: unknown) =>
                                        setError(
                                            e instanceof Error
                                                ? e.message
                                                : String(e),
                                        ),
                                    )
                                    .finally(() => setBusy(false));
                            }}
                        />
                    ) : null}
                </div>
            </div>
        </>
    );
}
