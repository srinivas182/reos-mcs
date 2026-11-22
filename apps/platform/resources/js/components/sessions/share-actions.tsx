import { BookOpen, CircleHelp } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { postJson } from '@/lib/content';
import { shareOptions, shares } from '@/routes/sessions';

type Options = {
    lessons: { id: number; title: string; course: string }[];
    topics: { topic: string; count: number }[];
};

/**
 * Quick actions for the host in the room (B6.3b, instructor screen 15):
 * open a lesson for everyone, or send three practice questions on a topic.
 */
export function ShareActions({
    sessionId,
    onShared,
}: {
    sessionId: string;
    onShared: () => void;
}) {
    const { t } = useTranslation();
    const [open, setOpen] = useState<'lesson' | 'questions' | null>(null);
    const [options, setOptions] = useState<Options | null>(null);
    const [filter, setFilter] = useState('');
    const [error, setError] = useState<string | undefined>();

    const show = async (kind: 'lesson' | 'questions') => {
        setOpen(kind);
        setError(undefined);

        if (options === null) {
            const response = await fetch(shareOptions(sessionId).url, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            setOptions((await response.json()) as Options);
        }
    };

    const send = async (body: Record<string, unknown>) => {
        try {
            await postJson(shares(sessionId).url, body);
            setOpen(null);
            onShared();
        } catch (e) {
            setError(e instanceof Error ? e.message : String(e));
        }
    };

    const lessons = (options?.lessons ?? []).filter((l) =>
        `${l.title} ${l.course}`
            .toLowerCase()
            .includes(filter.trim().toLowerCase()),
    );

    return (
        <>
            <Button variant="outline" onClick={() => void show('lesson')}>
                <BookOpen aria-hidden />
                {t('sessions.share.lesson')}
            </Button>
            <Button variant="outline" onClick={() => void show('questions')}>
                <CircleHelp aria-hidden />
                {t('sessions.share.questions')}
            </Button>
            <Dialog
                open={open !== null}
                onOpenChange={(next) => (next ? undefined : setOpen(null))}
            >
                <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            {open === 'lesson'
                                ? t('sessions.share.lesson')
                                : t('sessions.share.questions')}
                        </DialogTitle>
                        <DialogDescription>
                            {open === 'lesson'
                                ? t('sessions.share.lessonHelp')
                                : t('sessions.share.questionsHelp')}
                        </DialogDescription>
                    </DialogHeader>
                    {options === null ? (
                        <p className="text-base text-muted-foreground">
                            {t('sessions.whiteboard.loading')}
                        </p>
                    ) : open === 'lesson' ? (
                        <div className="space-y-3">
                            <Input
                                aria-label={t('sessions.share.find')}
                                placeholder={t('sessions.share.find')}
                                value={filter}
                                onChange={(e) => setFilter(e.target.value)}
                            />
                            {lessons.length === 0 ? (
                                <p className="text-base text-muted-foreground">
                                    {t('sessions.share.noLessons')}
                                </p>
                            ) : (
                                <ul className="grid gap-1">
                                    {lessons.map((l) => (
                                        <li key={l.id}>
                                            <button
                                                type="button"
                                                className="flex min-h-12 w-full flex-col items-start rounded-reos-md px-3 py-2 text-left hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                onClick={() =>
                                                    void send({
                                                        kind: 'lesson',
                                                        lesson: l.id,
                                                    })
                                                }
                                            >
                                                <span className="text-base font-medium">
                                                    {l.title}
                                                </span>
                                                <span className="text-sm text-muted-foreground">
                                                    {l.course}
                                                </span>
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    ) : (
                        <div className="grid gap-2 sm:grid-cols-2">
                            {options.topics.map((topic) => (
                                <Button
                                    key={topic.topic}
                                    variant="outline"
                                    className="justify-start"
                                    onClick={() =>
                                        void send({
                                            kind: 'questions',
                                            topic: topic.topic,
                                        })
                                    }
                                >
                                    {t(
                                        `onboarding.topics.${topic.topic as 'agency'}`,
                                    )}
                                </Button>
                            ))}
                        </div>
                    )}
                    <InputError message={error} />
                </DialogContent>
            </Dialog>
        </>
    );
}
