import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { AnswerCard } from '@/components/virtual-broker/answer-card';
import { BrokerReply } from '@/components/virtual-broker/broker-reply';
import { PolicyKnowledge } from '@/components/virtual-broker/policy-knowledge';
import { QuestionList } from '@/components/virtual-broker/question-list';
import type { BrokerVirtualBroker } from '@/components/virtual-broker/types';
import { undoToast } from '@/lib/undo-toast';
import { manual, reply, settings } from '@/routes/organisation/virtual-broker';
import {
    destroy as removeFaq,
    restore as restoreFaq,
} from '@/routes/organisation/virtual-broker/faq';

/**
 * The virtual broker, broker side (A12.1; broker web screen 34 with broker
 * journey screens 5, 11 and 12): agents' questions (waiting and answered)
 * with the AI draft, stats, and the knowledge it answers from.
 */
export default function BrokerVirtualBrokerPage({
    broker,
    open,
}: {
    broker: BrokerVirtualBroker;
    open: string | null;
}) {
    const { t } = useTranslation();
    const [tab, setTab] = useState<'waiting' | 'answered'>(
        open && broker.answered.some((q) => q.id === open)
            ? 'answered'
            : 'waiting',
    );
    const list = tab === 'waiting' ? broker.waiting : broker.answered;
    const [selected, setSelected] = useState<string | null>(
        open ?? list[0]?.id ?? null,
    );
    const [busy, setBusy] = useState(false);
    const current = list.find((q) => q.id === selected) ?? list[0] ?? null;
    const done = { preserveScroll: true, onFinish: () => setBusy(false) };

    return (
        <>
            <Head title={t('virtualBroker.broker.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('virtualBroker.broker.title')}
                    description={t('virtualBroker.broker.purpose', {
                        brokerage: broker.brokerage,
                    })}
                />
                <dl className="grid gap-3 sm:grid-cols-3">
                    {[
                        [
                            t('virtualBroker.broker.stats.ai'),
                            String(broker.stats.answered_by_ai),
                        ],
                        [
                            t('virtualBroker.broker.stats.sent'),
                            String(broker.stats.sent_to_broker),
                        ],
                        [
                            t('virtualBroker.broker.stats.reply'),
                            broker.stats.reply_minutes === null
                                ? '—'
                                : t('virtualBroker.broker.stats.minutes', {
                                      count: broker.stats.reply_minutes,
                                  }),
                        ],
                    ].map(([label, value]) => (
                        <div
                            key={label}
                            className="rounded-reos-lg border border-border bg-card p-4"
                        >
                            <dt className="text-base text-muted-foreground">
                                {label}
                            </dt>
                            <dd className="text-title font-semibold">
                                {value}
                            </dd>
                        </div>
                    ))}
                </dl>
                <section aria-labelledby="vb-questions" className="space-y-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <h2 id="vb-questions" className="text-lg font-semibold">
                            {t('virtualBroker.broker.questions')}
                        </h2>
                        <div
                            className="flex gap-2"
                            role="group"
                            aria-label={t('virtualBroker.broker.questions')}
                        >
                            {(['waiting', 'answered'] as const).map((key) => (
                                <Button
                                    key={key}
                                    type="button"
                                    variant={
                                        tab === key ? 'default' : 'outline'
                                    }
                                    aria-pressed={tab === key}
                                    onClick={() => {
                                        setTab(key);
                                        setSelected(
                                            (key === 'waiting'
                                                ? broker.waiting
                                                : broker.answered)[0]?.id ??
                                                null,
                                        );
                                    }}
                                >
                                    {t(`virtualBroker.broker.tabs.${key}`, {
                                        count: (key === 'waiting'
                                            ? broker.waiting
                                            : broker.answered
                                        ).length,
                                    })}
                                </Button>
                            ))}
                        </div>
                    </div>
                    <div className="grid gap-6 lg:grid-cols-[2fr_3fr]">
                        <QuestionList
                            questions={list}
                            selected={current?.id ?? null}
                            onSelect={setSelected}
                            showAgent
                            emptyTitle={
                                tab === 'waiting'
                                    ? t('virtualBroker.broker.noneWaiting')
                                    : t('virtualBroker.broker.noneAnswered')
                            }
                            emptyBody={
                                tab === 'waiting'
                                    ? t('virtualBroker.broker.noneWaitingBody')
                                    : t('virtualBroker.broker.noneAnsweredBody')
                            }
                        />
                        {current && tab === 'waiting' ? (
                            <BrokerReply
                                key={current.id}
                                question={current}
                                busy={busy}
                                onSend={(answer, add) => {
                                    setBusy(true);
                                    router.post(
                                        reply(current.id).url,
                                        { answer, add_to_knowledge: add },
                                        done,
                                    );
                                }}
                            />
                        ) : current ? (
                            <AnswerCard question={current} />
                        ) : null}
                    </div>
                </section>
                <PolicyKnowledge
                    data={broker}
                    busy={busy}
                    onUpload={(file) => {
                        setBusy(true);
                        router.post(
                            manual().url,
                            { file },
                            { ...done, forceFormData: true },
                        );
                    }}
                    onSaveSettings={(answererId, replyHours) => {
                        setBusy(true);
                        router.put(
                            settings().url,
                            {
                                answerer_id: answererId,
                                reply_hours: replyHours,
                            },
                            done,
                        );
                    }}
                    onRemoveFaq={(id) => {
                        router.delete(removeFaq(id).url, {
                            preserveScroll: true,
                            onSuccess: () =>
                                undoToast({
                                    message: t('virtualBroker.broker.removed'),
                                    onUndo: () =>
                                        router.post(
                                            restoreFaq(id).url,
                                            {},
                                            { preserveScroll: true },
                                        ),
                                }),
                        });
                    }}
                />
            </div>
        </>
    );
}
