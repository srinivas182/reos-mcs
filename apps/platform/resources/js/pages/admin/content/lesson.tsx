import { Form, Head, Link, router, usePoll } from '@inertiajs/react';
import { ArrowLeft, RefreshCw, Trash2 } from 'lucide-react';
import { useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import { LessonFiles } from '@/components/lesson-files';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type {
    IngestStatus,
    KindRule,
    LessonFile,
    PassageKind,
} from '@/lib/content';
import { where } from '@/lib/content';
import { nativeSelect } from '@/lib/tenants';
import { undoToast } from '@/lib/undo-toast';
import { show as showCourse } from '@/routes/content/courses';
import { destroy, reindex, restore, update } from '@/routes/content/lessons';

type Props = {
    lesson: {
        id: number;
        title: string;
        language: string;
        minutes: number | null;
        body: string;
        module_id: number | null;
        ingest_status: IngestStatus;
        ingested_at: string | null;
    };
    course: { id: number; title: string };
    modules: { id: number; title: string }[];
    files: LessonFile[];
    kinds: KindRule[];
    knowledge: {
        passages: number;
        embedded: number;
        meaning: boolean;
        video_host: 'mux' | 'simulator' | 'off';
        sample: {
            id: number;
            kind: PassageKind;
            heading: string | null;
            page: number | null;
            seconds: number | null;
            excerpt: string;
        }[];
    };
};

/**
 * One lesson (A5.3): its text, its files and what the knowledge base holds
 * from them, each passage with where it opens. The page refreshes itself
 * while files are scanned and read.
 */
export default function LessonPage({
    lesson,
    course,
    modules,
    files,
    kinds,
    knowledge,
}: Props) {
    const { t } = useTranslation();
    const busy =
        lesson.ingest_status === 'pending' ||
        lesson.ingest_status === 'processing' ||
        files.some(
            (f) => f.status === 'processing' || f.status === 'awaiting_upload',
        );
    const { start, stop } = usePoll(
        3000,
        { only: ['lesson', 'files', 'knowledge'] },
        { autoStart: false },
    );

    useEffect(() => {
        if (busy) {
            start();
        } else {
            stop();
        }

        return stop;
    }, [busy, start, stop]);

    const remove = () =>
        router.delete(destroy(lesson.id).url, {
            onSuccess: () =>
                undoToast({
                    message: t('content.lessons.deleted', {
                        title: lesson.title,
                    }),
                    onUndo: () => router.post(restore(lesson.id).url),
                }),
        });

    return (
        <>
            <Head title={lesson.title} />

            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={lesson.title}
                    description={t('content.lessons.description', {
                        status: t(`content.ingest.${lesson.ingest_status}`),
                        count: knowledge.passages,
                    })}
                    back={
                        <Link
                            href={showCourse(course.id)}
                            className="inline-flex min-h-touch items-center gap-2 font-medium text-primary underline-offset-4 hover:underline"
                        >
                            <ArrowLeft aria-hidden className="size-5" />
                            {course.title}
                        </Link>
                    }
                    actions={
                        <>
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.post(
                                        reindex(lesson.id).url,
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <RefreshCw aria-hidden />
                                {t('content.lessons.reindex')}
                            </Button>
                            <Button variant="outline" onClick={remove}>
                                <Trash2 aria-hidden />
                                {t('content.lessons.delete')}
                            </Button>
                        </>
                    }
                />

                <div className="grid gap-6 lg:grid-cols-3">
                    <section className="space-y-4 rounded-reos-lg border border-border bg-card p-5 lg:col-span-2">
                        <h2 className="font-semibold">
                            {t('content.lessons.text')}
                        </h2>
                        <Form
                            {...update.form(lesson.id)}
                            options={{ preserveScroll: true }}
                            className="space-y-4"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="grid gap-2 sm:col-span-2">
                                            <Label htmlFor="title">
                                                {t(
                                                    'content.lessons.fields.title',
                                                )}
                                            </Label>
                                            <Input
                                                id="title"
                                                name="title"
                                                defaultValue={lesson.title}
                                                required
                                                maxLength={200}
                                            />
                                            <InputError
                                                message={errors.title}
                                            />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="module">
                                                {t(
                                                    'content.lessons.fields.module',
                                                )}
                                            </Label>
                                            <select
                                                id="module"
                                                name="course_module_id"
                                                className={nativeSelect}
                                                defaultValue={
                                                    lesson.module_id ?? ''
                                                }
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
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <div className="grid gap-2">
                                                <Label htmlFor="language">
                                                    {t(
                                                        'content.lessons.fields.language',
                                                    )}
                                                </Label>
                                                <select
                                                    id="language"
                                                    name="language"
                                                    className={nativeSelect}
                                                    defaultValue={
                                                        lesson.language
                                                    }
                                                >
                                                    <option value="en">
                                                        {t(
                                                            'content.languages.en',
                                                        )}
                                                    </option>
                                                    <option value="es">
                                                        {t(
                                                            'content.languages.es',
                                                        )}
                                                    </option>
                                                </select>
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="minutes">
                                                    {t(
                                                        'content.lessons.fields.minutes',
                                                    )}
                                                </Label>
                                                <Input
                                                    id="minutes"
                                                    name="minutes"
                                                    type="number"
                                                    min={1}
                                                    max={600}
                                                    defaultValue={
                                                        lesson.minutes ?? ''
                                                    }
                                                />
                                                <InputError
                                                    message={errors.minutes}
                                                />
                                            </div>
                                        </div>
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="body">
                                            {t('content.lessons.fields.body')}
                                        </Label>
                                        <Textarea
                                            id="body"
                                            name="body"
                                            rows={14}
                                            defaultValue={lesson.body}
                                            aria-describedby="body-help"
                                            className="font-normal"
                                        />
                                        <p
                                            id="body-help"
                                            className="text-sm text-muted-foreground"
                                        >
                                            {t('content.lessons.bodyHelp')}
                                        </p>
                                        <InputError message={errors.body} />
                                    </div>
                                    <Button type="submit" disabled={processing}>
                                        {t('content.save')}
                                    </Button>
                                </>
                            )}
                        </Form>
                    </section>

                    <section className="space-y-3 rounded-reos-lg border border-border bg-card p-5">
                        <h2 className="font-semibold">
                            {t('content.knowledge.title')}
                        </h2>
                        <p className="text-body">
                            {t('content.knowledge.passages', {
                                count: knowledge.passages,
                            })}
                        </p>
                        <ul className="space-y-1 text-sm text-muted-foreground">
                            <li>
                                {t(
                                    knowledge.meaning
                                        ? 'content.knowledge.meaningOn'
                                        : 'content.knowledge.meaningOff',
                                )}
                            </li>
                            {knowledge.meaning ? (
                                <li>
                                    {t('content.knowledge.embedded', {
                                        count: knowledge.embedded,
                                        total: knowledge.passages,
                                    })}
                                </li>
                            ) : null}
                            <li>
                                {t(
                                    `content.knowledge.video.${knowledge.video_host}`,
                                )}
                            </li>
                        </ul>
                    </section>
                </div>

                <LessonFiles lessonId={lesson.id} files={files} kinds={kinds} />

                <section className="space-y-3 rounded-reos-lg border border-border bg-card p-5">
                    <div className="space-y-1">
                        <h2 className="font-semibold">
                            {t('content.knowledge.passagesTitle')}
                        </h2>
                        <p className="text-body text-muted-foreground">
                            {t('content.knowledge.passagesIntro')}
                        </p>
                    </div>
                    {knowledge.sample.length === 0 ? (
                        <p className="text-body text-muted-foreground">
                            {t('content.knowledge.none')}
                        </p>
                    ) : (
                        <ol className="divide-y divide-border">
                            {knowledge.sample.map((passage) => (
                                <li key={passage.id} className="space-y-1 py-3">
                                    <p className="text-sm font-medium text-muted-foreground">
                                        {t(
                                            `content.knowledge.kinds.${passage.kind}`,
                                        )}{' '}
                                        · {where(t, passage)}
                                    </p>
                                    <p className="text-body">
                                        {passage.excerpt}
                                    </p>
                                </li>
                            ))}
                        </ol>
                    )}
                </section>
            </div>
        </>
    );
}
