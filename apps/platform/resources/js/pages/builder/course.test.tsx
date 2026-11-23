import { render as baseRender, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    course,
    module,
    modules,
    roles,
    settings,
    teaching,
} from '@/components/builder/fixtures';
import { TeachingCard } from '@/components/builder/teaching-card';
import { i18n } from '@/lib/i18n';
import CourseBuilderCourse from '@/pages/builder/course';
import CourseBuilderIndex from '@/pages/builder/index';

const router = vi.hoisted(() => ({
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
    reload: vi.fn(),
    on: vi.fn(() => () => {}),
}));

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    router,
    usePage: () => ({ url: '/course-builder/10', props: { errors: {} } }),
}));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);
const page = () =>
    render(
        <CourseBuilderCourse
            course={course}
            modules={modules}
            module={module}
            settings={settings}
            presenters={[]}
            roles={roles}
        />,
    );

beforeEach(() => Object.values(router).forEach((fn) => fn.mockClear()));

describe('Course builder (screen 43, A7.2)', () => {
    it('shows the module list, its source material, what AI will make and the lessons', () => {
        page();

        expect(
            screen.getByRole('link', {
                name: /Agency in Texas.*Lessons in draft/,
            }),
        ).toHaveAttribute('aria-current', 'page');
        expect(screen.getByText(/Read: 2,310 words/)).toBeInTheDocument();
        expect(
            screen.getByText(
                /Ready. Add its captions file to have its words read./,
            ),
        ).toBeInTheDocument();
        expect(
            screen.getByText('1 file is still being read.'),
        ).toBeInTheDocument();
        expect(screen.getByText('340 credits to generate')).toBeInTheDocument();
        expect(
            screen.getByText(/30 hours, set by approval TREC 0451/),
        ).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Hide course' }),
        ).toBeNull();
    });

    it('approves only a draft with text, and adds and orders lessons', async () => {
        const user = userEvent.setup();
        page();

        expect(
            screen.getByRole('button', { name: 'Approve: The IABS form' }),
        ).toBeDisabled();
        expect(
            screen.getByRole('button', { name: 'Move up: Who you represent' }),
        ).toBeDisabled();
        await user.click(
            screen.getByRole('button', {
                name: 'Move down: Who you represent',
            }),
        );
        expect(router.post).toHaveBeenCalledWith(
            '/course-builder/lessons/21/move',
            { direction: 'down' },
            expect.anything(),
        );

        await user.type(screen.getByLabelText('Lesson title'), 'Intermediary');
        await user.click(screen.getByRole('button', { name: 'Add lesson' }));
        expect(router.post).toHaveBeenCalledWith(
            '/course-builder/modules/1/lessons',
            { title: 'Intermediary' },
            expect.anything(),
        );
    });

    it('sends a seat-time change to the school', async () => {
        const user = userEvent.setup();
        page();

        await user.click(
            screen.getByRole('button', { name: 'Ask to change hours' }),
        );
        await user.clear(screen.getByLabelText('New hours'));
        await user.type(screen.getByLabelText('New hours'), '32');
        await user.type(
            screen.getByLabelText('Why'),
            'New 2027 contract forms.',
        );
        await user.click(
            screen.getByRole('button', { name: 'Send to the school' }),
        );
        expect(router.post).toHaveBeenCalledWith(
            '/course-builder/10/seat-time',
            { hours: 32, reason: 'New 2027 contract forms.' },
            expect.anything(),
        );
    });

    it('lists the courses to build, with an empty state', () => {
        render(
            <CourseBuilderIndex
                courses={[]}
                types={['qualifying', 'ce']}
                deliveries={['self_paced']}
            />,
        );
        expect(screen.getByText('No courses to build yet')).toBeInTheDocument();
    });

    it('shows the instructor dashboard (screen 42)', () => {
        render(<TeachingCard teaching={teaching} />);
        const tiles = screen.getAllByRole('definition');
        expect(
            within(tiles[0].parentElement as HTMLElement).getByText('3'),
        ).toBeInTheDocument();
        expect(screen.getByText('64%')).toBeInTheDocument();
        expect(
            screen.getByText('Agency: 14 of 31 answers wrong'),
        ).toBeInTheDocument();
    });
});
