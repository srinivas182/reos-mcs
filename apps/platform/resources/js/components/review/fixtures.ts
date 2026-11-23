import type {
    AiLessonRow,
    DisputeRow,
    QuestionRow,
    ReviewItemRow,
    ReviewLine,
    ReviewVersion,
    ReviewVideo,
} from './types';
import type { StarterBankData } from './starter-bank';

/** Realistic review data for tests and stories (demo data only). */
export const reviewLesson = {
    id: 23,
    title: 'Disclosing who you represent',
    course: { id: 10, title: 'Principles of Real Estate II' },
};

export const reviewVersion: ReviewVersion = {
    id: 41,
    version: 2,
    status: 'ready',
    review_state: 'in_review',
    review_note: null,
    vendor: 'heygen',
    credits: 180,
};

export const reviewLines: ReviewLine[] = [
    {
        n: 0,
        en: 'In Texas, you tell a party who you represent at the first substantive contact.',
        es: 'En Texas, usted le dice a una parte a quién representa en el primer contacto sustancial.',
        source: 'Agency module script.pdf',
        page: 3,
        status: 'approved',
        rerecord: false,
        suggestion: null,
        comments: [],
        flags: [],
    },
    {
        n: 1,
        en: 'Follow these steps and you are guaranteed to pass.',
        es: 'Siga estos pasos y tiene garantizado aprobar.',
        source: 'Agency module script.pdf',
        page: 4,
        status: 'to_review',
        rerecord: false,
        suggestion: {
            en: 'Follow these steps and they help you prepare for the exam.',
            es: 'Siga estos pasos y le ayudan a prepararse para el examen.',
        },
        comments: [
            {
                by: 9,
                name: 'Paul Diaz',
                body: 'Please avoid promises here.',
                at: '2027-03-16T15:00:00Z',
            },
        ],
        flags: [
            {
                rule: 'guarantee',
                language: 'en',
                source: '22 TAC §535.155 (advertising)',
            },
            {
                rule: 'guarantee',
                language: 'es',
                source: '22 TAC §535.155 (advertising)',
            },
        ],
    },
    {
        n: 2,
        en: 'You give the Information About Brokerage Services form at that first contact.',
        es: 'Usted entrega el formulario Información sobre Servicios de Corretaje en ese primer contacto.',
        source: 'IABS form.pdf',
        page: 1,
        status: 'changed',
        rerecord: true,
        suggestion: null,
        comments: [],
        flags: [],
    },
];

export const reviewVideos: ReviewVideo[] = [
    { language: 'en', status: 'ready', simulated: true, url: null },
    { language: 'es', status: 'ready', simulated: true, url: null },
];

export const reviewItems: ReviewItemRow[] = [
    {
        id: 301,
        kind: 'checkin',
        after_line: 1,
        status: 'to_review',
        content: {
            en: { prompt: 'When do you tell a party who you represent?' },
            es: { prompt: '¿Cuándo le dice a una parte a quién representa?' },
        },
    },
    {
        id: 302,
        kind: 'flashcard',
        after_line: null,
        status: 'approved',
        content: {
            en: { front: 'IABS', back: 'Information About Brokerage Services' },
        },
    },
];

export const aiLessons: AiLessonRow[] = [
    {
        id: 23,
        title: 'Disclosing who you represent',
        course: 'Principles of Real Estate II',
        status: 'in_review',
        version: 2,
        flags: 2,
        blockers: 3,
        updated_at: '2027-03-16T15:00:00Z',
    },
    {
        id: 24,
        title: 'The IABS form, step by step',
        course: 'Principles of Real Estate II',
        status: 'approved',
        version: 1,
        flags: 0,
        blockers: 0,
        updated_at: '2027-03-15T10:00:00Z',
    },
];

const language = (prompt: string) => ({
    prompt,
    options: [
        'At the first substantive contact',
        'At closing',
        'Only when asked',
        'After the offer',
    ],
    explanation: 'The rule is the first substantive contact.',
});

export const questions: QuestionRow[] = [
    {
        id: 501,
        course: 'Principles of Real Estate II',
        lesson: 'Disclosing who you represent',
        topic: 'agency',
        difficulty: 'medium',
        portion: 'state',
        status: 'to_review',
        source: 'Agency module script.pdf, p. 3',
        en: language('When must an agent disclose who they represent?'),
        es: language('¿Cuándo debe un agente revelar a quién representa?'),
        correct: 0,
    },
];

export const disputes: DisputeRow[] = [
    {
        id: 71,
        reason: 'wrong_answer',
        details: 'I read it is given at closing.',
        by: 'Maria Lopez',
        at: '2027-03-17T09:00:00Z',
        question: {
            id: 801,
            text: 'When must an agent disclose who they represent?',
            options: ['At the first substantive contact', 'At closing'],
            correct: 0,
            source: 'Agency module script.pdf, p. 3',
            review_item_id: 501,
        },
    },
];

/** Two of REOS's Texas starter questions (A9.1), as the Starter bank tab gets them. */
export const starterBank: StarterBankData = {
    topic: 'state_rules',
    counts: {
        to_review: 287,
        approved: 11,
        retired: 2,
        topics: {
            agency: 44,
            contracts: 45,
            ownership: 38,
            finance: 35,
            math: 33,
            state_rules: 92,
        },
    },
    questions: [
        {
            id: 9101,
            course: '',
            lesson: '',
            topic: 'state_rules',
            difficulty: 'medium',
            portion: 'state',
            status: 'to_review',
            source: 'Texas Occupations Code §1101.558',
            en: {
                prompt: 'A Texas license holder must give the Information About Brokerage Services (IABS) notice',
                options: [
                    'at closing',
                    'only to sellers',
                    'when the listing is signed',
                    'at the first substantive communication about specific real property',
                ],
                explanation:
                    'The IABS goes out at the first substantive communication with a party about specific property.',
            },
            es: {
                prompt: 'Un agente con licencia de Texas debe entregar el aviso de Información sobre Servicios de Corretaje (IABS)',
                options: [
                    'en el cierre',
                    'solo a los vendedores',
                    'cuando se firma el listado',
                    'en la primera comunicación sustancial sobre una propiedad específica',
                ],
                explanation:
                    'El IABS se entrega en la primera comunicación sustancial con una parte sobre una propiedad específica.',
            },
            correct: 3,
        },
        {
            id: 9102,
            course: '',
            lesson: '',
            topic: 'state_rules',
            difficulty: 'medium',
            portion: 'state',
            status: 'to_review',
            source: 'Texas Property Code §41.002',
            en: {
                prompt: 'A Texas urban homestead is protected up to',
                options: ['1 acre', '10 acres', '100 acres', '200 acres'],
                explanation:
                    'An urban homestead can be up to 10 acres in one or more contiguous lots.',
            },
            es: {
                prompt: 'En Texas, la vivienda familiar (homestead) urbana está protegida hasta',
                options: ['1 acre', '10 acres', '100 acres', '200 acres'],
                explanation:
                    'La vivienda familiar urbana puede tener hasta 10 acres en uno o más lotes contiguos.',
            },
            correct: 1,
        },
    ],
};
