import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Lock } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { index as billing } from '@/routes/billing';
import { index } from '@/routes/learn';

/** A lesson that needs a plan (B5.1): every course's first lesson is free; See plans opens Plan and billing. */
export default function LockedLesson({
    lesson,
}: {
    lesson: { id: number; title: string; course: string };
}) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={lesson.title} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={lesson.title}
                    description={lesson.course}
                    back={
                        <Link
                            href={index()}
                            className="inline-flex min-h-touch items-center gap-2 font-medium text-primary underline-offset-4 hover:underline"
                        >
                            <ArrowLeft aria-hidden className="size-5" />
                            {t('learn.lesson.back')}
                        </Link>
                    }
                />
                <EmptyState
                    icon={Lock}
                    title={t('billing.locked.title')}
                    description={t('billing.locked.body', {
                        lesson: lesson.title,
                        course: lesson.course,
                    })}
                    action={
                        <Button asChild>
                            <Link href={billing()}>
                                {t('billing.locked.see')}
                            </Link>
                        </Button>
                    }
                />
            </div>
        </>
    );
}
