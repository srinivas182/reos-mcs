import { render as baseRender, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { i18n } from '@/lib/i18n';
import { OversightBoard, OversightProfileView } from './oversight-board';
import { loneStarBoard, priyaProfile } from './oversight-fixtures';

const api = vi.hoisted(() => ({ putJson: vi.fn() }));
vi.mock('@/lib/content', () => api);
vi.mock('sonner', () => ({
    toast: Object.assign(vi.fn(), { success: vi.fn() }),
}));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);
const routes = {
    agent: (id: number) => `/agents/${id}`,
    settings: '/settings',
};

describe('Agents and deals (A16.1)', () => {
    beforeEach(() => vi.clearAllMocks());

    it('lists who needs help with the reasons, and deals at risk', () => {
        render(<OversightBoard board={loneStarBoard} routes={routes} />);

        expect(
            screen.getByText('1 of 2 agents may need help · 1 deals at risk'),
        ).toBeInTheDocument();
        expect(screen.getByText('Quiet for 14 days')).toBeInTheDocument();
        expect(screen.getByText('No deal in 90 days')).toBeInTheDocument();
        expect(
            screen.getByText('Key date within 72 hours'),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Open James Miller' }),
        ).toHaveAttribute('href', '/agents/3');
    });

    it('saves the brokerage thresholds', async () => {
        api.putJson.mockResolvedValue({ data: loneStarBoard });
        render(<OversightBoard board={loneStarBoard} routes={routes} />);

        const quiet = screen.getByLabelText('Quiet after (days)');
        await userEvent.clear(quiet);
        await userEvent.type(quiet, '7');
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        expect(api.putJson).toHaveBeenCalledWith('/settings', {
            quiet_days: 7,
            no_deal_days: 90,
            new_days: 90,
        });
    });

    it('shows an agent by counts and stages, with the address only under contract', () => {
        render(
            <OversightProfileView
                profile={priyaProfile}
                settings={loneStarBoard.settings}
            />,
        );

        expect(
            screen.getByText('Under contract · Buyer · 1420 Pecan St, Katy'),
        ).toBeInTheDocument();
        expect(screen.getByText('Active · Seller · Katy')).toBeInTheDocument();
        expect(screen.getByText('24')).toBeInTheDocument();
    });
});
