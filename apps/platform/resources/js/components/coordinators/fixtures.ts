import type { DealCoordinators, FileDetail, FilesBoardView } from './types';

/** Tina Brooks, a transaction coordinator connected with James Miller (B17.1). */
export const dealCoordinators: DealCoordinators = {
    coordinators: [
        { id: 41, name: 'Tina Brooks', business: 'Brooks Closings' },
        { id: 42, name: 'Omar Diaz', business: 'Omar Diaz' },
    ],
    shares: [
        {
            id: '01a2223d-9b3e-7ee5-91ce-c06e7eb2f4ab',
            coordinator: 'Tina Brooks',
            switches: {
                dates: true,
                documents: true,
                parties: false,
                messages: false,
            },
            status: 'active',
            end_reason: null,
            ended_at: null,
        },
        {
            id: '01a2223d-9b3e-7ee5-91ce-c06e7eb2f4ac',
            coordinator: 'Omar Diaz',
            switches: {
                dates: true,
                documents: true,
                parties: false,
                messages: false,
            },
            status: 'ended',
            end_reason: 'removed',
            ended_at: '2026-11-20T15:00:00Z',
        },
    ],
};

export const filesBoard: FilesBoardView = {
    counts: { active: 3, due_this_week: 1, missing: 2 },
    files: [
        {
            id: '01a2223d-9b3e-7ee5-91ce-c06e7eb2f4ab',
            agent: 'James Miller',
            property: '1302 Live Oak St',
            stage: 'under_contract',
            shared_on: '2026-11-23',
            next: { label: 'Option period ends', due_on: '2026-11-27' },
            missing: 1,
            progress: { done: 2, total: 4 },
        },
        {
            id: '01a2223d-9b3e-7ee5-91ce-c06e7eb2f4ad',
            agent: 'Maria Garcia',
            property: '88 Cypress Bend',
            stage: 'under_contract',
            shared_on: '2026-11-18',
            next: { label: 'Appraisal', due_on: '2026-12-03' },
            missing: 1,
            progress: { done: 5, total: 7 },
        },
        {
            id: '01a2223d-9b3e-7ee5-91ce-c06e7eb2f4ae',
            agent: 'Priya Shah',
            property: '410 Mason Rd',
            stage: 'active',
            shared_on: '2026-11-21',
            next: null,
            missing: 0,
            progress: { done: 0, total: 1 },
        },
    ],
};

export const fileDetail: FileDetail = {
    id: '01a2223d-9b3e-7ee5-91ce-c06e7eb2f4ab',
    agent: 'James Miller',
    property: '1302 Live Oak St',
    city: 'Katy',
    stage: 'under_contract',
    shared_on: '2026-11-23',
    switches: { dates: true, documents: true, parties: false, messages: true },
    dates: [
        {
            id: '11',
            label: 'Option period ends',
            due_on: '2026-11-27',
            done: false,
        },
        { id: '12', label: 'Closing', due_on: '2026-12-18', done: false },
    ],
    documents: [
        { name: 'Inspection report', status: 'missing', done: false },
        { name: 'Contract', status: 'complete', done: true },
    ],
    parties: null,
    draft: 'Hi James Miller, this is Tina Brooks on 1302 Live Oak St. Still missing: Inspection report. Next date: Option period ends 2026-11-27. Please send what you have when you can.',
    conversation: null,
};
