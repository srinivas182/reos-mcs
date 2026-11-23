import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Layers } from 'lucide-react';
import { useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import { AiPlan } from '@/components/builder/ai-plan';
import { BuilderSettingsCard } from '@/components/builder/builder-settings';
import { LessonList } from '@/components/builder/lesson-list';
import { ModuleList } from '@/components/builder/module-list';
import { SeatTimeNotice } from '@/components/builder/seat-time';
import { SourceMaterial } from '@/components/builder/source-material';
import type {
    BuilderCourse,
    BuilderModule,
    BuilderSettings,
    ModuleRow,
    RoleRule,
} from '@/components/builder/types';
import { statusTone } from '@/components/builder/types';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { index, status } from '@/routes/builder';

/**
 * Course builder (instructor web screen 43, A7.2): the module list with
 * status, the module's source material, what AI will make, the settings and
 * the lessons with their status; Generate module drafts AI lessons
 * (A7.3) and the page checks on them while they're made. Nothing is published without approval;
 * seat-time changes go to the school for TREC.
 */
export default function CourseBuilderCourse({
    course,
    modules,
    module,
    settings,
    presenters,
    roles,
    vendor_live,
}: {
    course: BuilderCourse;
    modules: ModuleRow[];
    module: BuilderModule | null;
    settings: BuilderSettings;
    presenters: { value: string; label: string }[];
    roles: RoleRule[];
    vendor_live: boolean;
    vendors: string[];
}) {
    const { t } = useTranslation();
    const generating =
        module?.lessons.some((l) => l.status === 'generating') ?? false;

    // While AI lessons are being made, check every 15 seconds (A7.3).
    useEffect(() => {
        if (!generating) {
            return;
        }

        const timer = window.setInterval(
            () => router.reload({ only: ['module', 'modules'] }),
            15000,
        );

        return () => window.clearInterval(timer);
    }, [generating]);

    return (
        <>
            <Head title={course.title} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={course.title}
                    description={t('builder.courseDescription')}
                    back={
                        <Link
                            href={index()}
                            className="inline-flex min-h-touch items-center gap-2 font-medium text-primary underline-offset-4 hover:underline"
                        >
                            <ArrowLeft aria-hidden className="size-5" />
                            {t('builder.back')}
                        </Link>
                    }
                    actions={
                        <>
                            <span
                                className={cn(
                                    'inline-flex items-center rounded-reos-pill px-3 py-1 text-sm font-semibold',
                                    statusTone(course.status),
                                )}
                            >
                                {t(`builder.statuses.${course.status}`)}
                            </span>
                            {course.can_publish ? (
                                <Button
                                    variant={
                                        course.status === 'live'
                                            ? 'outline'
                                            : 'default'
                                    }
                                    onClick={() =>
                                        router.put(
                                            status(course.id).url,
                                            {
                                                status:
                                                    course.status === 'live'
                                                        ? 'hidden'
                                                        : 'live',
                                            },
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    {course.status === 'live'
                                        ? t('builder.unpublish')
                                        : t('builder.publish')}
                                </Button>
                            ) : null}
                        </>
                    }
                />
                <SeatTimeNotice course={course} />
                <div className="grid gap-6 lg:grid-cols-[18rem_1fr]">
                    <ModuleList
                        courseId={course.id}
                        modules={modules}
                        current={module?.id ?? null}
                    />
                    {module === null ? (
                        <EmptyState
                            icon={Layers}
                            title={t('builder.noModulesTitle')}
                            description={t('builder.noModules')}
                        />
                    ) : (
                        <div className="min-w-0 space-y-6">
                            <h2 className="text-xl font-semibold">
                                {module.title}
                            </h2>
                            <SourceMaterial
                                moduleId={module.id}
                                sources={module.sources}
                                roles={roles}
                            />
                            <div className="grid gap-6 xl:grid-cols-2">
                                <AiPlan
                                    moduleId={module.id}
                                    plan={module.plan}
                                    generating={generating}
                                    vendorLive={vendor_live}
                                />
                                <BuilderSettingsCard
                                    courseId={course.id}
                                    settings={settings}
                                    presenters={presenters}
                                />
                            </div>
                            <LessonList
                                moduleId={module.id}
                                lessons={module.lessons}
                            />
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
