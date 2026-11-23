import { render as baseRender, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { createRef } from 'react';
import { I18nextProvider } from 'react-i18next';
import { describe, expect, it, vi } from 'vitest';
import { i18n } from '@/lib/i18n';
import type { AskAnswer } from './ask-instructor';
import { AskInstructor } from './ask-instructor';

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

const answer: AskAnswer = {
    answer: 'At the first substantive contact.',
    sources: [
        {
            lesson_id: 7,
            lesson: 'Agency basics',
            seconds: 245,
            page: null,
            heading: null,
            text: '…',
        },
    ],
    quiz: {
        prompt: 'When is the IABS given?',
        options: ['At first contact', 'At closing'],
        correct: 0,
        explanation: 'TREC requires it at first contact.',
    },
    engine: 'ai',
    found: true,
};

function video() {
    const ref = createRef<HTMLVideoElement>();
    const element = document.createElement('video');
    Object.defineProperty(element, 'paused', {
        value: false,
        configurable: true,
    });
    element.currentTime = 312;
    const pause = vi.fn();
    const play = vi.fn(() => Promise.resolve());
    element.pause = pause;
    element.play = play;
    (ref as { current: HTMLVideoElement }).current = element;

    return { ref, element, pause, play };
}

describe('Interrupt and ask', () => {
    it('pauses at the second, answers with the source, and builds on the answer', async () => {
        const send = vi.fn().mockResolvedValue(answer);
        const { ref, element, pause, play } = video();
        render(<AskInstructor lessonId={7} videoRef={ref} send={send} />);

        await userEvent.type(
            screen.getByLabelText('Your question'),
            'When do I give the IABS?',
        );
        await userEvent.click(screen.getByRole('button', { name: 'Ask' }));

        expect(pause).toHaveBeenCalled();
        expect(send).toHaveBeenCalledWith({
            question: 'When do I give the IABS?',
            mode: 'ask',
            previous: null,
            at: 312,
        });
        expect(
            await screen.findByText('At the first substantive contact.'),
        ).toBeInTheDocument();
        expect(screen.getByText('Paused at 5:12')).toBeInTheDocument();

        await userEvent.click(
            screen.getByRole('button', { name: 'Explain simpler' }),
        );
        expect(send).toHaveBeenLastCalledWith({
            question: 'When do I give the IABS?',
            mode: 'simpler',
            previous: 'At the first substantive contact.',
            at: 312,
        });

        await userEvent.click(
            screen.getByRole('button', { name: 'Play from 4:05' }),
        );
        expect(element.currentTime).toBe(245);
        expect(play).toHaveBeenCalled();
    });

    it('checks a quiz answer', async () => {
        render(
            <AskInstructor
                lessonId={7}
                send={vi.fn().mockResolvedValue(answer)}
            />,
        );
        await userEvent.type(screen.getByLabelText('Your question'), 'IABS?');
        await userEvent.click(screen.getByRole('button', { name: 'Ask' }));

        await userEvent.click(await screen.findByLabelText('At closing'));
        await userEvent.click(
            screen.getByRole('button', { name: 'Check answer' }),
        );
        expect(screen.getByText('Not quite.')).toBeInTheDocument();
    });

    it("offers only Resume when the course doesn't cover it", async () => {
        render(
            <AskInstructor
                lessonId={7}
                send={vi.fn().mockResolvedValue({
                    ...answer,
                    answer: 'Not in this course.',
                    sources: [],
                    quiz: null,
                    engine: 'none',
                    found: false,
                })}
            />,
        );
        await userEvent.type(
            screen.getByLabelText('Your question'),
            'What is a 1031?',
        );
        await userEvent.click(screen.getByRole('button', { name: 'Ask' }));

        expect(
            await screen.findByText('Not in this course.'),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Quiz me' }),
        ).not.toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Resume lesson' }),
        ).toBeInTheDocument();
    });
});
