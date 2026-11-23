import { render as baseRender, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { inviteOptions } from '@/components/invite-fixtures';
import { instructors, schoolCourses } from '@/components/instructors/fixtures';
import { i18n } from '@/lib/i18n';
import AvatarConsentSettings from '@/pages/settings/avatar';
import Instructors from '@/pages/school/instructors';

const router = vi.hoisted(() => ({
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
    on: vi.fn(() => () => {}),
}));

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    router,
    usePage: () => ({ url: '/school/instructors', props: { errors: {} } }),
}));

// The checkboxes (Radix) measure themselves; jsdom has no ResizeObserver.
vi.stubGlobal(
    'ResizeObserver',
    class {
        observe() {}
        unobserve() {}
        disconnect() {}
    },
);

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

beforeEach(() => {
    router.put.mockReset();
    router.delete.mockReset();
});

describe('Instructors (screen 52, A7.1b)', () => {
    it("shows each instructor's courses, approvals and avatar consent", () => {
        render(
            <Instructors
                school={{ name: 'Texas Realty School' }}
                instructors={instructors}
                courses={schoolCourses}
                invite={inviteOptions()}
            />,
        );

        const rows = screen.getAllByRole('row');
        expect(
            within(rows[1]).getByText('Given Feb 12, 2027'),
        ).toBeInTheDocument();
        expect(
            within(rows[2]).getByText('Withdrawn: stop using by Mar 31, 2027'),
        ).toBeInTheDocument();
        expect(within(rows[3]).getByText('Invited')).toBeInTheDocument();
        expect(
            within(rows[3]).queryByRole('button', { name: /Revoke/ }),
        ).toBeNull();
    });

    it('sets the courses an instructor teaches', async () => {
        const user = userEvent.setup();
        render(
            <Instructors
                school={{ name: 'Texas Realty School' }}
                instructors={instructors}
                courses={schoolCourses}
                invite={inviteOptions()}
            />,
        );

        await user.click(
            screen.getByRole('button', { name: 'Edit: Dr. Rachel Hughes' }),
        );
        await user.click(
            screen.getByRole('checkbox', { name: 'Contracts for new agents' }),
        );
        await user.click(screen.getByRole('button', { name: 'Save courses' }));

        expect(router.put).toHaveBeenCalledWith(
            '/school/instructors/7/courses',
            { courses: [10, 11] },
            expect.anything(),
        );
    });

    it("records an instructor's avatar ids, only once consent is given (A7.3)", async () => {
        const user = userEvent.setup();
        render(
            <Instructors
                school={{ name: 'Texas Realty School' }}
                instructors={instructors}
                courses={schoolCourses}
                invite={inviteOptions()}
            />,
        );

        expect(
            screen.queryByRole('button', { name: /Avatar ids: Marcus/ }),
        ).toBeNull();
        await user.click(
            screen.getByRole('button', {
                name: 'Avatar ids: Dr. Rachel Hughes',
            }),
        );
        await user.type(
            screen.getByLabelText('HeyGen avatar id'),
            'twin_rachel_01',
        );
        await user.click(
            screen.getByRole('button', { name: 'Save avatar ids' }),
        );

        expect(router.put).toHaveBeenCalledWith(
            '/school/instructors/7/twins',
            { heygen: 'twin_rachel_01', synthesia: null },
            expect.anything(),
        );
    });

    it('revokes with Undo instead of a confirmation', async () => {
        const user = userEvent.setup();
        render(
            <Instructors
                school={{ name: 'Texas Realty School' }}
                instructors={instructors}
                courses={schoolCourses}
                invite={inviteOptions()}
            />,
        );

        await user.click(
            screen.getByRole('button', { name: 'Revoke: Marcus Lee' }),
        );
        expect(router.delete).toHaveBeenCalledWith(
            '/school/instructors/8',
            expect.anything(),
        );
    });

    it('invites instructors when there are none yet', () => {
        render(
            <Instructors
                school={{ name: 'Texas Realty School' }}
                instructors={[]}
                courses={schoolCourses}
                invite={inviteOptions()}
            />,
        );

        expect(screen.getByText('No instructors yet')).toBeInTheDocument();
        expect(
            screen.getAllByRole('button', { name: 'Invite instructors' }),
        ).toHaveLength(2);
    });
});

describe('Avatar consent (A7.1b)', () => {
    it('shows the terms and only lets the instructor agree with a sample', () => {
        render(
            <AvatarConsentSettings
                school={{ name: 'Texas Realty School' }}
                version="2026-11-23"
                consent={null}
            />,
        );

        expect(
            screen.getByText('What you agree to (version 2026-11-23)'),
        ).toBeInTheDocument();
        expect(
            screen.getByText(/used only by Texas Realty School/),
        ).toBeInTheDocument();
        expect(
            screen.getByText(
                'No avatar of you is made or used until you agree here.',
            ),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('button', { name: 'Give consent' }),
        ).toBeDisabled();
    });

    it('withdraws given consent', async () => {
        const user = userEvent.setup();
        render(
            <AvatarConsentSettings
                school={{ name: 'Texas Realty School' }}
                version="2026-11-23"
                consent={{
                    status: 'given',
                    version: '2026-11-23',
                    given_at: '2027-02-12T15:00:00Z',
                    withdrawn_at: null,
                    lessons_due_by: null,
                    sample_url: null,
                    current: true,
                }}
            />,
        );

        expect(
            screen.getByText(
                'You gave consent on Feb 12, 2027 (version 2026-11-23).',
            ),
        ).toBeInTheDocument();
        await user.click(
            screen.getByRole('button', { name: 'Withdraw consent' }),
        );
        expect(router.delete).toHaveBeenCalledWith(
            '/settings/avatar',
            expect.anything(),
        );
    });

    it('sets the most instructors may charge for 1-on-1 sessions (A10.2d)', async () => {
        const user = userEvent.setup();
        render(
            <Instructors
                school={{ name: 'Texas Realty School' }}
                instructors={instructors}
                courses={schoolCourses}
                invite={inviteOptions()}
                session_limits={{ max_30_cents: null, max_60_cents: 9000 }}
            />,
        );

        expect(screen.getByLabelText('Most for 60 minutes ($)')).toHaveValue(
            90,
        );
        await user.type(screen.getByLabelText('Most for 30 minutes ($)'), '40');
        await user.clear(screen.getByLabelText('Most for 60 minutes ($)'));
        await user.click(screen.getByRole('button', { name: 'Save limits' }));

        expect(router.put).toHaveBeenCalledWith(
            '/school/instructors/session-limits',
            { max_30_cents: 4000, max_60_cents: null },
            expect.anything(),
        );
    });
});
