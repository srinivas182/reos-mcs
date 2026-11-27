import { useCallback, useEffect, useRef, useState } from 'react';

type Alternative = { transcript: string };
type ResultEvent = {
    resultIndex: number;
    results: ArrayLike<ArrayLike<Alternative> & { isFinal: boolean }>;
};
type Recognition = {
    lang: string;
    interimResults: boolean;
    continuous: boolean;
    start: () => void;
    stop: () => void;
    onresult: ((event: ResultEvent) => void) | null;
    onend: (() => void) | null;
    onerror: (() => void) | null;
};
type RecognitionConstructor = new () => Recognition;

function recognitionConstructor(): RecognitionConstructor | null {
    if (typeof window === 'undefined') return null;
    const w = window as unknown as {
        SpeechRecognition?: RecognitionConstructor;
        webkitSpeechRecognition?: RecognitionConstructor;
    };

    return w.SpeechRecognition ?? w.webkitSpeechRecognition ?? null;
}

/**
 * Speaking an answer in the browser (A14.1a): the browser's own speech
 * recognition writes the words into the answer box, where the agent can
 * check them before sending. Browsers without it fall back to typing.
 */
export function useDictation(lang: string, onWords: (words: string) => void) {
    const Ctor = recognitionConstructor();
    const [listening, setListening] = useState(false);
    const recognition = useRef<Recognition | null>(null);
    const words = useRef(onWords);

    useEffect(() => {
        words.current = onWords;
    }, [onWords]);

    useEffect(() => () => recognition.current?.stop(), []);

    const start = useCallback(() => {
        if (!Ctor) return;
        const r = new Ctor();
        r.lang = lang;
        r.interimResults = false;
        r.continuous = true;
        r.onresult = (event) => {
            let said = '';
            for (let i = event.resultIndex; i < event.results.length; i++) {
                const result = event.results[i];
                if (result?.isFinal) said += result[0]?.transcript ?? '';
            }
            if (said.trim() !== '') words.current(said.trim());
        };
        r.onend = () => setListening(false);
        r.onerror = () => setListening(false);
        recognition.current = r;
        r.start();
        setListening(true);
    }, [Ctor, lang]);

    const stop = useCallback(() => {
        recognition.current?.stop();
        setListening(false);
    }, []);

    return { supported: Ctor !== null, listening, start, stop };
}

/** The browser's speech language for the screen's language. */
export const speechLang = (language: string): string =>
    language.startsWith('es') ? 'es-US' : 'en-US';
