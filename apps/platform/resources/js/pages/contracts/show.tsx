import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, ShieldQuestion } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ContractAsk } from '@/components/contracts/contract-ask';
import { ReviewProgress } from '@/components/contracts/review-progress';
import { ReviewResult } from '@/components/contracts/review-result';
import type { ContractReview } from '@/components/contracts/types';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { postJson } from '@/lib/content';
import { broker, deadlines, file, index, questions } from '@/routes/contracts';
import { index as virtualBroker } from '@/routes/virtual-broker';

/**
 * One contract review (A12.2; agent web screen 15 with screens 64–66): the
 * contract beside its findings (a page reference opens that page), the
 * deadlines, key terms, Send to broker and Ask about this contract. While
 * it's reviewing, the steps show and the page refreshes itself.
 */
export default function ContractReviewPage({
    review: initial,
}: {
    review: ContractReview;
}) {
    const { t } = useTranslation();
    const [review, setReview] = useState(initial);
    const [page, setPage] = useState(1);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => setReview(initial), [initial]);
    useEffect(() => {
        if (review.status !== 'reviewing') return undefined;
        const timer = setInterval(
            () => router.reload({ only: ['review'] }),
            3000,
        );
        return () => clearInterval(timer);
    }, [review.status]);

    const act = (url: string, body: unknown = {}) => {
        setBusy(true);
        setError(null);
        return postJson<{ data: ContractReview }>(url, body)
            .then(({ data }) => setReview(data))
            .catch((e: unknown) =>
                setError(e instanceof Error ? e.message : String(e)),
            )
            .finally(() => setBusy(false));
    };

    return (
        <>
            <Head title={t('contracts.web.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    back={
                        <Link
                            href={index().url}
                            className="inline-flex min-h-12 items-center gap-2 text-base font-medium text-primary"
                        >
                            <ArrowLeft aria-hidden className="size-4" />
                            {t('contracts.web.back')}
                        </Link>
                    }
                    title={review.form_name ?? review.file_name}
                    description={
                        review.deal
                            ? t('contracts.web.onDeal', {
                                  property: review.deal.property,
                              })
                            : t('contracts.web.purpose')
                    }
                    actions={
                        <Button asChild variant="outline">
                            <Link href={virtualBroker().url}>
                                <ShieldQuestion aria-hidden />
                                {t('virtualBroker.title')}
                            </Link>
                        </Button>
                    }
                />
                {error ? (
                    <p role="alert" className="text-base text-destructive">
                        {error}
                    </p>
                ) : null}
                {review.status === 'reviewing' ? (
                    <ReviewProgress step={review.step} />
                ) : null}
                {review.status === 'failed' ? (
                    <p
                        role="alert"
                        className="rounded-reos-md border border-reos-tone-danger-fg/30 bg-reos-tone-danger-bg p-4 text-base text-reos-tone-danger-fg"
                    >
                        {review.error}
                    </p>
                ) : null}
                {review.status === 'done' ? (
                    <div className="grid gap-6 xl:grid-cols-2">
                        <section
                            aria-label={t('contracts.web.viewContract')}
                            className="space-y-2"
                        >
                            <iframe
                                key={page}
                                title={t('contracts.web.viewContract')}
                                src={`${file(review.id).url}#page=${page}`}
                                className="h-[70vh] w-full rounded-reos-lg border border-border bg-card"
                            />
                            <a
                                href={file(review.id).url}
                                target="_blank"
                                rel="noreferrer"
                                className="inline-flex min-h-12 items-center text-base font-medium text-primary underline-offset-4 hover:underline"
                            >
                                {t('contracts.web.viewContract')}
                            </a>
                        </section>
                        <div className="space-y-6">
                            <ReviewResult
                                review={review}
                                busy={busy}
                                onPage={setPage}
                                onAddDeadlines={() =>
                                    void act(deadlines(review.id).url)
                                }
                                onSendToBroker={() =>
                                    void act(broker(review.id).url)
                                }
                            />
                            <ContractAsk
                                questions={review.questions}
                                busy={busy}
                                cost={1}
                                onAsk={(q) =>
                                    act(questions(review.id).url, {
                                        question: q,
                                    })
                                }
                            />
                        </div>
                    </div>
                ) : null}
            </div>
        </>
    );
}
