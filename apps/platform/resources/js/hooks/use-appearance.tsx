import { router } from '@inertiajs/react';
import { useSyncExternalStore } from 'react';
import type { TextSize } from '@reos/tokens';
import { update as updateAppearanceRoute } from '@/routes/appearance';

/**
 * Theme and text-size preferences (Sprint 2.2b).
 *
 * The server renders `<html>` with the saved choice (`class="dark"`,
 * `data-theme`, `data-text-size`), so the first paint is already right. This
 * store reads those attributes on start, applies changes instantly, keeps a
 * device cookie for signed-out pages, and saves to the account when signed in.
 */
export type ResolvedAppearance = 'light' | 'dark' | 'contrast';
export type Appearance = ResolvedAppearance | 'system';
export type { TextSize };

export const appearances: readonly Appearance[] = [
    'system',
    'light',
    'dark',
    'contrast',
];
export const textSizes: readonly TextSize[] = ['standard', 'large', 'largest'];

export type UseAppearanceReturn = {
    readonly appearance: Appearance;
    readonly resolvedAppearance: ResolvedAppearance;
    readonly textSize: TextSize;
    readonly updateAppearance: (mode: Appearance) => void;
    readonly updateTextSize: (size: TextSize) => void;
};

type State = { appearance: Appearance; textSize: TextSize };

const listeners = new Set<() => void>();
let state: State = { appearance: 'system', textSize: 'standard' };
let signedIn = false;

const isBrowser = (): boolean => typeof window !== 'undefined';

const prefersDark = (): boolean =>
    isBrowser() && window.matchMedia('(prefers-color-scheme: dark)').matches;

const resolve = (appearance: Appearance): ResolvedAppearance =>
    appearance === 'system' ? (prefersDark() ? 'dark' : 'light') : appearance;

const setCookie = (name: string, value: string, days = 365): void => {
    if (!isBrowser()) {
        return;
    }

    document.cookie = `${name}=${value};path=/;max-age=${days * 86400};SameSite=Lax`;
};

const apply = ({ appearance, textSize }: State): void => {
    if (!isBrowser()) {
        return;
    }

    const html = document.documentElement;
    const resolved = resolve(appearance);

    html.classList.toggle('dark', resolved === 'dark');
    html.style.colorScheme = resolved === 'dark' ? 'dark' : 'light';

    if (appearance === 'system') {
        delete html.dataset.theme;
    } else {
        html.dataset.theme = appearance;
    }

    html.dataset.textSize = textSize;
};

const notify = (): void => listeners.forEach((listener) => listener());

const subscribe = (callback: () => void) => {
    listeners.add(callback);

    return () => listeners.delete(callback);
};

const isAppearance = (value: unknown): value is Appearance =>
    typeof value === 'string' &&
    (appearances as readonly string[]).includes(value);

const isTextSize = (value: unknown): value is TextSize =>
    typeof value === 'string' &&
    (textSizes as readonly string[]).includes(value);

/** Reads the server-rendered choice from `<html>`. Call once before rendering. */
export function initializeTheme(options: { signedIn?: boolean } = {}): void {
    if (!isBrowser()) {
        return;
    }

    signedIn = options.signedIn ?? false;

    const html = document.documentElement;
    const theme = html.dataset.theme;

    state = {
        appearance: isAppearance(theme) ? theme : 'system',
        textSize: isTextSize(html.dataset.textSize)
            ? html.dataset.textSize
            : 'standard',
    };

    apply(state);

    window
        .matchMedia('(prefers-color-scheme: dark)')
        .addEventListener('change', () => {
            apply(state);
            notify();
        });
}

/** Tells the store whether changes should also be saved to the account. */
export function setSignedIn(value: boolean): void {
    signedIn = value;
}

const save = (
    change: Partial<{ appearance: Appearance; text_size: TextSize }>,
): void => {
    if (!signedIn) {
        return;
    }

    router.patch(updateAppearanceRoute().url, change, {
        preserveScroll: true,
        preserveState: true,
        showProgress: false,
    });
};

export function useAppearance(): UseAppearanceReturn {
    const current = useSyncExternalStore(
        subscribe,
        () => state,
        () => state,
    );

    const updateAppearance = (appearance: Appearance): void => {
        state = { ...state, appearance };
        setCookie('appearance', appearance);
        apply(state);
        notify();
        save({ appearance });
    };

    const updateTextSize = (textSize: TextSize): void => {
        state = { ...state, textSize };
        setCookie('text_size', textSize);
        apply(state);
        notify();
        save({ text_size: textSize });
    };

    return {
        appearance: current.appearance,
        resolvedAppearance: resolve(current.appearance),
        textSize: current.textSize,
        updateAppearance,
        updateTextSize,
    } as const;
}
