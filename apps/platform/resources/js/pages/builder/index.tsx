import { Head, Link, router } from '@inertiajs/react';
import { BookOpen, Plus } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { statusTone } from '@/components/builder/types';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { nativeSelect } from '@/lib/tenants';
import { cn } from '@/lib/utils';
import { show, store } from '@/routes/builder';

type CourseCard = {
    id: number;
    title: string;
    type: string;
    delivery: string;
    status: 'draft' | 'in_review' | 'live' | 'hidden';
    hours: number | null;
    modules: number;
    lessons: number;
};

/**
 * The course builder's list (A7.2): the school's courses a school admin
 * builds, or the courses an instructor teaches, and New course.
 */
export default function CourseBuilderIndex({
    courses,
    types,
    deliveries,
}: {
    courses: CourseCard[];
    types: string[];
    deliveries: string[];
}) {
    const { t } = useTranslation();
    const [creating, setCreating] = useState(false);
    const [draft, setDraft] = useState({
        title: '',
        type: 'qualifying',
        delivery: 'self_paced',
        hours: '',
    });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const newButton = (
        <Button onClick={() => setCreating(true)}>
            <Plus aria-hidden />
            {t('builder.new')}
        </Button>
    );

    return (
        <>
            <Head title={t('builder.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('builder.title')}
                    description={t('builder.description')}
                    actions={newButton}
                />
                {courses.length === 0 ? (
                    <EmptyState
                        icon={BookOpen}
                        title={t('builder.emptyTitle')}
                        description={t('builder.empty')}
                        action={newButton}
                    />
                ) : (
                    <ul className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {courses.map((course) => (
                            <li key={course.id}>
                                <Link
                                    href={show(course.id).url}
                                    className="block h-full space-y-2 rounded-reos-lg border border-border bg-card p-5 hover:border-primary focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <h2 className="font-semibold">
                                            {course.title}
                                        </h2>
                                        <span
                                            className={cn(
                                                'inline-flex shrink-0 rounded-reos-pill px-2.5 py-0.5 text-sm font-semibold',
                                                statusTone(course.status),
                                            )}
                                        >
                                            {t(
                                                `builder.statuses.${course.status}`,
                                            )}
                                        </span>
                                    </div>
                                    <p className="text-base text-muted-foreground">
                                        {[
                                            t(
                                                `content.types.${course.type as 'ce'}`,
                                            ),
                                            t(
                                                `approvals.deliveries.${course.delivery as 'self_paced'}`,
                                            ),
                                            t('builder.modulesCount', {
                                                count: course.modules,
                                            }),
                                            t('builder.lessonsCount', {
                                                count: course.lessons,
                                            }),
                                        ].join(' · ')}
                                    </p>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
            <Dialog open={creating} onOpenChange={setCreating}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{t('builder.newTitle')}</DialogTitle>
                        <DialogDescription>
                            {t('builder.description')}
                        </DialogDescription>
                    </DialogHeader>
                    <form
                        className="space-y-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            router.post(
                                store().url,
                                {
                                    ...draft,
                                    hours:
                                        draft.hours === ''
                                            ? null
                                            : Number(draft.hours),
                                },
                                { onError: setErrors },
                            );
                        }}
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="course-title">
                                {t('builder.fields.title')}
                            </Label>
                            <Input
                                id="course-title"
                                value={draft.title}
                                maxLength={200}
                                onChange={(e) =>
                                    setDraft({
                                        ...draft,
                                        title: e.target.value,
                                    })
                                }
                            />
                            {errors.title ? (
                                <p className="text-sm text-destructive">
                                    {errors.title}
                                </p>
                            ) : null}
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="course-type">
                                    {t('builder.fields.type')}
                                </Label>
                                <select
                                    id="course-type"
                                    className={nativeSelect}
                                    value={draft.type}
                                    onChange={(e) =>
                                        setDraft({
                                            ...draft,
                                            type: e.target.value,
                                        })
                                    }
                                >
                                    {types.map((type) => (
                                        <option key={type} value={type}>
                                            {t(`content.types.${type as 'ce'}`)}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="course-delivery">
                                    {t('builder.fields.delivery')}
                                </Label>
                                <select
                                    id="course-delivery"
                                    className={nativeSelect}
                                    value={draft.delivery}
                                    onChange={(e) =>
                                        setDraft({
                                            ...draft,
                                            delivery: e.target.value,
                                        })
                                    }
                                >
                                    {deliveries.map((d) => (
                                        <option key={d} value={d}>
                                            {t(
                                                `approvals.deliveries.${d as 'self_paced'}`,
                                            )}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>
                        <div className="grid max-w-40 gap-2">
                            <Label htmlFor="course-hours">
                                {t('builder.fields.hours')}
                            </Label>
                            <Input
                                id="course-hours"
                                type="number"
                                min={0}
                                step="any"
                                value={draft.hours}
                                onChange={(e) =>
                                    setDraft({
                                        ...draft,
                                        hours: e.target.value,
                                    })
                                }
                            />
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setCreating(false)}
                            >
                                {t('builder.cancel')}
                            </Button>
                            <Button type="submit">{t('builder.create')}</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
