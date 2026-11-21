/**
 * Voice capture on the phone (B4.3b, ADR 0018).
 *
 * - The phone's own speech recognition turns speech into words, on the
 *   device when it can (private, instant, free). No audio leaves the phone.
 * - The words go to `POST /voice/parse` (Claude on Amazon Bedrock, or the
 *   server's date parser). With no signal, the phone's copy of the parser
 *   (`@reos/voice`) reads them instead, so a reminder with a clear time is
 *   still set at once.
 * - Nothing is saved until the person confirms the card.
 */
import type { Schemas } from '@reos/api-client';
import { parse, type Language } from '@reos/voice';
import { ExpoSpeechRecognitionModule, useSpeechRecognitionEvent } from 'expo-speech-recognition';
import { useCallback, useEffect, useRef, useState } from 'react';

import { api } from '@/lib/api';
import { localIso } from '@/lib/calendar';
import { blankDraft, type TodoDraft } from '@/lib/todos';

/** One to-do as understood, ready for the confirm card. */
export type Understood = { title: string; notes: string | null; people: string[]; due_at: string | null; due_date: string | null; remind_at: string | null };

/** Who read the words: Claude (`ai`), the server's parser, or this phone (no signal). */
export type Engine = 'ai' | 'parser' | 'phone';

export type Understanding = { engine: Engine; items: Understood[] };

type ParseRequest = Schemas['ParseVoiceRequest'];

export const speechLanguage = (language: string): Language => (language.toLowerCase().startsWith('es') ? 'es' : 'en');

/** The phone's time zone (`America/Chicago`). */
export const phoneTimezone = (): string => Intl.DateTimeFormat().resolvedOptions().timeZone || 'America/Chicago';

/** A local date and wall time as ISO with this phone's offset. */
export function localAt(date: string, time: string): string {
  const [y, m, d] = date.split('-').map(Number);
  const [h, min] = time.split(':').map(Number);

  return localIso(new Date(y ?? 1970, (m ?? 1) - 1, d ?? 1, h ?? 0, min ?? 0));
}

/** The phone's own reading, used with no signal: one item, the same shape the server returns. */
export function understandOnPhone(text: string, language: Language, now: Date = new Date()): Understood[] {
  const item = parse(text, language, localIso(now).slice(0, 16));

  if (item.title.trim() === '') {
    return [];
  }

  const at = item.due?.time ? localAt(item.due.date, item.due.time) : null;

  return [
    {
      title: item.title,
      notes: null,
      people: item.people,
      due_at: at,
      due_date: item.due && at === null ? item.due.date : null,
      remind_at: item.remind && at !== null ? at : null,
    },
  ];
}

/**
 * Asks the server what was said; with no signal (or the server down) the
 * phone reads it itself. A request the server refuses (too many in a minute)
 * is read on the phone too: the person always gets a card.
 */
export async function understand(text: string, language: Language, now: Date = new Date()): Promise<Understanding> {
  const body: ParseRequest = { text, language, timezone: phoneTimezone() };

  try {
    const { data, response } = await api().POST('/voice/parse', { body });

    if (response.ok && data !== undefined) {
      return { engine: data.engine, items: data.items };
    }
  } catch {
    // No signal: read it on the phone below.
  }

  return { engine: 'phone', items: understandOnPhone(text, language, now) };
}

export type SpeechState = 'idle' | 'listening' | 'denied' | 'unavailable';

/**
 * The phone's speech recognition: `start()` asks for the microphone once,
 * then listens (on the device when supported) and shows words as they're
 * heard. `onDone` receives the words when listening stops (the person let
 * go, tapped Stop, or paused).
 */
export function useSpeech(language: Language, onDone: (text: string) => void) {
  const [state, setState] = useState<SpeechState>('idle');
  const [transcript, setTranscript] = useState('');
  const heard = useRef('');
  const done = useRef(onDone);

  useEffect(() => {
    done.current = onDone;
  });

  useSpeechRecognitionEvent('result', (event) => {
    heard.current = event.results[0]?.transcript ?? heard.current;
    setTranscript(heard.current);
  });

  useSpeechRecognitionEvent('error', (event) => {
    if (event.error === 'not-allowed') {
      setState('denied');
    } else if (event.error === 'service-not-allowed' || event.error === 'language-not-supported') {
      setState('unavailable');
    }
  });

  useSpeechRecognitionEvent('end', () => {
    setState((current) => (current === 'listening' ? 'idle' : current));
    done.current(heard.current.trim());
  });

  const start = useCallback(async () => {
    if (!ExpoSpeechRecognitionModule.isRecognitionAvailable()) {
      setState('unavailable');

      return;
    }

    const permission = await ExpoSpeechRecognitionModule.requestPermissionsAsync();

    if (!permission.granted) {
      setState('denied');

      return;
    }

    heard.current = '';
    setTranscript('');
    setState('listening');
    ExpoSpeechRecognitionModule.start({
      lang: language === 'es' ? 'es-US' : 'en-US',
      interimResults: true,
      requiresOnDeviceRecognition: ExpoSpeechRecognitionModule.supportsOnDeviceRecognition(),
      addsPunctuation: false,
      contextualStrings: ['REOS', 'showing', 'listing', 'closing', 'escrow', 'option period'],
    });
  }, [language]);

  const stop = useCallback(() => {
    ExpoSpeechRecognitionModule.stop();
  }, []);

  return { state, transcript, start, stop };
}

/** A confirm-card item: the draft to save, plus the names heard (to link one to a contact). */
export type CardItem = { draft: TodoDraft; people: string[] };

export function cardItems(items: Understood[], source: TodoDraft['source']): CardItem[] {
  return items.map((item) => ({
    draft: { ...blankDraft(source), title: item.title, notes: item.notes, due_at: item.due_at, due_date: item.due_date, remind_at: item.remind_at },
    people: item.people,
  }));
}

const DAY = 86_400_000;

/** The due date a day earlier or later, keeping the time and the reminder in step. */
export function moveDay(draft: TodoDraft, days: number): TodoDraft {
  if (draft.due_at !== null) {
    const at = new Date(draft.due_at);
    const moved = localIso(new Date(at.getFullYear(), at.getMonth(), at.getDate() + days, at.getHours(), at.getMinutes()));

    return { ...draft, due_at: moved, remind_at: draft.remind_at === null ? null : moved };
  }

  if (draft.due_date !== null) {
    const moved = new Date(Date.parse(`${draft.due_date}T12:00:00Z`) + days * DAY).toISOString().slice(0, 10);

    return { ...draft, due_date: moved };
  }

  return draft;
}

/** The due time 15 minutes earlier or later (a timed to-do only). */
export function moveMinutes(draft: TodoDraft, minutes: number): TodoDraft {
  if (draft.due_at === null) return draft;

  const moved = localIso(new Date(Date.parse(draft.due_at) + minutes * 60_000));

  return { ...draft, due_at: moved, remind_at: draft.remind_at === null ? null : moved };
}

/** No date → the next whole hour today, reminding; a date → none. */
export function toggleDate(draft: TodoDraft, now: Date = new Date()): TodoDraft {
  if (draft.due_at !== null || draft.due_date !== null) {
    return { ...draft, due_at: null, due_date: null, remind_at: null };
  }

  const at = localIso(new Date(now.getFullYear(), now.getMonth(), now.getDate(), now.getHours() + 1, 0));

  return { ...draft, due_at: at, remind_at: at };
}

export function setRemind(draft: TodoDraft, remind: boolean): TodoDraft {
  return { ...draft, remind_at: remind && draft.due_at !== null ? draft.due_at : null };
}
