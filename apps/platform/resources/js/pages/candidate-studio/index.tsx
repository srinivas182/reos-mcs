import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import {
    MadePostEditor,
    MomentList,
} from '@/components/candidate-studio/candidate-studio';
import type {
    MadePost,
    Moment,
    Target,
} from '@/components/candidate-studio/types';
import { PageHeader } from '@/components/page-header';
import { postJson } from '@/lib/content';
import { check, make, post as postRoute } from '@/routes/candidate-studio';

/**
 * The candidate studio (B13.3; candidate journey, study wins): free
 * study-streak, practice-score and exam-pass posts with the school's or
 * REOS's brand and "Future agent". Every caption is checked: no service
 * advertising before the license is active.
 */
export default function CandidateStudioPage({
    moments,
    targets,
}: {
    moments: Moment[];
    targets: Target[];
}) {
    const { t } = useTranslation();
    const [busy, setBusy] = useState(false);
    const [made, setMade] = useState<MadePost | null>(null);

    const run = async <T,>(work: () => Promise<T>): Promise<T | undefined> => {
        setBusy(true);
        try {
            return await work();
        } catch (e) {
            toast.error(e instanceof Error ? e.message : String(e));

            return undefined;
        } finally {
            setBusy(false);
        }
    };

    return (
        <>
            <Head title={t('studio.candidate.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('studio.candidate.title')}
                    description={t('studio.candidate.purpose')}
                />
                <section aria-labelledby="moments-title" className="space-y-3">
                    <h2 id="moments-title" className="text-lg font-semibold">
                        {t('studio.candidate.moments')}
                    </h2>
                    <MomentList
                        moments={moments}
                        busy={busy}
                        onOpen={(m) =>
                            void run(() =>
                                postJson<{ data: MadePost }>(
                                    make(m.milestone).url,
                                ).then((r) => setMade(r.data)),
                            )
                        }
                    />
                </section>
                {made ? (
                    <MadePostEditor
                        key={made.id}
                        post={made}
                        targets={targets}
                        busy={busy}
                        onCheck={async (text) =>
                            (
                                await postJson<{
                                    data: {
                                        ok: boolean;
                                        issues: {
                                            phrase: string | null;
                                            citation: string;
                                        }[];
                                    };
                                }>(check().url, { text })
                            ).data
                        }
                        onPost={(caption, design, text, chosen) =>
                            void run(async () => {
                                await postJson(postRoute(made.id).url, {
                                    caption,
                                    design,
                                    text,
                                    targets: chosen.map(
                                        ({ connection, channel, id }) => ({
                                            connection,
                                            channel,
                                            id,
                                        }),
                                    ),
                                });
                                toast.success(t('studio.candidate.posted'));
                            })
                        }
                        onCopy={(text) =>
                            void navigator.clipboard
                                .writeText(text)
                                .then(() =>
                                    toast.success(t('studio.candidate.copied')),
                                )
                        }
                    />
                ) : null}
            </div>
        </>
    );
}
