import { render as baseRender, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { waitingQuestion } from '@/components/ask/fixtures';
import { i18n } from '@/lib/i18n';
import StudentQuestionsPage from '@/pages/instructor/questions';

const router = vi.hoisted(() => ({
    visit: vi.fn(),
    reload: vi.fn(),
    on: vi.fn(() => () => {}),
}));

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    router,
    usePage: () => ({ url: '/instructor/questions', props: { errors: {} } }),
}));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

describe('Student questions (instructor screens i09 and i10, A10.1b)', () => {
    let fetchMock: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchMock = vi.fn(
            async () =>
                new Response(JSON.stringify({ data: waitingQuestion }), {
                    status: 200,
                    headers: { 'Content-Type': 'application/json' },
                }),
        );
        vi.stubGlobal('fetch', fetchMock);
        // The checkboxes (Radix) measure themselves; jsdom has no ResizeObserver.
        vi.stubGlobal(
            'ResizeObserver',
            class {
                observe() {}
                unobserve() {}
                disconnect() {}
            },
        );
    });

    afterEach(() => vi.unstubAllGlobals());

    it('answers a waiting question and adds it to course knowledge by default', async () => {
        const user = userEvent.setup();
        render(
            <StudentQuestionsPage
                data={[waitingQuestion]}
                counts={{ waiting: 1, answered: 4, from_content: 37 }}
                status="waiting"
                selected={waitingQuestion}
            />,
        );

        expect(
            screen.getByText(
                '37 questions answered from your course content in the last 30 days.',
            ),
        ).toBeInTheDocument();
        expect(
            screen.getByText('What the course content said'),
        ).toBeInTheDocument();
        await user.type(
            screen.getByLabelText('Your answer'),
            'Give it once the talk turns to a specific property.',
        );
        await user.click(screen.getByRole('button', { name: 'Send answer' }));

        const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
        expect(url).toBe(
            `/instructor/questions/${waitingQuestion.uuid}/answer`,
        );
        expect(JSON.parse(init.body as string)).toEqual({
            answer: 'Give it once the talk turns to a specific property.',
            add_to_knowledge: true,
            flashcard: false,
        });
        expect(
            await screen.findByText('Answer sent to Ana Ruiz.'),
        ).toBeInTheDocument();
    });

    it('says when nothing is waiting', () => {
        render(
            <StudentQuestionsPage
                data={[]}
                counts={{ waiting: 0, answered: 0, from_content: 0 }}
                status="waiting"
                selected={null}
            />,
        );

        expect(screen.getByText('No questions waiting')).toBeInTheDocument();
    });
});
