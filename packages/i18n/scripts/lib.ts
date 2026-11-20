/** Pure helpers for checking and converting translation files (tested in test/). */

export type Tree = { [key: string]: string | Tree };

/** `{ a: { b: 'x' } }` → `{ 'a.b': 'x' }`. Rejects non-string leaves. */
export function flatten(tree: Tree, prefix = ''): Record<string, string> {
  const out: Record<string, string> = {};
  for (const [key, value] of Object.entries(tree)) {
    const path = prefix ? `${prefix}.${key}` : key;
    if (typeof value === 'string') out[path] = value;
    else if (value && typeof value === 'object') Object.assign(out, flatten(value, path));
    else throw new Error(`${path} must be a string or an object`);
  }
  return out;
}

/** The sorted `{{placeholder}}` names in a string. */
export function placeholders(text: string): string[] {
  return [...text.matchAll(/\{\{\s*(\w+)\s*\}\}/g)].map((m) => m[1] ?? '').sort();
}

/** Problems with `target` compared with the `source` language (missing, extra, empty, placeholder mismatch). */
export function compare(source: Record<string, string>, target: Record<string, string>, locale: string): string[] {
  const problems: string[] = [];
  for (const [key, text] of Object.entries(source)) {
    const translated = target[key];
    if (translated === undefined) {
      problems.push(`${locale}: missing "${key}" (English: "${text}")`);
      continue;
    }
    if (translated.trim() === '') problems.push(`${locale}: "${key}" is empty`);
    const want = placeholders(text).join(',');
    const got = placeholders(translated).join(',');
    if (want !== got) problems.push(`${locale}: "${key}" has placeholders [${got}] but English has [${want}]`);
  }
  for (const key of Object.keys(target)) {
    if (!(key in source)) problems.push(`${locale}: "${key}" doesn't exist in English (remove it or add it to en.json)`);
  }
  for (const [key, text] of Object.entries(source)) {
    if (text.trim() === '') problems.push(`en: "${key}" is empty`);
  }
  return problems;
}

/** i18next `{{name}}` → Laravel `:name`, so `__('key', ['name' => …])` works with the same files. */
export function toLaravel(flat: Record<string, string>): Record<string, string> {
  return Object.fromEntries(
    Object.entries(flat)
      .sort(([a], [b]) => a.localeCompare(b))
      .map(([key, text]) => [key, text.replace(/\{\{\s*(\w+)\s*\}\}/g, ':$1')]),
  );
}
