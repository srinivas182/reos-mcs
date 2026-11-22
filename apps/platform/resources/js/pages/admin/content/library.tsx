import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, BookOpen, Plus, Search } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { CourseStatusBadge } from '@/components/course-status-badge';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { KnowledgeResults } from '@/components/knowledge-results';
import { PageHeader } from '@/components/page-header';
import { StatePicker } from '@/components/state-picker';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import type {
    CourseStatus,
    CourseType,
    SearchHit,
    StateOption,
} from '@/lib/content';
import { nativeSelect } from '@/lib/tenants';
import { index as contentIndex, library } from '@/routes/content';
import { show, store } from '@/routes/content/courses';

type CourseRow = {
    id: number;
    title: string;
    type: CourseType;
    states: string[] | null;
    hours: number | null;
    status: CourseStatus;
    lessons: number;
    missing_approvals: string[];
    updated_at: string | null;
};

type Props = {
    courses: CourseRow[];
    types: CourseType[];
    statuses: CourseStatus[];
    states: StateOption[];
    search: {
        q: string;
        state: string | null;
        language: string | null;
        drafts: boolean;
        results: SearchHit[] | null;
    };
};

/**
 * Content library (A5.3; opened from screen 63, Content and approvals):
 * REOS courses with their type, states, lessons and status, New course,
 * and a search that tries the knowledge base as learners in a state see it.
 */
export default function ContentLibrary({
    courses,
    types,
    states,
    search,
}: Props) {
    const { t } = useTranslation();
    const [creating, setCreating] = useState(false);
    const name = (code: string) =>
        states.find((s) => s.code === code)?.name ?? code;

    return (
        <>
            <Head title={t('content.library.title')} />

            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('content.library.title')}
                    description={t('content.library.description')}
                    back={
                        <Link
                            href={contentIndex()}
                            className="inline-flex min-h-touch items-center gap-2 font-medium text-primary underline-offset-4 hover:underline"
                        >
                            <ArrowLeft aria-hidden className="size-5" />
                            {t('content.library.back')}
                        </Link>
                    }
                    actions={
                        <Button onClick={() => setCreating(true)}>
                            <Plus aria-hidden />
                            {t('content.courses.new')}
                        </Button>
                    }
                />

                {courses.length === 0 ? (
                    <EmptyState
                        icon={BookOpen}
                        title={t('content.library.emptyTitle')}
                        description={t('content.library.empty')}
                        action={
                            <Button onClick={() => setCreating(true)}>
                                {t('content.courses.new')}
                            </Button>
                        }
                    />
                ) : (
                    <div className="overflow-x-auto rounded-reos-lg border border-border bg-card">
                        <table className="w-full text-left text-base">
                            <caption className="sr-only">
                                {t('content.library.title')}
                            </caption>
                            <thead className="text-sm text-muted-foreground uppercase">
                                <tr className="border-b border-border">
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-semibold"
                                    >
                                        {t('content.columns.course')}
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-semibold"
                                    >
                                        {t('content.columns.type')}
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-semibold"
                                    >
                                        {t('content.columns.states')}
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 text-right font-semibold"
                                    >
                                        {t('content.columns.hours')}
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 text-right font-semibold"
                                    >
                                        {t('content.columns.lessons')}
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-4 py-3 font-semibold"
                                    >
                                        {t('content.columns.status')}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {courses.map((course) => (
                                    <tr
                                        key={course.id}
                                        className="border-b border-border last:border-b-0"
                                    >
                                        <th
                                            scope="row"
                                            className="px-4 py-2 font-medium"
                                        >
                                            <Link
                                                href={show(course.id)}
                                                className="inline-flex min-h-12 items-center text-primary underline-offset-4 hover:underline"
                                            >
                                                {course.title}
                                            </Link>
                                        </th>
                                        <td className="px-4 py-2">
                                            {t(`content.types.${course.type}`)}
                                        </td>
                                        <td className="px-4 py-2">
                                            {course.states === null
                                                ? t('content.everyState')
                                                : course.states
                                                      .map(name)
                                                      .join(', ')}
                                        </td>
                                        <td className="px-4 py-2 text-right">
                                            {course.hours ?? '—'}
                                        </td>
                                        <td className="px-4 py-2 text-right">
                                            {course.lessons}
                                        </td>
                                        <td className="px-4 py-2">
                                            <CourseStatusBadge
                                                status={course.status}
                                                noApproval={
                                                    course.missing_approvals
                                                        .length > 0
                                                }
                                            />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <section className="space-y-4 rounded-reos-lg border border-border bg-card p-5">
                    <div className="space-y-1">
                        <h2 className="font-semibold">
                            {t('content.search.title')}
                        </h2>
                        <p className="text-body text-muted-foreground">
                            {t('content.search.intro')}
                        </p>
                    </div>
                    <Form
                        action={library().url}
                        method="get"
                        options={{ preserveScroll: true, preserveState: true }}
                        className="flex flex-wrap items-end gap-3"
                    >
                        <div className="grid min-w-64 flex-1 gap-2">
                            <Label htmlFor="kb-q">
                                {t('content.search.label')}
                            </Label>
                            <Input
                                id="kb-q"
                                name="q"
                                type="search"
                                defaultValue={search.q}
                                maxLength={300}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="kb-state">
                                {t('content.search.state')}
                            </Label>
                            <select
                                id="kb-state"
                                name="state"
                                className={nativeSelect}
                                defaultValue={search.state ?? ''}
                            >
                                <option value="">
                                    {t('content.everyState')}
                                </option>
                                {states.map((s) => (
                                    <option key={s.code} value={s.code}>
                                        {s.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="kb-language">
                                {t('content.search.language')}
                            </Label>
                            <select
                                id="kb-language"
                                name="language"
                                className={nativeSelect}
                                defaultValue={search.language ?? ''}
                            >
                                <option value="">
                                    {t('content.search.anyLanguage')}
                                </option>
                                <option value="en">
                                    {t('content.languages.en')}
                                </option>
                                <option value="es">
                                    {t('content.languages.es')}
                                </option>
                            </select>
                        </div>
                        <label className="flex min-h-12 items-center gap-3">
                            <Checkbox
                                name="drafts"
                                value="1"
                                defaultChecked={search.drafts}
                            />
                            {t('content.search.drafts')}
                        </label>
                        <Button type="submit">
                            <Search aria-hidden />
                            {t('content.search.button')}
                        </Button>
                    </Form>
                    {search.results !== null ? (
                        <KnowledgeResults
                            query={search.q}
                            results={search.results}
                        />
                    ) : null}
                </section>
            </div>

            <NewCourseDialog
                open={creating}
                onOpenChange={setCreating}
                types={types}
                states={states}
            />
        </>
    );
}

function NewCourseDialog({
    open,
    onOpenChange,
    types,
    states,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    types: CourseType[];
    states: StateOption[];
}) {
    const { t } = useTranslation();
    const [chosen, setChosen] = useState<string[]>([]);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{t('content.courses.new')}</DialogTitle>
                    <DialogDescription>
                        {t('content.courses.newIntro')}
                    </DialogDescription>
                </DialogHeader>
                <Form {...store.form()} className="space-y-4">
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="course-title">
                                    {t('content.courses.fields.title')}
                                </Label>
                                <Input
                                    id="course-title"
                                    name="title"
                                    required
                                    maxLength={200}
                                    autoComplete="off"
                                />
                                <InputError message={errors.title} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="course-type">
                                    {t('content.courses.fields.type')}
                                </Label>
                                <select
                                    id="course-type"
                                    name="type"
                                    required
                                    className={nativeSelect}
                                    defaultValue=""
                                >
                                    <option value="">
                                        {t('content.choose')}
                                    </option>
                                    {types.map((type) => (
                                        <option key={type} value={type}>
                                            {t(`content.types.${type}`)}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.type} />
                            </div>
                            <StatePicker
                                states={states}
                                value={chosen}
                                onChange={setChosen}
                                error={errors.states ?? errors['states.0']}
                            />
                            <div className="grid gap-2">
                                <Label htmlFor="course-hours">
                                    {t('content.courses.fields.hours')}
                                </Label>
                                <Input
                                    id="course-hours"
                                    name="hours"
                                    type="number"
                                    min={0}
                                    max={500}
                                    step={0.5}
                                    inputMode="decimal"
                                />
                                <InputError message={errors.hours} />
                            </div>
                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => onOpenChange(false)}
                                >
                                    {t('common.cancel')}
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {t('content.courses.create')}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
