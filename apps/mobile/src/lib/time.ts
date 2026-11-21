/** "2 days ago" / "Anteayer", in the reader's language, capitalised to open a sentence. */
export function timeAgo(iso: string, language: string, now: Date = new Date()): string {
  const seconds = (new Date(iso).getTime() - now.getTime()) / 1000;
  const format = new Intl.RelativeTimeFormat(language, { numeric: 'auto' });
  const units: [Intl.RelativeTimeFormatUnit, number][] = [
    ['day', 86_400],
    ['hour', 3_600],
    ['minute', 60],
  ];
  const match = units.find(([, size]) => Math.abs(seconds) >= size);
  const text = match ? format.format(Math.round(seconds / match[1]), match[0]) : format.format(0, 'second');

  return text.charAt(0).toLocaleUpperCase(language) + text.slice(1);
}

/** "November 19" / "19 de noviembre". */
export function dayAndMonth(iso: string, language: string): string {
  return new Intl.DateTimeFormat(language, { month: 'long', day: 'numeric' }).format(new Date(iso));
}
