import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/page-header';
import type { VideoOverview } from '@/components/video-practice/types';
import {
    VideoRecentList,
    VideoRecorder,
} from '@/components/video-practice/video-practice-view';
import { review, show } from '@/routes/video-practice';

/**
 * Video practice (A14.1b): record a listing pitch or a video intro in the
 * browser, correct the words, and get a review. The video never leaves
 * the browser.
 */
export default function VideoPracticePage({
    kinds,
    max_seconds,
    recent,
}: VideoOverview) {
    const { t } = useTranslation();
    const [busy, setBusy] = useState(false);

    return (
        <>
            <Head title={t('videopractice.title')} />
            <div className="space-y-8 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('videopractice.title')}
                    description={t('videopractice.purpose')}
                />
                <VideoRecorder
                    kinds={kinds}
                    maxSeconds={max_seconds}
                    busy={busy}
                    onReview={(kind, transcript, seconds) => {
                        setBusy(true);
                        router.post(
                            review().url,
                            { kind, transcript, duration_seconds: seconds },
                            { onFinish: () => setBusy(false) },
                        );
                    }}
                />
                <section aria-labelledby="video-recent" className="space-y-3">
                    <h2 id="video-recent" className="text-lg font-semibold">
                        {t('videopractice.recent')}
                    </h2>
                    <VideoRecentList
                        recent={recent}
                        href={(id) => show(id).url}
                    />
                    <p className="text-base text-muted-foreground">
                        {t('videopractice.private')}
                    </p>
                </section>
            </div>
        </>
    );
}
