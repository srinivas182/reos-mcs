import type { CoursePick } from './course-picks';

/** Course picks for Maria Garcia: renewal in 60 days, practice found the ask weak. */
export const picksFixture = (): CoursePick[] => [
    {
        id: 11,
        title: 'Texas Legal Update I',
        type: 'ce',
        hours: 4,
        reason: 'renewal',
        skill: null,
        audio: true,
        first_lesson: 101,
        percent: 25,
    },
    {
        id: 12,
        title: 'Asking for the next step',
        type: 'elective',
        hours: 2,
        reason: 'skill',
        skill: 'asking_for_business',
        audio: true,
        first_lesson: 201,
        percent: 0,
    },
    {
        id: 13,
        title: 'Your first 90 days',
        type: 'elective',
        hours: 3,
        reason: 'mode',
        skill: null,
        audio: false,
        first_lesson: 301,
        percent: 0,
    },
];
