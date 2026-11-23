import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2 } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { statusTone } from '@/components/builder/types';
import { PageHeader } from '@/components/page-header';
import { EditLineDialog } from '@/components/review/edit-line-dialog';
import { LineCard } from '@/components/review/line-card';
import { ReviewItems } from '@/components/review/review-items';
import type {
    ReviewItemRow,
    ReviewLine,
    ReviewVersion,
    ReviewVideo,
} from '@/components/review/types';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { show as courseShow } from '@/routes/builder';
import { publish, render, sendBack } from '@/routes/builder/review';

/**
 * AI lesson review (instructor web screen 44, A8.1): the avatar video in
 * either language, the check-ins and flashcards, the script line by line
 * with its source, flagged lines with their legal source and a suggestion,
 * the versions; Approve and publish only when nothing blocks it, or Send
 * back with a note. Flagged legal lines block publishing; versions are kept.
 */
export default function LessonReviewPage({
    lesson,
    version,
    versions,
    lines,
    videos,
    items,
    blockers,
    pending_rerecord,
}: {
    lesson: {
        id: number;
        title: string;
        course: { id: number; title: string };
    };
    version: ReviewVersion;
    versions: {
        version: number;
        status: string;
        review_state: string | null;
        created_at: string | null;
    }[];
    lines: ReviewLine[];
    videos: ReviewVideo[];
    items: ReviewItemRow[];
    blockers: { text: string }[];
    pending_rerecord: number;
}) {
    const { t } = useTranslation();
    const [language, setLanguage] = useState<'en' | 'es'>('en');
    const [editing, setEditing] = useState<ReviewLine | null>(null);
    const [sending, setSending] = useState(false);
    const [note, setNote] = useState('');
    const locked = version.review_state === 'approved';
    const video = videos.find((v) => v.language === language);

    const actions = locked ? null : (
        <div className="flex flex-wrap gap-2">
            {pending_rerecord > 0 ? (
                <Button
                    variant="outline"
                    onClick={() => router.post(render(lesson.id).url)}
                >
                    {t('review.page.renderAgain', { count: pending_rerecord })}
                </Button>
            ) : null}
            <Button variant="outline" onClick={() => setSending(true)}>
                {t('review.page.sendBack')}
            </Button>
            <Button
                disabled={blockers.length > 0}
                onClick={() => router.post(publish(lesson.id).url)}
            >
                <CheckCircle2 aria-hidden />
                {t('review.page.publish')}
            </Button>
        </div>
    );

    return (
        <>
            <Head title={lesson.title} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={lesson.title}
                    description={t('review.page.description')}
                    back={
                        <Link
                            href={courseShow(lesson.course.id)}
                            className="inline-flex min-h-touch items-center gap-2 font-medium text-primary underline-offset-4 hover:underline"
                        >
                            <ArrowLeft aria-hidden className="size-5" />
                            {lesson.course.title}
                        </Link>
                    }
                    actions={actions}
                />

                {locked ? (
                    <p className="rounded-reos-md bg-reos-tone-success-bg p-3 text-base text-reos-tone-success-fg">
                        {t('review.page.published')}
                    </p>
                ) : null}
                {version.review_state === 'sent_back' && version.review_note ? (
                    <p className="rounded-reos-md bg-reos-tone-warning-bg p-3 text-base text-reos-tone-warning-fg">
                        {t('review.page.sentBackNote', {
                            note: version.review_note,
                        })}
                    </p>
                ) : null}
                {!locked && blockers.length > 0 ? (
                    <section className="rounded-reos-md border border-border bg-card p-4">
                        <h2 className="font-semibold">
                            {t('review.page.blocked')}
                        </h2>
                        <ul className="mt-2 list-disc space-y-1 pl-5 text-base">
                            {blockers.map((b) => (
                                <li key={b.text}>{b.text}</li>
                            ))}
                        </ul>
                    </section>
                ) : null}

                <div className="grid gap-6 xl:grid-cols-[minmax(0,22rem)_minmax(0,1fr)]">
                    <div className="space-y-6">
                        <section className="space-y-3 rounded-reos-lg border border-border bg-card p-4">
                            <div className="flex items-center justify-between gap-2">
                                <h2 className="text-lg font-semibold">
                                    {t('review.page.video')}
                                </h2>
                                <div
                                    role="group"
                                    aria-label={t('review.page.video')}
                                    className="flex gap-1"
                                >
                                    {(['en', 'es'] as const).map((l) => (
                                        <Button
                                            key={l}
                                            size="sm"
                                            variant={
                                                language === l
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                            aria-pressed={language === l}
                                            onClick={() => setLanguage(l)}
                                        >
                                            {t(
                                                `review.page.${l === 'en' ? 'english' : 'spanish'}`,
                                            )}
                                        </Button>
                                    ))}
                                </div>
                            </div>
                            {video?.url ? (
                                <video
                                    key={video.url}
                                    controls
                                    preload="metadata"
                                    src={video.url}
                                    className="aspect-video w-full rounded-reos-md bg-muted"
                                    aria-label={`${t('review.page.video')}: ${t(`review.page.${language === 'en' ? 'english' : 'spanish'}`)}`}
                                />
                            ) : (
                                <p className="text-base text-muted-foreground">
                                    {t('review.page.noVideo')}
                                </p>
                            )}
                        </section>
                        {items.length > 0 ? (
                            <ReviewItems
                                lessonId={lesson.id}
                                items={items}
                                locked={locked}
                            />
                        ) : null}
                        <section className="space-y-2 rounded-reos-lg border border-border bg-card p-4">
                            <h2 className="text-lg font-semibold">
                                {t('review.page.versions')}
                            </h2>
                            <ul className="space-y-1 text-base">
                                {versions.map((v) => (
                                    <li
                                        key={v.version}
                                        className="flex items-center justify-between gap-2"
                                    >
                                        <span
                                            className={cn(
                                                v.version === version.version &&
                                                    'font-semibold',
                                            )}
                                        >
                                            {t('review.page.versionRow', {
                                                version: v.version,
                                            })}
                                        </span>
                                        <span
                                            className={cn(
                                                'inline-flex rounded-reos-pill px-2.5 py-0.5 text-sm font-semibold',
                                                statusTone(v.status),
                                            )}
                                        >
                                            {t(
                                                `builder.ai.statuses.${v.status as 'ready'}`,
                                            )}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    </div>
                    <section className="space-y-3">
                        <h2 className="text-lg font-semibold">
                            {t('review.page.script')}
                        </h2>
                        <ol className="space-y-3">
                            {lines.map((line) => (
                                <LineCard
                                    key={line.n}
                                    lessonId={lesson.id}
                                    line={line}
                                    locked={locked}
                                    onEdit={setEditing}
                                />
                            ))}
                        </ol>
                    </section>
                </div>
            </div>
            <EditLineDialog
                lessonId={lesson.id}
                line={editing}
                onClose={() => setEditing(null)}
            />
            <Dialog open={sending} onOpenChange={setSending}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            {t('review.page.sendBackTitle')}
                        </DialogTitle>
                    </DialogHeader>
                    <form
                        className="space-y-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            router.post(sendBack(lesson.id).url, { note });
                        }}
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="send-back-note">
                                {t('review.page.sendBackLabel')}
                            </Label>
                            <Textarea
                                id="send-back-note"
                                value={note}
                                maxLength={2000}
                                onChange={(e) => setNote(e.target.value)}
                            />
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setSending(false)}
                            >
                                {t('review.page.cancel')}
                            </Button>
                            <Button
                                type="submit"
                                disabled={note.trim().length < 5}
                            >
                                {t('review.page.sendBackGo')}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
