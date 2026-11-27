import type { MadePost, Moment } from './types';

/** Jordan Reyes passed the Texas exam and keeps a study streak (REOS demo content). */
export const momentsFixture = (): Moment[] => [
    { milestone: 'exam_pass', label: 'Texas real estate exam', made: null },
    { milestone: 'study_streak', label: '14-day study streak', made: 'c-2' },
];

export const madeFixture = (): MadePost => ({
    id: 'c-1',
    milestone: 'exam_pass',
    label: 'Texas real estate exam',
    captions: [
        {
            tone: 'professional',
            text: 'I passed the Texas real estate exam! Next step: my license. Thank you to everyone who helped me study. #FutureAgent #TexasRealEstate',
        },
        {
            tone: 'warm',
            text: 'I did it: I passed the Texas real estate exam! So grateful for the support along the way. #FutureAgent #RealEstateExam',
        },
        {
            tone: 'short',
            text: 'Passed the Texas real estate exam! #FutureAgent',
        },
    ],
    designs: [
        { layout: 'band', path: 'a.png', url: '' },
        { layout: 'top', path: 'b.png', url: '' },
        { layout: 'split', path: 'c.png', url: '' },
    ],
    checks: [
        { caption: 0, ok: true, issues: [] },
        { caption: 1, ok: true, issues: [] },
        { caption: 2, ok: true, issues: [] },
    ],
});
