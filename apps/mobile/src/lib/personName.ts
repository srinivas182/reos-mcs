/** Courtesy titles that come before a name ("Dr. Rachel Hughes"). */
const TITLES = new Set(['dr', 'mr', 'mrs', 'ms', 'mx', 'prof', 'rev', 'hon']);

/** The words of a name without a leading title: "Dr. Rachel Hughes" → ["Rachel", "Hughes"]. */
export function nameParts(name: string): string[] {
  const words = name.trim().split(/\s+/).filter(Boolean);

  while (words.length > 1 && TITLES.has(words[0].replace(/\.$/, '').toLowerCase())) {
    words.shift();
  }

  return words;
}

/** Initials for an avatar: "James Miller" → "JM", "Dr. Rachel Hughes" → "RH". */
export function initialsOf(name: string): string {
  const parts = nameParts(name);
  const pick = parts.length > 1 ? [parts[0], parts[parts.length - 1]] : parts;

  return pick.map((part) => part[0]?.toUpperCase() ?? '').join('');
}
