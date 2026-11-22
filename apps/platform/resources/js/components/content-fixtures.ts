import type { KindRule, LessonFile, SearchHit } from '@/lib/content';

/** Realistic REOS content for stories and tests (A5.3). */
export const kinds: KindRule[] = [
    { kind: 'video', extensions: ['mp4', 'mov', 'm4v', 'webm'], max_mb: 5120 },
    { kind: 'slides', extensions: ['pdf', 'pptx'], max_mb: 100 },
    { kind: 'document', extensions: ['pdf', 'docx'], max_mb: 100 },
    { kind: 'captions', extensions: ['vtt'], max_mb: 2 },
];

export const files: LessonFile[] = [
    {
        id: 11,
        kind: 'video',
        name: 'agency-disclosure.mp4',
        size: 412_000_000,
        status: 'ready',
        failure: null,
        pages: null,
        duration_seconds: 754,
        captions: true,
    },
    {
        id: 12,
        kind: 'slides',
        name: 'Agency disclosure slides.pdf',
        size: 2_400_000,
        status: 'ready',
        failure: null,
        pages: 14,
        duration_seconds: null,
        captions: false,
    },
    {
        id: 13,
        kind: 'document',
        name: 'IABS worksheet.docx',
        size: 88_000,
        status: 'processing',
        failure: null,
        pages: null,
        duration_seconds: null,
        captions: false,
    },
    {
        id: 14,
        kind: 'slides',
        name: 'old-deck.pdf',
        size: 1_200,
        status: 'rejected',
        failure: 'content.files.failed.type',
        pages: null,
        duration_seconds: null,
        captions: false,
    },
];

export const hits: SearchHit[] = [
    {
        passage_id: 101,
        lesson_id: 7,
        lesson_title: 'Agency disclosure',
        course_id: 3,
        course_title: 'Principles of Real Estate II',
        kind: 'slide',
        heading: null,
        page: 6,
        seconds: null,
        language: 'en',
        snippet:
            'Give the [[Information About Brokerage Services]] form at the first substantive contact with a party.',
        match: 'both',
    },
    {
        passage_id: 102,
        lesson_id: 7,
        lesson_title: 'Agency disclosure',
        course_id: 3,
        course_title: 'Principles of Real Estate II',
        kind: 'transcript',
        heading: null,
        page: null,
        seconds: 245,
        language: 'en',
        snippet:
            'So before you talk about price or terms, hand them the [[IABS]] and explain who you represent.',
        match: 'keyword',
    },
    {
        passage_id: 103,
        lesson_id: 9,
        lesson_title: 'Representación y divulgación',
        course_id: 4,
        course_title: 'Éxito en el examen: Texas',
        kind: 'text',
        heading: 'Primer contacto',
        page: null,
        seconds: null,
        language: 'es',
        snippet:
            'Entregue el formulario de servicios de corretaje en el primer contacto sustancial.',
        match: 'meaning',
    },
];
