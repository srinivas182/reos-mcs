import type { TFunction } from 'i18next';

/** Content library types (A5.3, ADR 0020). */
export type CourseType =
    | 'qualifying'
    | 'ce'
    | 'post_licence'
    | 'test_prep'
    | 'elective';
export type CourseStatus = 'draft' | 'in_review' | 'live' | 'hidden';
export type MediaKind = 'video' | 'slides' | 'document' | 'captions';
export type MediaStatus =
    | 'awaiting_upload'
    | 'processing'
    | 'ready'
    | 'rejected'
    | 'failed';
export type IngestStatus = 'pending' | 'processing' | 'ready' | 'failed';
export type PassageKind = 'text' | 'slide' | 'transcript';

export type Language = 'en' | 'es';

/** Why a file was refused or failed (translation keys the server sends). */
export type FileFailure = `content.files.failed.${
    | 'size'
    | 'type'
    | 'infected'
    | 'scanner'
    | 'missing'
    | 'video'
    | 'unreadable'
    | 'noCaptions'
    | 'processing'}`;

/** A state content can be tagged to; `active` ones (live or being prepared) show first. */
export type StateOption = { code: string; name: string; active: boolean };

export type LessonFile = {
    id: number;
    kind: MediaKind;
    name: string;
    size: number;
    status: MediaStatus;
    failure: FileFailure | null;
    pages: number | null;
    duration_seconds: number | null;
    captions: boolean;
};

export type KindRule = {
    kind: MediaKind;
    extensions: string[];
    max_mb: number;
};

export type SearchHit = {
    passage_id: number;
    lesson_id: number;
    lesson_title: string;
    course_id: number;
    course_title: string;
    kind: PassageKind;
    heading: string | null;
    page: number | null;
    seconds: number | null;
    language: Language;
    /** Matching words are wrapped in [[ and ]]. */
    snippet: string;
    match: 'keyword' | 'meaning' | 'both';
};

/** Status chips use the semantic tones. */
export const statusTone: Record<CourseStatus, string> = {
    live: 'bg-reos-tone-success-bg text-reos-tone-success-fg',
    in_review: 'bg-reos-tone-warning-bg text-reos-tone-warning-fg',
    draft: 'bg-reos-tone-neutral-bg text-reos-tone-neutral-fg',
    hidden: 'bg-reos-tone-neutral-bg text-reos-tone-neutral-fg',
};

export const fileTone: Record<MediaStatus, string> = {
    awaiting_upload: 'bg-reos-tone-info-bg text-reos-tone-info-fg',
    processing: 'bg-reos-tone-info-bg text-reos-tone-info-fg',
    ready: 'bg-reos-tone-success-bg text-reos-tone-success-fg',
    rejected: 'bg-reos-tone-danger-bg text-reos-tone-danger-fg',
    failed: 'bg-reos-tone-danger-bg text-reos-tone-danger-fg',
};

/** "4:05" or "1:02:09". */
export function clock(seconds: number): string {
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = String(seconds % 60).padStart(2, '0');

    return h > 0 ? `${h}:${String(m).padStart(2, '0')}:${s}` : `${m}:${s}`;
}

/** Where a passage opens: "Page 3", "At 4:05" or its heading. */
export function where(
    t: TFunction,
    passage: {
        page: number | null;
        seconds: number | null;
        heading: string | null;
    },
): string {
    if (passage.seconds !== null) {
        return t('content.where.at', { time: clock(passage.seconds) });
    }

    if (passage.page !== null) {
        return t('content.where.page', { page: passage.page });
    }

    return passage.heading ?? t('content.where.text');
}

export function fileSize(bytes: number, locale: string): string {
    const units = ['byte', 'kilobyte', 'megabyte', 'gigabyte'] as const;
    let value = bytes;
    let unit = 0;

    while (value >= 1024 && unit < units.length - 1) {
        value /= 1024;
        unit++;
    }

    return new Intl.NumberFormat(locale, {
        style: 'unit',
        unit: units[unit],
        unitDisplay: 'short',
        maximumFractionDigits: unit < 2 ? 0 : 1,
    }).format(value);
}

/** Splits a snippet into plain and matching parts ([[match]]). */
export function marks(snippet: string): { text: string; match: boolean }[] {
    return snippet
        .split(/(\[\[.*?\]\])/)
        .filter((part) => part !== '')
        .map((part) =>
            part.startsWith('[[') && part.endsWith(']]')
                ? { text: part.slice(2, -2), match: true }
                : { text: part, match: false },
        );
}

/** The kind a file most likely is, from its name. */
export function kindFor(name: string, rules: KindRule[]): MediaKind | null {
    const extension = name.split('.').pop()?.toLowerCase() ?? '';

    return (
        rules.find((rule) => rule.extensions.includes(extension))?.kind ?? null
    );
}

function xsrf(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1] ?? '') : '';
}

/** A JSON request to REOS from the page (uploads), with the session's CSRF token. */
export async function postJson<T>(url: string, body: unknown = {}): Promise<T> {
    return sendJson<T>('POST', url, body);
}

/** PUT JSON to a REOS route (a test answer, A8.3). */
export async function putJson<T>(url: string, body: unknown = {}): Promise<T> {
    return sendJson<T>('PUT', url, body);
}

/** DELETE a REOS route from the page (a deal, B9.1). */
export async function deleteJson<T>(url: string): Promise<T> {
    return sendJson<T>('DELETE', url, {});
}

async function sendJson<T>(
    method: 'POST' | 'PUT' | 'DELETE',
    url: string,
    body: unknown,
): Promise<T> {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrf(),
        },
        body: JSON.stringify(body),
    });
    const data = (await response.json().catch(() => ({}))) as T & {
        message?: string;
        errors?: Record<string, string[]>;
    };

    if (!response.ok) {
        const first = data.errors
            ? Object.values(data.errors)[0]?.[0]
            : undefined;
        throw new Error(first ?? data.message ?? `HTTP ${response.status}`);
    }

    return data;
}

/** Uploads a file to its signed link, reporting progress (0–1). */
export function put(
    upload: { url: string; headers: Record<string, string> },
    file: Blob,
    onProgress: (fraction: number) => void,
): Promise<void> {
    return new Promise((resolve, reject) => {
        const request = new XMLHttpRequest();
        request.open('PUT', upload.url);

        for (const [header, value] of Object.entries(upload.headers)) {
            request.setRequestHeader(header, value);
        }

        request.upload.onprogress = (event) => {
            if (event.lengthComputable) {
                onProgress(event.loaded / event.total);
            }
        };
        request.onload = () =>
            request.status >= 200 && request.status < 300
                ? resolve()
                : reject(new Error(`HTTP ${request.status}`));
        request.onerror = () => reject(new Error('network'));
        request.send(file);
    });
}

export type LegalRule =
    | 'guarantee'
    | 'pass_rate'
    | 'risk_free'
    | 'approval_claim'
    | 'superlative';

/** A course's checks before it goes live (A5.3b, screen 63). */
export type CourseChecks = {
    approvals: string[];
    flags: {
        key: string;
        rule: LegalRule;
        lesson_id: number;
        lesson_title: string;
        kind: PassageKind;
        heading: string | null;
        page: number | null;
        seconds: number | null;
        excerpt: string;
        cleared: boolean;
    }[];
    open: number;
    clean: boolean;
};
