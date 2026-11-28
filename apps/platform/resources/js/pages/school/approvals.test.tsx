import { render as baseRender, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ApprovalsCard } from '@/components/approvals/approvals-card';
import { approvals, options, summary } from '@/components/approvals/fixtures';
import { i18n } from '@/lib/i18n';
import StateApprovals from '@/pages/school/approvals';

const router = vi.hoisted(() => ({
    post: vi.fn(),
    patch: vi.fn(),
    put: vi.fn(),
    on: vi.fn(() => () => {}),
}));

// The audio-only checkbox (Radix, A14.2) measures itself; jsdom has no ResizeObserver.
vi.stubGlobal(
    'ResizeObserver',
    class {
        observe() {}
        unobserve() {}
        disconnect() {}
    },
);

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    router,
    usePage: () => ({ url: '/school/approvals', props: { errors: {} } }),
}));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

beforeEach(() => {
    router.post.mockReset();
    router.patch.mockReset();
    router.put.mockReset();
});

describe('State approvals (screen 50, A7.1)', () => {
    it('lists each approval with its status, and which one labels the course', () => {
        render(
            <StateApprovals
                school={{ name: 'Texas Realty School' }}
                approvals={approvals}
                {...options}
            />,
        );

        const rows = screen.getAllByRole('row');
        expect(within(rows[1]).getByText('TREC 0451')).toBeInTheDocument();
        expect(
            within(rows[1]).getByText('Labels the course'),
        ).toBeInTheDocument();
        expect(
            within(rows[2]).getByText('Waiting for approval'),
        ).toBeInTheDocument();
        expect(
            within(rows[2]).getByText(
                /self-paced online, so this approval doesn't label it/,
            ),
        ).toBeInTheDocument();
        expect(
            within(rows[3]).getByText('Expires in 25 days'),
        ).toBeInTheDocument();
    });

    it("adds an approval with the state's seat-time rules filled in", async () => {
        const user = userEvent.setup();
        render(
            <StateApprovals
                school={{ name: 'Texas Realty School' }}
                approvals={approvals}
                {...options}
            />,
        );

        await user.click(screen.getByRole('button', { name: 'Add approval' }));
        expect(screen.getByLabelText('Most hours in one day')).toHaveValue(12);
        await user.selectOptions(
            screen.getByLabelText('Delivery'),
            'ai_avatar',
        );
        await user.type(screen.getByLabelText('Approval number'), 'TREC 0452');
        await user.click(screen.getByRole('button', { name: 'Save approval' }));

        expect(router.post).toHaveBeenCalledWith(
            '/school/approvals',
            expect.objectContaining({
                course_id: 10,
                state_code: 'TX',
                delivery: 'ai_avatar',
                number: 'TREC 0452',
                max_hours_per_day: 12,
                presence_percent: 100,
            }),
            expect.anything(),
        );
    });

    it('renews with a new expiry date', async () => {
        const user = userEvent.setup();
        render(
            <StateApprovals
                school={{ name: 'Texas Realty School' }}
                approvals={approvals}
                {...options}
            />,
        );

        await user.click(
            screen.getByRole('button', {
                name: 'Renew: Contracts for new agents, TX, Self-paced online',
            }),
        );
        expect(screen.getByLabelText('Expires on')).toHaveValue('');
        await user.type(screen.getByLabelText('Expires on'), '2029-03-26');
        await user.click(screen.getByRole('button', { name: 'Save approval' }));

        expect(router.patch).toHaveBeenCalledWith(
            '/school/approvals/3',
            expect.objectContaining({ expires_on: '2029-03-26' }),
            expect.anything(),
        );
    });

    it('withdraws with Undo instead of a confirmation', async () => {
        const user = userEvent.setup();
        render(
            <StateApprovals
                school={{ name: 'Texas Realty School' }}
                approvals={approvals}
                {...options}
            />,
        );

        await user.click(
            screen.getByRole('button', {
                name: 'Withdraw: Principles of Real Estate II, TX, Self-paced online',
            }),
        );
        expect(router.put).toHaveBeenCalledWith(
            '/school/approvals/1/status',
            { status: 'withdrawn' },
            expect.anything(),
        );
    });

    it('says what to do when the school has no courses', () => {
        render(
            <StateApprovals
                school={{ name: 'Texas Realty School' }}
                approvals={[]}
                {...options}
                courses={[]}
            />,
        );

        expect(screen.getByText('No courses yet')).toBeInTheDocument();
        expect(
            screen.queryByRole('button', { name: 'Add approval' }),
        ).toBeNull();
    });

    it('shows what needs attention on the school dashboard', () => {
        render(<ApprovalsCard summary={summary} />);

        expect(
            screen.getByText('2 approvals in force · 2 courses', {
                exact: false,
            }),
        ).toBeInTheDocument();
        expect(
            screen.getByText(
                /Contracts for new agents · TX: expires Mar 26, 2027/,
            ),
        ).toBeInTheDocument();
        expect(
            screen.getByText(
                /Principles of Real Estate II · TX: waiting for approval/,
            ),
        ).toBeInTheDocument();
    });
});
