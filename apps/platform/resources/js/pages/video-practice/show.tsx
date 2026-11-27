import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import type { VideoReview } from '@/components/video-practice/types';
import { ReviewView } from '@/components/video-practice/video-practice-view';
import { index as learn } from '@/routes/learn';
import { index } from '@/routes/video-practice';

/** A video practice review (A14.1b). */
export default function VideoReviewPage({ review }: { review: VideoReview }) {
    const { t } = useTranslation();
    const title = t(`videopractice.kinds.${review.kind}`);

    return (
        <>
            <Head title={title} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={title}
                    description={t('videopractice.purpose')}
                    back={
                        <Link
                            href={index().url}
                            className="inline-flex min-h-12 items-center gap-2 text-base text-muted-foreground hover:text-foreground"
                        >
                            <ArrowLeft aria-hidden className="size-4" />
                            {t('videopractice.allPractice')}
                        </Link>
                    }
                    actions={
                        <Button asChild>
                            <Link href={index().url}>
                                {t('videopractice.again')}
                            </Link>
                        </Button>
                    }
                />
                <ReviewView
                    review={review}
                    lessonHref={(lesson) =>
                        learn({ query: { topic: lesson } }).url
                    }
                />
            </div>
        </>
    );
}
