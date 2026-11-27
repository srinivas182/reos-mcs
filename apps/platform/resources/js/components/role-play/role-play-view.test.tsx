import { render as baseRender, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { i18n } from '@/lib/i18n';
import {
    activeSession,
    assessmentFixture,
    overviewFixture,
    scoredSession,
} from './fixtures';
import {
    AssessmentCard,
    PracticePicker,
    RecentList,
    SessionView,
} from './role-play-view';

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);
const lessonHref = (lesson: string) => `/learn?topic=${lesson}`;

/** A stand-in for the browser's speech recognition. */
class FakeRecognition {
    static last: FakeRecognition | null = null;
    lang = '';
    interimResults = false;
    continuous = false;
    onresult: ((e: unknown) => void) | null = null;
    onend: (() => void) | null = null;
    onerror: (() => void) | null = null;
    start = vi.fn(() => {
        FakeRecognition.last = this;
    });
    stop = vi.fn(() => this.onend?.());
}

describe('Role-play (A14.1a)', () => {
    afterEach(() => vi.unstubAllGlobals());

    it('starts a practice with the person chosen', async () => {
        const onStart = vi.fn();
        render(
            <PracticePicker
                types={overviewFixture().types}
                busy={false}
                onStart={onStart}
            />,
        );

        expect(screen.getByText('Your best: 84')).toBeInTheDocument();
        await userEvent.click(
            screen.getByRole('button', { name: 'Selling without an agent' }),
        );
        expect(onStart).toHaveBeenCalledWith('prospecting', 'fsbo');
    });

    it('writes spoken words into the answer box to check before sending', async () => {
        vi.stubGlobal('webkitSpeechRecognition', FakeRecognition);
        const onSay = vi.fn();
        render(
            <SessionView
                session={activeSession()}
                busy={false}
                onSay={onSay}
                onFinish={vi.fn()}
                lessonHref={lessonHref}
            />,
        );

        await userEvent.click(screen.getByRole('button', { name: 'Speak' }));
        expect(FakeRecognition.last?.lang).toBe('en-US');
        FakeRecognition.last?.onresult?.({
            resultIndex: 0,
            results: [
                Object.assign(
                    [{ transcript: 'I understand, let me show you the comps' }],
                    { isFinal: true },
                ),
            ],
        });
        expect(
            await screen.findByDisplayValue(
                'I understand, let me show you the comps',
            ),
        ).toBeInTheDocument();
        await userEvent.click(screen.getByRole('button', { name: 'Send' }));
        expect(onSay).toHaveBeenCalledWith(
            'I understand, let me show you the comps',
        );
    });

    it('offers typing when the browser has no speech', () => {
        render(
            <SessionView
                session={activeSession()}
                busy={false}
                onSay={vi.fn()}
                onFinish={vi.fn()}
                lessonHref={lessonHref}
            />,
        );

        expect(
            screen.queryByRole('button', { name: 'Speak' }),
        ).not.toBeInTheDocument();
        expect(
            screen.getByText(
                "Voice isn't available here. Type your answer instead.",
            ),
        ).toBeInTheDocument();
    });

    it('shows the score, the outcome and exactly three fixes with lessons', () => {
        render(
            <SessionView
                session={scoredSession()}
                busy={false}
                onSay={vi.fn()}
                onFinish={vi.fn()}
                lessonHref={lessonHref}
            />,
        );

        expect(screen.getByText('72 out of 100')).toBeInTheDocument();
        expect(
            screen.getByText('They agreed to the next step.'),
        ).toBeInTheDocument();
        expect(screen.getAllByRole('link', { name: /^Lesson:/ })).toHaveLength(
            3,
        );
        expect(screen.queryByLabelText('Your answer')).not.toBeInTheDocument();
    });

    it('shows assessment progress and the weakest skills', async () => {
        const onAssess = vi.fn();
        const { rerender } = render(
            <AssessmentCard
                assessment={assessmentFixture()}
                busy={false}
                onAssess={onAssess}
            />,
        );
        expect(screen.getByText('2 of 5 rounds done')).toBeInTheDocument();
        await userEvent.click(
            screen.getByRole('button', { name: 'Continue round 3' }),
        );
        expect(onAssess).toHaveBeenCalled();

        rerender(
            <I18nextProvider i18n={i18n}>
                <AssessmentCard
                    assessment={assessmentFixture({
                        done: 5,
                        completed: true,
                        next: null,
                        weakest: ['prospecting', 'appraisal_gap'],
                        results: [{ type: 'prospecting', score: 38 }],
                    })}
                    busy={false}
                    onAssess={onAssess}
                />
            </I18nextProvider>,
        );
        expect(
            screen.getByText(
                'Finished. Work on these first: Prospecting call, Appraisal gap',
            ),
        ).toBeInTheDocument();
    });

    it('gives an empty list a way to start', () => {
        render(<RecentList recent={[]} href={(id) => `/role-play/${id}`} />);
        expect(screen.getByText('No practice yet')).toBeInTheDocument();
    });
});
