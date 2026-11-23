import { Form, Head, Link, usePage } from '@inertiajs/react';
import { BookOpen, Search } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { KnowledgeResults } from '@/components/knowledge-results';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { CourseType, Language, SearchHit } from '@/lib/content';
import { index, lesson as openLesson } from '@/routes/learn';

type Course = {
    id: number;
    title: string;
    type: CourseType;
    hours: number | null;
    /** The approval in force in the state (CE and qualifying only, A7.1). */
    credit: { number: string; hours: number | null } | null;
    lessons: {
        id: number;
        title: string;
        minutes: number | null;
        language: Language;
    }[];
};

/** Where a search result opens: the lesson at its page, moment or heading. */
export const learnerHref = (hit: SearchHit) =>
    openLesson(hit.lesson_id, {
        query: {
            ...(hit.page !== null ? { page: hit.page } : {}),
            ...(hit.seconds !== null ? { t: hit.seconds } : {}),
            ...(hit.heading !== null &&
            hit.page === null &&
            hit.seconds === null
                ? { heading: hit.heading }
                : {}),
        },
    });

/**
 * Learn (A5.3b; candidate "Courses", agent "Learn and CE"): the live courses
 * for the person's state, and a search inside every lesson whose results
 * open at the page, moment or heading.
 */
export default function Learn({
    courses,
    search,
}: {
    courses: Course[];
    search: {
        q: string;
        language: Language | null;
        results: SearchHit[] | null;
    };
}) {
    const { t } = useTranslation();
    const { region } = usePage().props;
    const state = region?.name ?? '';

    return (
        <>
            <Head title={t('learn.title')} />

            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('learn.title')}
                    description={t('learn.description', { state })}
                />

                <section className="space-y-4 rounded-reos-lg border border-border bg-card p-5">
                    <Form
                        action={index().url}
                        method="get"
                        options={{ preserveScroll: true }}
                        className="flex flex-wrap items-end gap-3"
                    >
                        <div className="grid min-w-64 flex-1 gap-2">
                            <Label htmlFor="learn-q">
                                {t('learn.searchLabel')}
                            </Label>
                            <Input
                                id="learn-q"
                                name="q"
                                type="search"
                                defaultValue={search.q}
                                maxLength={300}
                            />
                        </div>
                        <Button type="submit">
                            <Search aria-hidden />
                            {t('learn.searchButton')}
                        </Button>
                    </Form>
                    {search.results !== null ? (
                        <KnowledgeResults
                            query={search.q}
                            results={search.results}
                            lessonHref={learnerHref}
                        />
                    ) : null}
                </section>

                <section className="space-y-3">
                    <h2 className="text-lg font-semibold">
                        {t('learn.coursesTitle')}
                    </h2>
                    {courses.length === 0 ? (
                        <EmptyState
                            icon={BookOpen}
                            title={t('learn.emptyTitle', { state })}
                            description={t('learn.empty')}
                        />
                    ) : (
                        <div className="grid gap-4 md:grid-cols-2">
                            {courses.map((course) => (
                                <article
                                    key={course.id}
                                    className="space-y-3 rounded-reos-lg border border-border bg-card p-5"
                                >
                                    <div className="space-y-1">
                                        <h3 className="font-semibold">
                                            {course.title}
                                        </h3>
                                        <p className="text-sm text-muted-foreground">
                                            {[
                                                // No CE or qualifying label without an approval in force (A7.1).
                                                course.credit
                                                    ? t('learn.credit', {
                                                          type: t(
                                                              `content.types.${course.type}`,
                                                          ),
                                                          number: course.credit
                                                              .number,
                                                      })
                                                    : course.type === 'ce' ||
                                                        course.type ===
                                                            'qualifying'
                                                      ? t('learn.noCredit', {
                                                            state,
                                                        })
                                                      : t(
                                                            `content.types.${course.type}`,
                                                        ),
                                                course.hours !== null
                                                    ? t('learn.hours', {
                                                          count: course.hours,
                                                      })
                                                    : null,
                                                t('learn.lessons', {
                                                    count: course.lessons
                                                        .length,
                                                }),
                                            ]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </p>
                                    </div>
                                    <ul className="divide-y divide-border">
                                        {course.lessons.map((item) => (
                                            <li key={item.id}>
                                                <Link
                                                    href={openLesson(item.id)}
                                                    className="flex min-h-12 items-center justify-between gap-3 py-2 font-medium text-primary underline-offset-4 hover:underline"
                                                >
                                                    {item.title}
                                                    <span className="text-sm font-normal text-muted-foreground">
                                                        {[
                                                            item.minutes !==
                                                            null
                                                                ? t(
                                                                      'learn.minutes',
                                                                      {
                                                                          count: item.minutes,
                                                                      },
                                                                  )
                                                                : null,
                                                            item.language !==
                                                            'en'
                                                                ? t(
                                                                      `content.languages.${item.language}`,
                                                                  )
                                                                : null,
                                                        ]
                                                            .filter(Boolean)
                                                            .join(' · ')}
                                                    </span>
                                                </Link>
                                            </li>
                                        ))}
                                    </ul>
                                </article>
                            ))}
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}
