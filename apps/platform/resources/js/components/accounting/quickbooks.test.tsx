import { render as baseRender, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { i18n } from '@/lib/i18n';
import { loneStarBooks, notConnected } from './fixtures';
import { QuickBooksPanel } from './quickbooks';

const api = vi.hoisted(() => ({
    postJson: vi.fn(),
    putJson: vi.fn(),
    deleteJson: vi.fn(),
}));
vi.mock('@/lib/content', () => api);
const toast = vi.hoisted(() =>
    Object.assign(vi.fn(), { success: vi.fn(), error: vi.fn() }),
);
vi.mock('sonner', () => ({ toast }));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);
const routes = {
    connect: '/c',
    accounts: '/a',
    export: '/e',
    disconnect: '/d',
};

describe('QuickBooks (B17.3)', () => {
    beforeEach(() => vi.clearAllMocks());

    it('sends the broker to Intuit to connect', async () => {
        const onRedirect = vi.fn();
        api.postJson.mockResolvedValue({
            data: { url: 'https://appcenter.intuit.com/connect/oauth2?x=1' },
        });
        render(
            <QuickBooksPanel
                board={notConnected}
                routes={routes}
                onChanged={() => undefined}
                onRedirect={onRedirect}
            />,
        );
        await userEvent.click(
            screen.getByRole('button', { name: 'Connect QuickBooks' }),
        );
        expect(onRedirect).toHaveBeenCalledWith(
            'https://appcenter.intuit.com/connect/oauth2?x=1',
        );
        expect(screen.queryByRole('button', { name: 'Export now' })).toBeNull();
    });

    it('maps an account and exports what is waiting', async () => {
        const onChanged = vi.fn();
        api.putJson.mockResolvedValue({ data: { mapped: true } });
        api.postJson.mockResolvedValue({ data: { posted: 2, failed: 0 } });
        render(
            <QuickBooksPanel
                board={loneStarBooks}
                routes={routes}
                onChanged={onChanged}
            />,
        );

        expect(
            screen.getByText('Simulated: nothing is sent to Intuit.'),
        ).toBeInTheDocument();
        expect(
            screen.getByText('88 Cypress Bend · Maria Garcia · $9,600'),
        ).toBeInTheDocument();
        await userEvent.selectOptions(
            screen.getByLabelText('Brokerage share (income)'),
            '80',
        );
        await userEvent.click(
            screen.getByRole('button', { name: 'Save accounts' }),
        );
        expect(api.putJson).toHaveBeenCalledWith('/a', {
            accounts: {
                ...loneStarBooks.connection?.accounts,
                brokerage_income: '80',
            },
        });

        await userEvent.click(
            screen.getByRole('button', { name: 'Export now' }),
        );
        expect(api.postJson).toHaveBeenCalledWith('/e');
        expect(toast.success).toHaveBeenLastCalledWith(
            "Posted 2. 0 didn't post.",
        );
        expect(screen.getByText('Posted as JE-4F2A91C0')).toBeInTheDocument();
    });
});
