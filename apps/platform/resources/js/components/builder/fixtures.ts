import type {
    BuilderCourse,
    BuilderModule,
    BuilderSettings,
    LessonDraft,
    ModuleRow,
    RoleRule,
} from './types';
import type { Teaching } from './teaching-card';

export const course: BuilderCourse = {
    id: 10,
    title: 'Principles of Real Estate II',
    type: 'qualifying',
    delivery: 'self_paced',
    status: 'live',
    hours: 30,
    hours_locked: true,
    approval: 'TREC 0451',
    can_publish: false,
};

export const modules: ModuleRow[] = [
    {
        id: 1,
        title: 'Agency in Texas',
        sources: 2,
        lessons: 2,
        approved: 1,
        status: 'draft',
    },
    {
        id: 2,
        title: 'Earnest money and the option fee',
        sources: 1,
        lessons: 0,
        approved: 0,
        status: 'sources',
    },
];

export const module: BuilderModule = {
    id: 1,
    title: 'Agency in Texas',
    sources: [
        {
            id: 5,
            role: 'script',
            name: 'Agency script 2027.docx',
            size: 48_211,
            status: 'ready',
            failure: null,
            pages: null,
            words: 2_310,
        },
        {
            id: 6,
            role: 'recording',
            name: 'Class Feb 10.mp4',
            size: 412_000_000,
            status: 'ready',
            failure: null,
            pages: null,
            words: null,
        },
        {
            id: 7,
            role: 'slides',
            name: 'Agency deck.pptx',
            size: 2_400_000,
            status: 'processing',
            failure: null,
            pages: null,
            words: null,
        },
    ],
    lessons: [
        {
            id: 21,
            title: 'Who you represent',
            minutes: 8,
            language: 'en',
            status: 'approved',
            has_body: true,
            body: '# Who you represent\nExplain it at the first substantive contact.',
            ai: null,
        },
        {
            id: 22,
            title: 'The IABS form',
            minutes: null,
            language: 'en',
            status: 'draft',
            has_body: false,
            body: null,
            ai: null,
        },
        {
            id: 23,
            title: 'Disclosing who you represent',
            minutes: 9,
            language: 'en',
            status: 'in_review',
            has_body: false,
            body: null,
            ai: { version: 1, status: 'ready', flags: 0 },
        },
    ],
    plan: {
        words: 2_310,
        minutes: 17,
        lessons: 2,
        checkins: 2,
        questions: 10,
        flashcards: 16,
        credits: 340,
        sources_ready: 2,
        sources_waiting: 1,
        credits_left: 5_000,
    },
};

/** An AI lesson's draft: both languages side by side, a flagged line, and the two videos. */
export const lessonDraft: LessonDraft = {
    lesson: { id: 23, title: 'Disclosing who you represent' },
    version: 2,
    versions: 2,
    status: 'flagged',
    failure: null,
    presenter: 'instructor:7',
    vendor: 'heygen',
    credits: 180,
    lines: [
        {
            n: 0,
            en: 'In Texas, you tell a party who you represent at the first substantive contact.',
            es: 'En Texas, usted le dice a una parte a quién representa en el primer contacto sustancial.',
            source: 'Agency module script.pdf',
            page: 3,
        },
        {
            n: 1,
            en: 'You give the Information About Brokerage Services form at that same first contact.',
            es: 'Usted entrega el formulario Información sobre Servicios de Corretaje en ese mismo primer contacto.',
            source: 'IABS form.pdf',
            page: 1,
        },
        {
            n: 2,
            en: 'Follow these steps and you are guaranteed to stay out of trouble.',
            es: 'Siga estos pasos y tiene garantizado no tener problemas.',
            source: 'Agency module script.pdf',
            page: 4,
        },
    ],
    flags: [
        { line: 2, rule: 'guarantee', language: 'en' },
        { line: 2, rule: 'guarantee', language: 'es' },
    ],
    renders: [],
    review: { checkins: 2, questions: 5, flashcards: 8 },
};

export const settings: BuilderSettings = {
    language: 'both',
    lesson_minutes: 10,
    presenter: 'stock',
    vendor: 'heygen',
};

export const roles: RoleRule[] = [
    { role: 'script', types: ['pdf', 'docx'], max_mb: 100 },
    { role: 'slides', types: ['pptx', 'pdf'], max_mb: 200 },
    { role: 'form', types: ['pdf', 'docx'], max_mb: 100 },
    { role: 'recording', types: ['mp4', 'mov', 'm4v', 'webm'], max_mb: 5120 },
    { role: 'captions', types: ['vtt'], max_mb: 5 },
];

export const teaching: Teaching = {
    courses: 2,
    reviews: 3,
    drafts: 1,
    sessions_week: 4,
    pass_rate: 64,
    rating: null,
    today: [
        {
            id: 's-1',
            title: 'Contract review: TREC One to Four',
            starts_at: '2027-03-02T21:00:00Z',
        },
    ],
    missed: [
        { topic: 'agency', missed: 14, answered: 31 },
        { topic: 'math', missed: 9, answered: 22 },
    ],
};
