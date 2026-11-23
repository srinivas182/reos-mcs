import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, PartyPopper } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { PassCard } from '@/components/licence/pass-card';
import type { PassFeed } from '@/components/licence/types';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { postJson } from '@/lib/content';
import { show } from '@/routes/licence';
import { congrats } from '@/routes/licence/passes';
import { start } from '@/routes/practice';

/**
 * Passes (candidate f06, A10.3b): real wins from candidates in the state and
 * the study group who chose to share, the week's count, Send congrats, a
 * simulation, and the viewer's own readiness.
 */
export default function PassesPage({ feed }: { feed: PassFeed }) {
    const { t } = useTranslation();
    const [error, setError] = useState<string | null>(null);

    const send = async (id: number, message: string) => {
        try {
            await postJson(congrats(id).url, { message });
            setError(null);
            router.reload({ only: ['feed'] });
        } catch (e) {
            setError(e instanceof Error ? e.message : String(e));
        }
    };

    return (
        <>
            <Head title={t('licence.news.passes.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    back={
                        <Link
                            href={show().url}
                            className="inline-flex min-h-12 items-center gap-2 text-base text-muted-foreground hover:text-foreground"
                        >
                            <ArrowLeft aria-hidden className="size-4" />
                            {t('licence.title')}
                        </Link>
                    }
                    title={t('licence.news.passes.title')}
                    description={t('licence.news.passes.purpose', {
                        state: feed.state,
                    })}
                    actions={
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.post(start().url, { kind: 'simulation' })
                            }
                        >
                            {t('licence.news.passes.simulation')}
                        </Button>
                    }
                />
                <div className="flex flex-wrap gap-x-6 gap-y-2 text-base">
                    <p className="font-semibold">
                        {t('licence.news.passes.week', {
                            count: feed.week,
                            state: feed.state,
                        })}
                    </p>
                    {feed.readiness !== null ? (
                        <p className="text-muted-foreground">
                            {t('licence.news.passes.readiness', {
                                percent: feed.readiness,
                            })}
                        </p>
                    ) : null}
                </div>
                {error ? <InputError message={error} /> : null}
                {feed.passes.length === 0 ? (
                    <EmptyState
                        icon={PartyPopper}
                        title={t('licence.news.passes.empty')}
                        description={t('licence.news.passes.emptyBody')}
                    />
                ) : (
                    <ul className="space-y-3">
                        {feed.passes.map((pass) => (
                            <li key={pass.id}>
                                <PassCard
                                    pass={pass}
                                    onCongrats={(message) =>
                                        void send(pass.id, message)
                                    }
                                />
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}
