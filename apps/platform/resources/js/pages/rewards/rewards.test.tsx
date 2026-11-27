import { render as baseRender, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { afterEach, describe, expect, it, vi } from 'vitest';
import {
    campaignsFixture,
    modesFixture,
    ratesFixture,
    rewardsFixture,
    todayFixture,
} from '@/components/rewards/fixtures';
import { i18n } from '@/lib/i18n';
import PointRatesPage from '@/pages/admin/points/index';
import BrokerRewardsPage from '@/pages/organisation/rewards';
import RewardsPage from '@/pages/rewards/index';

const { post, put } = vi.hoisted(() => ({
    post: vi.fn(
        (
            _u: string,
            _d: unknown,
            o?: { onSuccess?: () => void; onFinish?: () => void },
        ) => {
            o?.onSuccess?.();
            o?.onFinish?.();
        },
    ),
    put: vi.fn((_u: string, _d: unknown, o?: { onFinish?: () => void }) =>
        o?.onFinish?.(),
    ),
}));

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    router: { post, put },
}));
vi.mock('sonner', () => ({
    toast: Object.assign(vi.fn(), { success: vi.fn(), error: vi.fn() }),
}));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

describe('goals and rewards on the web (A13.3a)', () => {
    afterEach(() => {
        vi.clearAllMocks();
        vi.unstubAllGlobals();
    });

    it('shows today and uses points', async () => {
        render(
            <RewardsPage today={todayFixture()} rewards={rewardsFixture()} />,
        );
        expect(
            screen.getByText('45 points today · +25 bonus points'),
        ).toBeInTheDocument();
        expect(
            screen.getByText('3,120 points · worth $31.20 inside REOS'),
        ).toBeInTheDocument();
        await userEvent.click(
            screen.getByRole('button', { name: 'Use 1500 points' }),
        );
        expect(post).toHaveBeenCalledWith(
            '/rewards/redeem',
            { price: 11 },
            expect.anything(),
        );
    });

    it('copies the streak when the browser has no share sheet', async () => {
        const writeText = vi.fn(async () => undefined);
        Object.defineProperty(navigator, 'share', {
            value: undefined,
            configurable: true,
        });
        Object.defineProperty(navigator, 'clipboard', {
            value: { writeText },
            configurable: true,
        });
        render(
            <RewardsPage today={todayFixture()} rewards={rewardsFixture()} />,
        );
        await userEvent.click(
            screen.getByRole('button', { name: 'Share my streak' }),
        );
        expect(writeText).toHaveBeenCalledWith(
            "I'm on a 4-day streak of meeting my daily goals on REOS.",
        );
    });

    it('lets the broker save a mode and end a campaign', async () => {
        render(
            <BrokerRewardsPage
                brokerage="Lone Star Realty"
                goals={['conversations', 'ce_lesson', 'follow_ups', 'post']}
                modes={modesFixture()}
                campaigns={campaignsFixture()}
                multipliers={[2, 3]}
            />,
        );
        await userEvent.click(
            screen.getAllByRole('button', { name: 'Save' })[0],
        );
        expect(put).toHaveBeenCalledWith(
            '/organisation/rewards/goals/launch',
            {
                targets: {
                    conversations: 5,
                    ce_lesson: 1,
                    follow_ups: 3,
                    post: 1,
                },
            },
            expect.anything(),
        );
        await userEvent.click(screen.getByRole('button', { name: 'End now' }));
        expect(post).toHaveBeenCalledWith(
            '/organisation/rewards/campaigns/3a1b2c3d-4e5f-4a6b-8c7d-9e0f1a2b3c4d/end',
            {},
            expect.anything(),
        );
    });

    it('saves the super admin rates', async () => {
        render(
            <PointRatesPage
                rates={ratesFixture()}
                goals={['conversations', 'ce_lesson', 'follow_ups', 'post']}
                modes={['launch', 'grow', 'lead', 'own']}
            />,
        );
        const cap = screen.getByLabelText('Most points in a day');
        await userEvent.clear(cap);
        await userEvent.type(cap, '300');
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        expect(put).toHaveBeenCalledWith(
            '/admin/points',
            expect.objectContaining({ daily_cap: 300 }),
            expect.anything(),
        );
    });
});
