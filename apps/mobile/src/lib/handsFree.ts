/**
 * Hands-free mode (candidate screen 51, A9.3): what's said back to the
 * candidate and how a spoken answer is understood. Pure, so it's tested
 * without a phone; the screen (`app/practice/hands-free.tsx`) does the
 * speaking, listening and lock-screen controls.
 *
 * An answer can be a letter ("B", "option b", "bee"), an ordinal ("the
 * second one"), "true" or "false" for true-or-false options, or the
 * option's own words ("seventy-eight percent"). "Repeat", "Skip" and
 * "Stop" are commands in English and Spanish.
 */
import type { TFunction } from 'i18next';

export type Language = 'en' | 'es';
export type Heard = { kind: 'answer'; choice: number } | { kind: 'repeat' } | { kind: 'skip' } | { kind: 'stop' } | { kind: 'unclear' };

const LETTERS = ['a', 'b', 'c', 'd'];

/** How each letter sounds when speech recognition writes it out. */
const SOUNDS: Record<Language, string[][]> = {
  en: [
    ['a', 'ay', 'eh', 'hey'],
    ['b', 'be', 'bee', 'bea'],
    ['c', 'see', 'sea', 'cee', 'si'],
    ['d', 'dee', 'de'],
  ],
  es: [
    ['a', 'ah', 'ha'],
    ['b', 'be', 'bé', 've', 'uve'],
    ['c', 'ce', 'se', 'sé'],
    ['d', 'de', 'dé'],
  ],
};

const ORDINALS: Record<Language, string[][]> = {
  en: [
    ['first', 'one'],
    ['second', 'two'],
    ['third', 'three'],
    ['fourth', 'four', 'last'],
  ],
  es: [
    ['primera', 'primero', 'uno'],
    ['segunda', 'segundo', 'dos'],
    ['tercera', 'tercero', 'tres'],
    ['cuarta', 'cuarto', 'cuatro', 'última', 'ultima', 'último', 'ultimo'],
  ],
};

const COMMANDS: Record<Language, Record<'repeat' | 'skip' | 'stop', string[]>> = {
  en: { repeat: ['repeat', 'again', 'say that again', 'repeat the question'], skip: ['skip', 'next', 'pass'], stop: ['stop', 'quit', 'end', "i'm done", 'finish'] },
  es: { repeat: ['repetir', 'repite', 'repita', 'otra vez', 'de nuevo'], skip: ['saltar', 'siguiente', 'pasar', 'paso'], stop: ['parar', 'para', 'detener', 'terminar', 'alto', 'basta'] },
};

const TRUE: Record<Language, string[]> = { en: ['true', 'yes', 'right'], es: ['verdadero', 'cierto', 'sí', 'si'] };
const FALSE: Record<Language, string[]> = { en: ['false', 'no', 'wrong'], es: ['falso', 'no'] };

const FILLER: Record<Language, string[]> = {
  en: ['the', 'answer', 'is', 'option', 'letter', 'it', 'its', "it's", 'i', 'think', 'say', 'one', 'um', 'uh'],
  es: ['la', 'el', 'respuesta', 'es', 'opción', 'opcion', 'letra', 'creo', 'que', 'digo', 'eh'],
};

/** Lower case, accents kept for Spanish words, punctuation gone. */
export function normalise(text: string): string {
  return text
    .toLowerCase()
    .replace(/[.,!?¿¡;:"“”()]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

function words(text: string): string[] {
  return normalise(text).split(' ').filter(Boolean);
}

const UNITS: Record<Language, string[]> = {
  en: ['zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'],
  es: ['cero', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve', 'diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve'],
};
const TENS: Record<Language, string[]> = {
  en: ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'],
  es: ['', '', 'veinte', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'],
};

/** "seventy eight" and "setenta y ocho" become "78", so they match an option's digits. */
function numbers(list: string[], language: Language): string[] {
  const out: string[] = [];
  for (let i = 0; i < list.length; i++) {
    const w = list[i] ?? '';
    const ten = TENS[language].indexOf(w);
    if (ten >= 2) {
      const next = list[i + 1] === 'y' ? list[i + 2] : list[i + 1];
      const unit = next === undefined ? -1 : UNITS[language].indexOf(next);
      if (unit >= 1 && unit <= 9) {
        out.push(String(ten * 10 + unit));
        i += list[i + 1] === 'y' ? 2 : 1;
        continue;
      }
      out.push(String(ten * 10));
      continue;
    }
    const unit = UNITS[language].indexOf(w);
    out.push(unit >= 0 ? String(unit) : w === 'hundred' || w === 'cien' ? '100' : w);
  }

  return out;
}

/** Words that tell options apart: no fillers or tiny words; numbers as digits. */
function meaningful(text: string, language: Language): string[] {
  const filler = new Set(FILLER[language].filter((w) => w !== 'one'));

  return numbers(words(text.replace(/[-/]/g, ' ')), language)
    .map((w) => w.replace(/[^\p{L}\p{N}%]/gu, '').replace(/%$/, ''))
    .filter((w) => (w.length > 2 || /^\d+$/.test(w)) && !filler.has(w) && w !== 'percent' && w !== 'por' && w !== 'ciento');
}

/**
 * What the candidate said: an answer to one of the options, a command, or
 * unclear (ask again).
 */
export function understand(heard: string, options: string[], language: Language): Heard {
  const said = normalise(heard);

  if (said === '') return { kind: 'unclear' };

  for (const command of ['stop', 'repeat', 'skip'] as const) {
    if (COMMANDS[language][command].some((c) => said === c || said.startsWith(`${c} `) || said.endsWith(` ${c}`))) return { kind: command };
  }

  // A letter or an ordinal on its own, or after "option", "letter" or "the answer is".
  const filler = new Set(FILLER[language]);
  const all = words(said);
  const left = all.filter((w) => !filler.has(w));
  if (left.length <= 1) {
    const only = left[0] ?? all[all.length - 1] ?? '';
    const letter = SOUNDS[language].findIndex((sounds) => sounds.includes(only));
    if (letter >= 0 && letter < options.length) return { kind: 'answer', choice: letter };
    const ordinal = ORDINALS[language].findIndex((forms) => forms.includes(only));
    if (ordinal >= 0) return { kind: 'answer', choice: Math.min(ordinal, options.length - 1) };
  }

  // True or false, when those are the options.
  const lower = options.map((o) => normalise(o));
  const truthy = lower.findIndex((o) => TRUE[language].includes(o) || o === 'true' || o === 'verdadero');
  const falsy = lower.findIndex((o) => FALSE[language].includes(o) || o === 'false' || o === 'falso');
  if (truthy >= 0 && TRUE[language].some((w) => said === w)) return { kind: 'answer', choice: truthy };
  if (falsy >= 0 && FALSE[language].some((w) => said === w)) return { kind: 'answer', choice: falsy };

  // The option's own words: the best overlap wins if it clearly wins.
  const spoken = new Set(meaningful(said, language));
  if (spoken.size === 0) return { kind: 'unclear' };
  // How well the words agree both ways, so a short option isn't won by one shared word.
  const scores = options.map((option) => {
    const own = meaningful(option, language);
    if (own.length === 0) return 0;
    const hits = own.filter((w) => spoken.has(w) || [...spoken].some((s) => s.length > 4 && (w.startsWith(s) || s.startsWith(w)))).length;

    return (2 * hits) / (own.length + spoken.size);
  });
  const best = Math.max(...scores);
  const winners = scores.filter((s) => s === best).length;

  return best >= 0.5 && winners === 1 ? { kind: 'answer', choice: scores.indexOf(best) } : { kind: 'unclear' };
}

export type Speakable = { n: number; total: number; prompt: string; options: string[] };
type T = TFunction;

/** "Question 3 of 15. … A: …. B: …." */
export function questionText(q: Speakable, t: T): string {
  const options = q.options.map((option, i) => t('practice.handsFree.say.option', { letter: (LETTERS[i] ?? '').toUpperCase(), option }));

  return [t('practice.handsFree.say.question', { n: q.n, total: q.total }), q.prompt, ...options, t('practice.handsFree.say.yourAnswer')].join(' ');
}

/** Spoken feedback: right or not, the right answer, and why. */
export function feedbackText(feedback: { correct: boolean; right: number; explanation: string }, options: string[], t: T): string {
  const answer = t('practice.handsFree.say.option', { letter: (LETTERS[feedback.right] ?? '').toUpperCase(), option: options[feedback.right] ?? '' });

  return [feedback.correct ? t('practice.handsFree.say.right') : t('practice.handsFree.say.wrong', { answer }), feedback.explanation].filter(Boolean).join(' ');
}

/** The speech engine's language for the screen's language. */
export function voiceLanguage(language: string): string {
  return language === 'es' ? 'es-US' : 'en-US';
}
