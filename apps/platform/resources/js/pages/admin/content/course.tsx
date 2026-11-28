import { Form, Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, FileText, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { CourseChecks } from '@/components/course-checks';
import { CourseStatusBadge } from '@/components/course-status-badge';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { PageHeader } from '@/components/page-header';
import { StatePicker } from '@/components/state-picker';
import { Button } from '@/components/ui/button';

import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type {
    CourseChecks as Checks,
    CourseStatus,
    CourseType,
    IngestStatus,
    Language,
    StateOption,
} from '@/lib/content';
import { nativeSelect } from '@/lib/tenants';
import { undoToast } from '@/lib/undo-toast';
import { cn } from '@/lib/utils';
import { library } from '@/routes/content';
import {
    destroy,
    restore,
    status as setStatus,
    update,
} from '@/routes/content/courses';
import {
    show as showLesson,
    store as storeLesson,
} from '@/routes/content/lessons';
import {
    destroy as destroyModule,
    store as storeModule,
} from '@/routes/content/modules';

type Approval = { number: string; expires_on: string | null };

type SkillCode =
    | 'listing_presentation'
    | 'discovery_questions'
    | 'objection_handling'
    | 'clear_speaking'
    | 'asking_for_business';
type ModeCode = 'launch' | 'grow' | 'lead' | 'own';

type Props = {
    course: {
        id: number;
        title: string;
        type: CourseType;
        description: string | null;
        states: string[] | null;
        hours: number | null;
        skills: SkillCode[];
        modes: ModeCode[];
        approvals: Record<string, Approval>;
        status: CourseStatus;
        status_note: string | null;
        needs_approval: boolean;
        missing_approvals: string[];
        published_at: string | null;
        passages: number;
        embedded: number;
    };
    modules: { id: number; title: string }[];
    lessons: {
        id: number;
        title: string;
        module_id: number | null;
        language: Language;
        minutes: number | null;
        ingest_status: IngestStatus;
        files: number;
        passages: number;
    }[];
    checks: Checks;
    types: CourseType[];
    skills: SkillCode[];
    modes: ModeCode[];
    states: StateOption[];
};

const STATUSES: CourseStatus[] = ['draft', 'in_review', 'live', 'hidden'];

/**
 * One course (A5.3): details, states and the regulator's approval number for
 * each, status, and its modules and lessons. A CE or qualifying course can't
 * go live in a state without an approval number.
 */
export default function CoursePage({
    course,
    modules,
    lessons,
    checks,
    types,
    states,
    skills,
    modes,
}: Props) {
    const { t } = useTranslation();
    const [chosen, setChosen] = useState<string[]>(course.states ?? []);
    const [type, setType] = useState<CourseType>(course.type);
    const needsApproval = type === 'qualifying' || type === 'ce';
    const name = (code: string) =>
        states.find((s) => s.code === code)?.name ?? code;
    const groups = [
        ...modules.map((m) => ({ id: m.id as number | null, title: m.title })),
        { id: null, title: t('content.modules.none') },
    ]
        .map((group) => ({
            ...group,
            lessons: lessons.filter((l) => l.module_id === group.id),
        }))
        .filter((group) => group.id !== null || group.lessons.length > 0);

    const remove = () =>
        router.delete(destroy(course.id).url, {
            onSuccess: () =>
                undoToast({
                    message: t('content.courses.deleted', {
                        title: course.title,
                    }),
                    onUndo: () => router.post(restore(course.id).url),
                }),
        });

    const changeStatus = (status: CourseStatus) =>
        router.put(
            setStatus(course.id).url,
            { status },
            {
                preserveScroll: true,
                onError: (errors) => {
                    if (errors.status) toast.error(errors.status);
                },
            },
        );

    return (
        <>
            <Head title={course.title} />

            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={course.title}
                    description={t('content.courses.description', {
                        type: t(`content.types.${course.type}`),
                        states:
                            course.states === null
                                ? t('content.everyState')
                                : course.states.map(name).join(', '),
                    })}
                    back={
                        <Link
                            href={library()}
                            className="inline-flex min-h-touch items-center gap-2 font-medium text-primary underline-offset-4 hover:underline"
                        >
                            <ArrowLeft aria-hidden className="size-5" />
                            {t('content.library.title')}
                        </Link>
                    }
                    actions={
                        <>
                            <CourseStatusBadge
                                status={course.status}
                                noApproval={course.missing_approvals.length > 0}
                            />
                            <Button variant="outline" onClick={remove}>
                                <Trash2 aria-hidden />
                                {t('content.courses.delete')}
                            </Button>
                        </>
                    }
                />

                <div className="grid gap-6 lg:grid-cols-3">
                    <section className="space-y-4 rounded-reos-lg border border-border bg-card p-5 lg:col-span-2">
                        <h2 className="font-semibold">
                            {t('content.courses.details')}
                        </h2>
                        <Form
                            {...update.form(course.id)}
                            options={{ preserveScroll: true }}
                            className="space-y-4"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="grid gap-2 sm:col-span-2">
                                            <Label htmlFor="title">
                                                {t(
                                                    'content.courses.fields.title',
                                                )}
                                            </Label>
                                            <Input
                                                id="title"
                                                name="title"
                                                defaultValue={course.title}
                                                required
                                                maxLength={200}
                                            />
                                            <InputError
                                                message={errors.title}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="type">
                                                {t(
                                                    'content.courses.fields.type',
                                                )}
                                            </Label>
                                            <select
                                                id="type"
                                                name="type"
                                                className={nativeSelect}
                                                value={type}
                                                onChange={(e) =>
                                                    setType(
                                                        e.target
                                                            .value as CourseType,
                                                    )
                                                }
                                            >
                                                {types.map((value) => (
                                                    <option
                                                        key={value}
                                                        value={value}
                                                    >
                                                        {t(
                                                            `content.types.${value}`,
                                                        )}
                                                    </option>
                                                ))}
                                            </select>
                                            <InputError message={errors.type} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="hours">
                                                {t(
                                                    'content.courses.fields.hours',
                                                )}
                                            </Label>
                                            <Input
                                                id="hours"
                                                name="hours"
                                                type="number"
                                                min={0}
                                                max={500}
                                                step={0.5}
                                                defaultValue={
                                                    course.hours ?? ''
                                                }
                                            />
                                            <InputError
                                                message={errors.hours}
                                            />
                                        </div>
                                        <div className="grid gap-2 sm:col-span-2">
                                            <Label htmlFor="description">
                                                {t(
                                                    'content.courses.fields.description',
                                                )}
                                            </Label>
                                            <Textarea
                                                id="description"
                                                name="description"
                                                rows={3}
                                                defaultValue={
                                                    course.description ?? ''
                                                }
                                                maxLength={5000}
                                            />
                                            <InputError
                                                message={errors.description}
                                            />
                                        </div>
                                    </div>

                                    {/* What it teaches and who it suits, for agents' course picks (A14.2). */}
                                    <fieldset className="grid gap-2">
                                        <legend className="mb-1 text-sm font-medium">
                                            {t('content.courses.fields.skills')}
                                        </legend>
                                        <p className="text-sm text-muted-foreground">
                                            {t(
                                                'content.courses.fields.skillsHint',
                                            )}
                                        </p>
                                        <input
                                            type="hidden"
                                            name="skills"
                                            value=""
                                        />
                                        <div className="grid gap-2 sm:grid-cols-2">
                                            {skills.map((skill) => (
                                                <label
                                                    key={skill}
                                                    className="flex min-h-12 items-center gap-3 text-base"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        name="skills[]"
                                                        value={skill}
                                                        defaultChecked={course.skills.includes(
                                                            skill,
                                                        )}
                                                        className="size-5"
                                                    />
                                                    {t(
                                                        `roleplay.lessons.${skill}`,
                                                    )}
                                                </label>
                                            ))}
                                        </div>
                                        <InputError
                                            message={
                                                errors.skills ??
                                                errors['skills.0']
                                            }
                                        />
                                    </fieldset>
                                    <fieldset className="grid gap-2">
                                        <legend className="mb-1 text-sm font-medium">
                                            {t('content.courses.fields.modes')}
                                        </legend>
                                        <input
                                            type="hidden"
                                            name="modes"
                                            value=""
                                        />
                                        <div className="flex flex-wrap gap-4">
                                            {modes.map((mode) => (
                                                <label
                                                    key={mode}
                                                    className="flex min-h-12 items-center gap-3 text-base"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        name="modes[]"
                                                        value={mode}
                                                        defaultChecked={course.modes.includes(
                                                            mode,
                                                        )}
                                                        className="size-5"
                                                    />
                                                    {t(
                                                        `agents.modes.goals.${mode}.name`,
                                                    )}
                                                </label>
                                            ))}
                                        </div>
                                        <InputError
                                            message={
                                                errors.modes ??
                                                errors['modes.0']
                                            }
                                        />
                                    </fieldset>

                                    <StatePicker
                                        states={states}
                                        value={chosen}
                                        onChange={setChosen}
                                        error={
                                            errors.states ?? errors['states.0']
                                        }
                                    />

                                    {needsApproval && chosen.length > 0 ? (
                                        <fieldset className="grid gap-3">
                                            <legend className="mb-1 text-sm font-medium">
                                                {t('content.approvals.title')}
                                            </legend>
                                            <p className="text-sm text-muted-foreground">
                                                {t('content.approvals.intro')}
                                            </p>
                                            {chosen.map((code) => (
                                                <div
                                                    key={code}
                                                    className="grid gap-3 sm:grid-cols-[8rem_1fr_12rem] sm:items-end"
                                                >
                                                    <p className="font-medium sm:pb-3">
                                                        {name(code)}
                                                    </p>
                                                    <div className="grid gap-2">
                                                        <Label
                                                            htmlFor={`approval-${code}`}
                                                        >
                                                            {t(
                                                                'content.approvals.number',
                                                            )}
                                                        </Label>
                                                        <Input
                                                            id={`approval-${code}`}
                                                            name={`approvals[${code}][number]`}
                                                            defaultValue={
                                                                course
                                                                    .approvals[
                                                                    code
                                                                ]?.number ?? ''
                                                            }
                                                            maxLength={60}
                                                        />
                                                    </div>
                                                    <div className="grid gap-2">
                                                        <Label
                                                            htmlFor={`expires-${code}`}
                                                        >
                                                            {t(
                                                                'content.approvals.expires',
                                                            )}
                                                        </Label>
                                                        <Input
                                                            id={`expires-${code}`}
                                                            name={`approvals[${code}][expires_on]`}
                                                            type="date"
                                                            defaultValue={
                                                                course
                                                                    .approvals[
                                                                    code
                                                                ]?.expires_on ??
                                                                ''
                                                            }
                                                        />
                                                    </div>
                                                </div>
                                            ))}
                                            <InputError
                                                message={errors.approvals}
                                            />
                                        </fieldset>
                                    ) : null}

                                    <Button type="submit" disabled={processing}>
                                        {t('content.save')}
                                    </Button>
                                </>
                            )}
                        </Form>
                    </section>

                    <div className="space-y-6">
                        <CourseChecks course={course} checks={checks} />
                        <section className="space-y-3 rounded-reos-lg border border-border bg-card p-5">
                            <h2 className="font-semibold">
                                {t('content.courses.statusTitle')}
                            </h2>
                            <div className="grid gap-2">
                                <Label htmlFor="status">
                                    {t('content.columns.status')}
                                </Label>
                                <select
                                    id="status"
                                    className={nativeSelect}
                                    value={course.status}
                                    onChange={(e) =>
                                        changeStatus(
                                            e.target.value as CourseStatus,
                                        )
                                    }
                                >
                                    {STATUSES.map((value) => (
                                        <option key={value} value={value}>
                                            {t(`content.status.${value}`)}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            {course.missing_approvals.length > 0 ? (
                                <p className="rounded-reos-md bg-reos-tone-warning-bg p-3 text-sm text-reos-tone-warning-fg">
                                    {t('content.approvals.missing', {
                                        states: course.missing_approvals
                                            .map(name)
                                            .join(', '),
                                    })}
                                </p>
                            ) : null}
                            <p className="text-sm text-muted-foreground">
                                {t('content.courses.statusHelp')}
                            </p>
                        </section>

                        <section className="space-y-2 rounded-reos-lg border border-border bg-card p-5">
                            <h2 className="font-semibold">
                                {t('content.knowledge.title')}
                            </h2>
                            <p className="text-body">
                                {t('content.knowledge.passages', {
                                    count: course.passages,
                                })}
                            </p>
                            <p className="text-sm text-muted-foreground">
                                {t('content.knowledge.embedded', {
                                    count: course.embedded,
                                    total: course.passages,
                                })}
                            </p>
                        </section>
                    </div>
                </div>

                <section className="space-y-4 rounded-reos-lg border border-border bg-card p-5">
                    <h2 className="font-semibold">
                        {t('content.modules.title')}
                    </h2>
                    {lessons.length === 0 && modules.length === 0 ? (
                        <EmptyState
                            icon={FileText}
                            title={t('content.lessons.emptyTitle')}
                            description={t('content.lessons.empty')}
                        />
                    ) : (
                        groups.map((group) => (
                            <div key={group.id ?? 'none'} className="space-y-2">
                                <div className="flex items-center justify-between gap-3">
                                    <h3 className="font-medium">
                                        {group.title}
                                    </h3>
                                    {group.id !== null ? (
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            aria-label={t(
                                                'content.modules.remove',
                                                { title: group.title },
                                            )}
                                            onClick={() =>
                                                router.delete(
                                                    destroyModule(group.id!)
                                                        .url,
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            <Trash2 aria-hidden />
                                        </Button>
                                    ) : null}
                                </div>
                                {group.lessons.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        {t('content.modules.empty')}
                                    </p>
                                ) : (
                                    <ul className="divide-y divide-border rounded-reos-md border border-border">
                                        {group.lessons.map((lesson) => (
                                            <li
                                                key={lesson.id}
                                                className="flex flex-wrap items-center gap-3 px-4 py-2"
                                            >
                                                <Link
                                                    href={showLesson(lesson.id)}
                                                    className="inline-flex min-h-12 flex-1 items-center font-medium text-primary underline-offset-4 hover:underline"
                                                >
                                                    {lesson.title}
                                                </Link>
                                                <span className="text-sm text-muted-foreground">
                                                    {[
                                                        t(
                                                            `content.languages.${lesson.language}`,
                                                        ),
                                                        t(
                                                            'content.lessons.files',
                                                            {
                                                                count: lesson.files,
                                                            },
                                                        ),
                                                        t(
                                                            'content.knowledge.passages',
                                                            {
                                                                count: lesson.passages,
                                                            },
                                                        ),
                                                    ].join(' · ')}
                                                </span>
                                                <span
                                                    className={cn(
                                                        'rounded-reos-pill px-3 py-1 text-sm font-semibold',
                                                        lesson.ingest_status ===
                                                            'failed'
                                                            ? 'bg-reos-tone-danger-bg text-reos-tone-danger-fg'
                                                            : lesson.ingest_status ===
                                                                'ready'
                                                              ? 'bg-reos-tone-success-bg text-reos-tone-success-fg'
                                                              : 'bg-reos-tone-info-bg text-reos-tone-info-fg',
                                                    )}
                                                >
                                                    {t(
                                                        `content.ingest.${lesson.ingest_status}`,
                                                    )}
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </div>
                        ))
                    )}

                    <div className="grid gap-6 border-t border-border pt-4 md:grid-cols-2">
                        <Form
                            {...storeLesson.form(course.id)}
                            className="space-y-3"
                            resetOnSuccess
                        >
                            {({ errors, processing }) => (
                                <>
                                    <h3 className="font-medium">
                                        {t('content.lessons.add')}
                                    </h3>
                                    <div className="grid gap-2">
                                        <Label htmlFor="lesson-title">
                                            {t('content.lessons.fields.title')}
                                        </Label>
                                        <Input
                                            id="lesson-title"
                                            name="title"
                                            required
                                            maxLength={200}
                                        />
                                        <InputError message={errors.title} />
                                    </div>
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="lesson-module">
                                                {t(
                                                    'content.lessons.fields.module',
                                                )}
                                            </Label>
                                            <select
                                                id="lesson-module"
                                                name="course_module_id"
                                                className={nativeSelect}
                                                defaultValue=""
                                            >
                                                <option value="">
                                                    {t('content.modules.none')}
                                                </option>
                                                {modules.map((m) => (
                                                    <option
                                                        key={m.id}
                                                        value={m.id}
                                                    >
                                                        {m.title}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="lesson-language">
                                                {t(
                                                    'content.lessons.fields.language',
                                                )}
                                            </Label>
                                            <select
                                                id="lesson-language"
                                                name="language"
                                                className={nativeSelect}
                                                defaultValue="en"
                                            >
                                                <option value="en">
                                                    {t('content.languages.en')}
                                                </option>
                                                <option value="es">
                                                    {t('content.languages.es')}
                                                </option>
                                            </select>
                                        </div>
                                    </div>
                                    <Button type="submit" disabled={processing}>
                                        <Plus aria-hidden />
                                        {t('content.lessons.add')}
                                    </Button>
                                </>
                            )}
                        </Form>
                        <Form
                            {...storeModule.form(course.id)}
                            options={{ preserveScroll: true }}
                            className="space-y-3"
                            resetOnSuccess
                        >
                            {({ errors, processing }) => (
                                <>
                                    <h3 className="font-medium">
                                        {t('content.modules.add')}
                                    </h3>
                                    <div className="grid gap-2">
                                        <Label htmlFor="module-title">
                                            {t('content.modules.fields.title')}
                                        </Label>
                                        <Input
                                            id="module-title"
                                            name="title"
                                            required
                                            maxLength={200}
                                        />
                                        <InputError message={errors.title} />
                                    </div>
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        disabled={processing}
                                    >
                                        <Plus aria-hidden />
                                        {t('content.modules.add')}
                                    </Button>
                                </>
                            )}
                        </Form>
                    </div>
                </section>
            </div>
        </>
    );
}
