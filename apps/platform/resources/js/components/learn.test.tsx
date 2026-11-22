import { render as baseRender, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { describe, expect, it, vi } from 'vitest';
import { CourseChecks } from '@/components/course-checks';
import { LessonPlayer } from '@/components/lesson-player';
import { i18n } from '@/lib/i18n';

const post = vi.fn();
const put = vi.fn();

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    router: {
        post: (...args: unknown[]) => post(...args),
        put: (...args: unknown[]) => put(...args),
        delete: vi.fn(),
    },
}));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

const flag = {
    key: `${'a'.repeat(64)}:guarantee`,
    rule: 'guarantee' as const,
    lesson_id: 7,
    lesson_title: '6.2 Agency disclosure',
    kind: 'transcript' as const,
    heading: null,
    page: null,
    seconds: 312,
    excerpt: 'we [[guarantee]] you pass',
    cleared: false,
};

describe('CourseChecks', () => {
    it('shows each open flag where it is, and clears one', async () => {
        render(
            <CourseChecks
                course={{
                    id: 3,
                    title: 'Principles of Real Estate II',
                    status: 'in_review',
                }}
                checks={{ approvals: [], flags: [flag], open: 1, clean: false }}
            />,
        );

        expect(screen.getByText('Promises a result')).toBeInTheDocument();
        expect(screen.getByText(/At 5:12/)).toBeInTheDocument();
        expect(screen.getByText('guarantee').tagName).toBe('MARK');
        expect(
            screen.getByRole('button', { name: 'Approve and publish' }),
        ).toBeDisabled();

        await userEvent.click(
            screen.getByRole('button', {
                name: 'Clear the flag in 6.2 Agency disclosure',
            }),
        );
        expect(post.mock.calls[0]![0]).toBe(
            `/admin/content/courses/3/flags/${flag.key}`,
        );
    });

    it('sends a course back with the note', async () => {
        render(
            <CourseChecks
                course={{
                    id: 3,
                    title: 'Principles of Real Estate II',
                    status: 'in_review',
                }}
                checks={{ approvals: [], flags: [], open: 0, clean: true }}
            />,
        );

        await userEvent.type(
            screen.getByLabelText('Note for the author'),
            'Add the 2026 IABS form.',
        );
        await userEvent.click(
            screen.getByRole('button', { name: 'Send back' }),
        );

        expect(put.mock.calls[0]![0]).toBe('/admin/content/courses/3/status');
        expect(put.mock.calls[0]![1]).toEqual({
            status: 'draft',
            note: 'Add the 2026 IABS form.',
        });
    });
});

describe('LessonPlayer', () => {
    it('says where it starts, and marks the transcript line playing', () => {
        render(
            <LessonPlayer
                title="6.2 Agency disclosure"
                video={{ type: 'file', url: '', duration: 754, poster: null }}
                transcript={[
                    { seconds: 1, text: 'Welcome back.' },
                    { seconds: 245, text: 'Hand them the IABS form.' },
                ]}
                startAt={245}
            />,
        );

        expect(screen.getByText('Starts at 4:05')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: /^4:05/ })).toHaveAttribute(
            'aria-current',
            'true',
        );
        expect(
            screen.getByRole('button', { name: /^0:01/ }),
        ).not.toHaveAttribute('aria-current');
    });
});
