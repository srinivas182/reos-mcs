import { Head, Link, router } from '@inertiajs/react';
import { MessageCircleQuestion, Plus, SendHorizontal } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { AskAnswer } from '@/components/ask/ask-answer';
import type {
    AskMode,
    AskPackage,
    AskThread,
    AskThreadRow,
} from '@/components/ask/types';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { postJson } from '@/lib/content';
import { index, store } from '@/routes/ask';

/**
 * Ask on the web (candidate screen 13, A10.1): conversations on the left,
 * the open one beside them. Answers come from the person's courses first,
 * with the lesson to watch; beyond them only when a sponsor pays; never a
 * credit balance for a candidate.
 */
export default function AskPage({
    threads,
    thread: initial,
    package: pack,
    state,
}: {
    threads: AskThreadRow[];
    thread: AskThread | null;
    package: AskPackage;
    /** The person's state, for "From your course content · Texas". */
    state: string;
}) {
    const { t } = useTranslation();
    const [thread, setThread] = useState<AskThread | null>(initial);
    const [question, setQuestion] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const send = async (mode: AskMode, text?: string) => {
        setBusy(true);
        setError(null);
        try {
            const { data } = await postJson<{ data: AskThread }>(store().url, {
                mode,
                question: mode === 'ask' ? text : null,
                thread: thread?.uuid ?? null,
            });
            setThread(data);
            setQuestion('');
            if (!thread) {
                router.reload({ only: ['threads'] });
            }
        } catch (e) {
            setError(e instanceof Error ? e.message : String(e));
        } finally {
            setBusy(false);
        }
    };
    const messages = thread?.messages ?? [];
    const lastAnswer = [...messages].reverse().find((m) => m.role === 'answer');

    return (
        <>
            <Head title={t('ask.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('ask.title')}
                    description={
                        pack.candidate
                            ? pack.sponsor
                                ? t('ask.purposeSponsored', {
                                      sponsor: pack.sponsor,
                                  })
                                : t('ask.purposeCandidate')
                            : t('ask.purpose')
                    }
                    actions={
                        <Button
                            variant="outline"
                            onClick={() => {
                                setThread(null);
                                router.visit(index().url, {
                                    preserveState: true,
                                    only: ['thread'],
                                });
                            }}
                        >
                            <Plus aria-hidden />
                            {t('ask.new')}
                        </Button>
                    }
                />
                <div className="grid gap-6 lg:grid-cols-[18rem_1fr]">
                    <nav aria-label={t('ask.threads')} className="space-y-2">
                        <h2 className="font-semibold">{t('ask.threads')}</h2>
                        {threads.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {t('ask.noThreads')}
                            </p>
                        ) : (
                            <ul className="space-y-1">
                                {threads.map((row) => (
                                    <li key={row.uuid}>
                                        <Link
                                            href={
                                                index({
                                                    query: { thread: row.uuid },
                                                }).url
                                            }
                                            preserveScroll
                                            onSuccess={(page) =>
                                                setThread(
                                                    (page.props.thread as
                                                        | AskThread
                                                        | null
                                                        | undefined) ?? null,
                                                )
                                            }
                                            aria-current={
                                                thread?.uuid === row.uuid
                                                    ? 'page'
                                                    : undefined
                                            }
                                            className="block min-h-12 rounded-reos-md px-3 py-2 hover:bg-muted aria-[current=page]:bg-muted aria-[current=page]:font-semibold"
                                        >
                                            {row.title}
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </nav>

                    <section
                        aria-label={t('ask.conversation')}
                        className="space-y-4"
                    >
                        {messages.length === 0 ? (
                            <EmptyState
                                icon={MessageCircleQuestion}
                                title={t('ask.emptyTitle')}
                                description={t('ask.empty')}
                            />
                        ) : (
                            messages.map((m, i) =>
                                m.role === 'answer' ? (
                                    <AskAnswer
                                        key={m.id}
                                        answer={m}
                                        question={
                                            messages
                                                .slice(0, i)
                                                .reverse()
                                                .find(
                                                    (q) =>
                                                        q.role === 'question',
                                                )?.body ?? ''
                                        }
                                        state={state}
                                        last={m.id === lastAnswer?.id}
                                        busy={busy}
                                        onFollow={(mode) => void send(mode)}
                                    />
                                ) : (
                                    <p
                                        key={m.id}
                                        className="ml-auto max-w-[85%] rounded-reos-lg bg-primary px-4 py-3 text-body text-primary-foreground"
                                    >
                                        {m.body}
                                    </p>
                                ),
                            )
                        )}

                        <form
                            className="space-y-2"
                            onSubmit={(e) => {
                                e.preventDefault();
                                if (question.trim().length >= 2) {
                                    void send('ask', question.trim());
                                }
                            }}
                        >
                            <Label htmlFor="ask-question">
                                {t('ask.label')}
                            </Label>
                            <Textarea
                                id="ask-question"
                                value={question}
                                maxLength={500}
                                rows={3}
                                placeholder={t('ask.placeholder')}
                                onChange={(e) => setQuestion(e.target.value)}
                            />
                            {error ? <InputError message={error} /> : null}
                            <Button
                                type="submit"
                                disabled={busy || question.trim().length < 2}
                            >
                                <SendHorizontal aria-hidden />
                                {busy ? t('ask.asking') : t('ask.submit')}
                            </Button>
                        </form>
                    </section>
                </div>
            </div>
        </>
    );
}
