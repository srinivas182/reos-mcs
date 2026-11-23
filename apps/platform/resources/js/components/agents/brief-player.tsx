import { Pause, Play, SkipForward } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

/**
 * The morning brief read aloud in the browser (A11.1c, screen 184): Play,
 * Pause and Skip over the script, one sentence group at a time, with the
 * line being read highlighted in the transcript. Browsers without speech
 * show the transcript alone.
 */
export function BriefPlayer({
    script,
    lang,
}: {
    script: string[];
    lang: string;
}) {
    const { t } = useTranslation();
    const speech =
        typeof window !== 'undefined' && 'speechSynthesis' in window
            ? window.speechSynthesis
            : null;
    const [line, setLine] = useState<number | null>(null);
    const [playing, setPlaying] = useState(false);
    // Where the reader is, and which run of speech may carry on (Pause and Skip start a new one).
    const at = useRef(0);
    const run = useRef(0);

    useEffect(() => () => speech?.cancel(), [speech]);

    const say = (index: number, id: number) => {
        if (!speech || index >= script.length) {
            setPlaying(false);
            setLine(null);
            at.current = 0;
            return;
        }
        at.current = index;
        setLine(index);
        const utterance = new SpeechSynthesisUtterance(script[index]);
        utterance.lang = lang;
        utterance.onend = () => {
            if (run.current === id) say(index + 1, id);
        };
        speech.speak(utterance);
    };
    const stop = () => {
        run.current += 1;
        speech?.cancel();
        return run.current;
    };
    const play = () => {
        const id = stop();
        setPlaying(true);
        say(at.current, id);
    };
    const pause = () => {
        stop();
        setPlaying(false);
    };
    const skip = () => {
        const id = stop();
        const next = at.current + 1;
        if (playing) say(next, id);
        else {
            at.current = Math.min(next, script.length - 1);
            setLine(at.current);
        }
    };

    return (
        <section
            aria-labelledby="brief-transcript"
            className="space-y-4 rounded-reos-lg border border-border bg-card p-5"
        >
            {speech ? (
                <div className="flex flex-wrap gap-3">
                    {playing ? (
                        <Button onClick={pause}>
                            <Pause aria-hidden />
                            {t('agents.brief.player.pause')}
                        </Button>
                    ) : (
                        <Button onClick={play}>
                            <Play aria-hidden />
                            {line === null
                                ? t('agents.brief.player.play')
                                : t('agents.brief.player.resume')}
                        </Button>
                    )}
                    <Button variant="outline" onClick={skip}>
                        <SkipForward aria-hidden />
                        {t('agents.brief.player.skip')}
                    </Button>
                </div>
            ) : (
                <p className="text-base text-muted-foreground">
                    {t('agents.brief.player.unsupported')}
                </p>
            )}
            <h2 id="brief-transcript" className="text-lg font-semibold">
                {t('agents.brief.player.transcript')}
            </h2>
            <ol className="space-y-2">
                {script.map((text, index) => (
                    <li
                        key={`${index}-${text}`}
                        aria-current={line === index ? 'true' : undefined}
                        className={cn(
                            'rounded-reos-md p-2 text-lg leading-relaxed',
                            line === index && 'bg-muted font-semibold',
                        )}
                    >
                        {text}
                    </li>
                ))}
            </ol>
        </section>
    );
}
