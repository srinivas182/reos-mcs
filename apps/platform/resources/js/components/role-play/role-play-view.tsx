import {
    ClipboardCheck,
    Lightbulb,
    Mic,
    MicOff,
    Send,
    Theater,
    Volume2,
} from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import {
    DIMENSIONS,
    type Assessment,
    type Lesson,
    type Persona,
    type PracticeType,
    type Session,
    type SkillType,
    type Summary,
} from './types';
import { speechLang, useDictation } from './use-dictation';

/** The five skills, each with its people to practise with and the agent's best score. */
export function PracticePicker({
    types,
    busy,
    onStart,
}: {
    types: PracticeType[];
    busy: boolean;
    onStart: (type: SkillType, persona: Persona) => void;
}) {
    const { t } = useTranslation();

    return (
        <ul className="grid gap-4 md:grid-cols-2">
            {types.map((item) => (
                <li
                    key={item.type}
                    className="space-y-3 rounded-reos-lg border bg-card p-5"
                >
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <h3 className="text-lg font-semibold">
                            {t(`roleplay.types.${item.type}`)}
                        </h3>
                        {item.best !== null ? (
                            <Badge variant="outline">
                                {t('roleplay.best', { score: item.best })}
                            </Badge>
                        ) : null}
                    </div>
                    <p className="text-base text-muted-foreground">
                        {t(`roleplay.typeHints.${item.type}`)}
                    </p>
                    <div className="flex flex-col gap-2">
                        {item.personas.map((persona) => (
                            <Button
                                key={persona}
                                variant="outline"
                                className="h-auto min-h-12 justify-start text-left whitespace-normal"
                                disabled={busy}
                                onClick={() => onStart(item.type, persona)}
                            >
                                <Theater aria-hidden />
                                {t(`roleplay.personas.${persona}.name`)}
                            </Button>
                        ))}
                    </div>
                </li>
            ))}
        </ul>
    );
}

/** The skills assessment (screen 7): progress through five rounds, then the weakest skills. */
export function AssessmentCard({
    assessment,
    busy,
    onAssess,
}: {
    assessment: Assessment | null;
    busy: boolean;
    onAssess: () => void;
}) {
    const { t } = useTranslation();
    const titleId = useId();
    const skills = (assessment?.weakest ?? [])
        .map((type) => t(`roleplay.types.${type}`))
        .join(', ');
    const label = !assessment
        ? t('roleplay.assessment.start')
        : assessment.completed
          ? t('roleplay.assessment.retake')
          : t('roleplay.assessment.continue', { n: assessment.done + 1 });

    return (
        <section
            aria-labelledby={titleId}
            className="space-y-3 rounded-reos-lg border border-reos-action bg-card p-5"
        >
            <h2
                id={titleId}
                className="flex items-center gap-2 text-lg font-semibold"
            >
                <ClipboardCheck aria-hidden className="size-5" />
                {t('roleplay.assessment.title')}
            </h2>
            <p className="text-base text-muted-foreground">
                {t('roleplay.assessment.purpose')}
            </p>
            {assessment && !assessment.completed ? (
                <p className="text-base">
                    {t('roleplay.assessment.progress', {
                        done: assessment.done,
                        total: assessment.total,
                    })}
                </p>
            ) : null}
            {assessment?.completed ? (
                <>
                    <p className="text-base font-medium">
                        {t('roleplay.assessment.done', { skills })}
                    </p>
                    <ul
                        aria-label={t('roleplay.results')}
                        className="grid gap-2 sm:grid-cols-2"
                    >
                        {assessment.results.map((r) => (
                            <li
                                key={r.type}
                                className={cn(
                                    'flex justify-between rounded-reos-md px-3 py-2 text-base',
                                    assessment.weakest.includes(r.type)
                                        ? 'bg-reos-tone-warning-bg text-reos-tone-warning-fg'
                                        : 'bg-muted',
                                )}
                            >
                                <span>{t(`roleplay.types.${r.type}`)}</span>
                                <span className="font-semibold">
                                    {t('roleplay.score', { score: r.score })}
                                </span>
                            </li>
                        ))}
                    </ul>
                </>
            ) : null}
            <Button disabled={busy} onClick={onAssess}>
                {label}
            </Button>
        </section>
    );
}

/** Recent practice, newest first; links to each. */
export function RecentList({
    recent,
    href,
}: {
    recent: Summary[];
    href: (id: string) => string;
}) {
    const { t, i18n } = useTranslation();

    if (recent.length === 0) {
        return (
            <EmptyState
                icon={Theater}
                title={t('roleplay.none')}
                description={t('roleplay.noneHint')}
            />
        );
    }

    const day = new Intl.DateTimeFormat(i18n.language, {
        month: 'short',
        day: 'numeric',
    });

    return (
        <ul className="divide-y rounded-reos-lg border bg-card">
            {recent.map((item) => (
                <li key={item.id}>
                    <a
                        href={href(item.id)}
                        className="flex min-h-12 flex-wrap items-center justify-between gap-2 px-4 py-3 text-base hover:bg-muted"
                    >
                        <span>
                            <span className="font-medium">
                                {t(`roleplay.types.${item.type}`)}
                            </span>
                            <span className="text-muted-foreground">
                                {' · '}
                                {t(`roleplay.personas.${item.persona}.name`)}
                                {' · '}
                                {day.format(new Date(item.created_at))}
                            </span>
                        </span>
                        <Badge variant="outline">
                            {item.score !== null
                                ? t('roleplay.score', { score: item.score })
                                : t('roleplay.inProgress')}
                        </Badge>
                    </a>
                </li>
            ))}
        </ul>
    );
}

/** The score, whether the persona agreed, the five parts and the three fixes. */
export function ScoreCard({
    session,
    lessonHref,
}: {
    session: Session;
    lessonHref: (lesson: Lesson) => string;
}) {
    const { t } = useTranslation();

    if (session.score === null || session.scores === null) return null;
    const scores = session.scores;

    return (
        <section
            aria-labelledby="score-title"
            className="space-y-4 rounded-reos-lg border bg-card p-5"
        >
            <div className="space-y-1">
                <h2 id="score-title" className="text-2xl font-semibold">
                    {t('roleplay.score', { score: session.score })}
                </h2>
                <p
                    className={cn(
                        'text-base font-medium',
                        session.outcome === 'won'
                            ? 'text-reos-tone-success-fg'
                            : 'text-muted-foreground',
                    )}
                >
                    {session.outcome === 'won'
                        ? t('roleplay.won')
                        : t('roleplay.lost')}
                </p>
            </div>
            <ul className="grid gap-2 sm:grid-cols-5">
                {DIMENSIONS.map((d) => (
                    <li
                        key={d}
                        className="rounded-reos-md bg-muted px-3 py-2 text-base"
                    >
                        {t('roleplay.part', {
                            label: t(`roleplay.dimensions.${d}`),
                            score: scores[d],
                        })}
                    </li>
                ))}
            </ul>
            <h3 className="text-lg font-semibold">
                {t('roleplay.fixesTitle')}
            </h3>
            <ol className="space-y-3">
                {session.fixes.map((fix) => (
                    <li
                        key={fix.dimension}
                        className="space-y-1 rounded-reos-md bg-reos-tone-info-bg p-4 text-reos-tone-info-fg"
                    >
                        <p className="flex items-center gap-2 font-semibold">
                            <Lightbulb aria-hidden className="size-5" />
                            {t(`roleplay.dimensions.${fix.dimension}`)}
                        </p>
                        <p className="text-base">{fix.text}</p>
                        <p className="text-base italic">
                            {t('roleplay.tryLine', { line: fix.example })}
                        </p>
                        <a
                            href={lessonHref(fix.lesson)}
                            className="inline-flex min-h-12 items-center text-base font-medium underline underline-offset-4"
                        >
                            {t('roleplay.lesson', {
                                name: t(`roleplay.lessons.${fix.lesson}`),
                            })}
                        </a>
                    </li>
                ))}
            </ol>
            <p className="text-base text-muted-foreground">
                {t(`roleplay.engine.${session.engine}`)}
            </p>
        </section>
    );
}

/**
 * A role-play: the conversation, then the answer box. The agent speaks
 * (the browser writes the words in) or types; the client's lines can be
 * read aloud. After the last answer, or Finish, the score shows.
 */
export function SessionView({
    session,
    busy,
    onSay,
    onFinish,
    lessonHref,
}: {
    session: Session;
    busy: boolean;
    onSay: (text: string) => void;
    onFinish: () => void;
    lessonHref: (lesson: Lesson) => string;
}) {
    const { t, i18n } = useTranslation();
    const [text, setText] = useState('');
    const [aloud, setAloud] = useState(false);
    const lang = speechLang(i18n.language);
    const dictation = useDictation(lang, (words) =>
        setText((now) => (now.trim() === '' ? words : `${now} ${words}`)),
    );
    const last = session.turns[session.turns.length - 1];
    const spoken = useRef<string | null>(null);

    // Read the client's newest line aloud when asked to.
    useEffect(() => {
        if (
            !aloud ||
            last?.role !== 'persona' ||
            spoken.current === last.text ||
            typeof window === 'undefined' ||
            !('speechSynthesis' in window)
        ) {
            return;
        }
        spoken.current = last.text;
        const utterance = new SpeechSynthesisUtterance(last.text);
        utterance.lang = lang;
        window.speechSynthesis.cancel();
        window.speechSynthesis.speak(utterance);
    }, [aloud, last, lang]);

    const answers = session.turns.filter((turn) => turn.role === 'agent');
    const send = () => {
        if (text.trim() === '') return;
        dictation.stop();
        onSay(text.trim());
        setText('');
    };

    return (
        <div className="space-y-6">
            <p className="text-base text-muted-foreground">
                {t(`roleplay.personas.${session.persona}.brief`)}
            </p>
            <ol
                aria-label={t(`roleplay.types.${session.type}`)}
                className="space-y-3"
            >
                {session.turns.map((turn, i) => (
                    <li
                        key={i}
                        className={cn(
                            'max-w-prose rounded-reos-lg p-4 text-base',
                            turn.role === 'persona'
                                ? 'bg-muted'
                                : 'ml-auto border bg-card',
                        )}
                    >
                        <p className="text-sm font-semibold text-muted-foreground">
                            {turn.role === 'persona'
                                ? t('roleplay.them')
                                : t('roleplay.you')}
                        </p>
                        <p>{turn.text}</p>
                    </li>
                ))}
            </ol>

            {session.status === 'active' ? (
                <div className="space-y-3 rounded-reos-lg border bg-card p-5">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <Label htmlFor="role-play-answer" className="text-base">
                            {t('roleplay.answer')}
                        </Label>
                        <span className="text-base text-muted-foreground">
                            {t('roleplay.turnsLeft', {
                                count: session.turns_left,
                            })}
                        </span>
                    </div>
                    <Textarea
                        id="role-play-answer"
                        value={text}
                        maxLength={1500}
                        rows={4}
                        aria-describedby="role-play-answer-hint"
                        onChange={(e) => setText(e.target.value)}
                    />
                    <p
                        id="role-play-answer-hint"
                        className="text-base text-muted-foreground"
                    >
                        {dictation.listening
                            ? t('roleplay.listening')
                            : dictation.supported
                              ? t('roleplay.answerHint')
                              : t('roleplay.noVoice')}
                    </p>
                    <div className="flex flex-wrap gap-2">
                        {dictation.supported ? (
                            <Button
                                variant="outline"
                                aria-pressed={dictation.listening}
                                onClick={
                                    dictation.listening
                                        ? dictation.stop
                                        : dictation.start
                                }
                            >
                                {dictation.listening ? (
                                    <MicOff aria-hidden />
                                ) : (
                                    <Mic aria-hidden />
                                )}
                                {dictation.listening
                                    ? t('roleplay.stopListening')
                                    : t('roleplay.speak')}
                            </Button>
                        ) : null}
                        <Button
                            disabled={busy || text.trim() === ''}
                            onClick={send}
                        >
                            <Send aria-hidden />
                            {t('roleplay.send')}
                        </Button>
                        <Button
                            variant="ghost"
                            disabled={busy || answers.length === 0}
                            onClick={onFinish}
                        >
                            {t('roleplay.finish')}
                        </Button>
                    </div>
                    <div className="flex items-center gap-3">
                        <Switch
                            id="role-play-aloud"
                            checked={aloud}
                            onCheckedChange={setAloud}
                        />
                        <Label
                            htmlFor="role-play-aloud"
                            className="flex items-center gap-2 text-base"
                        >
                            <Volume2 aria-hidden className="size-5" />
                            {t('roleplay.readAloud')}
                        </Label>
                    </div>
                </div>
            ) : (
                <ScoreCard session={session} lessonHref={lessonHref} />
            )}
        </div>
    );
}
