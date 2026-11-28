import { render as baseRender, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { i18n } from '@/lib/i18n';
import { SetupChecklist } from './setup-checklist';
import { VerifyBrokerage } from './verify-brokerage';

const api = vi.hoisted(() => ({ postJson: vi.fn() }));
vi.mock('@/lib/content', () => api);

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);
const routes = { lookup: '/verify/lookup', confirm: '/verify/confirm' };
const found = {
    licence: {
        number: '9033333',
        name: 'Grace Hill',
        status: '20',
        active: true,
        expires_on: '2027-08-31',
    },
    name_matches: true,
    brokerages: [
        {
            number: '0600123',
            name: 'Bayou City Homes LLC',
            kind: 'entity',
            sponsored: 2,
            on_reos: false,
        },
        {
            number: '9033333',
            name: 'Grace Hill',
            kind: 'individual',
            sponsored: 0,
            on_reos: false,
        },
    ],
};

describe('Verify your brokerage (A15.1a)', () => {
    beforeEach(() => vi.clearAllMocks());

    it('checks the license with TREC, then continues as the designated broker', async () => {
        const onVerified = vi.fn();
        api.postJson
            .mockResolvedValueOnce({ data: found })
            .mockResolvedValueOnce({
                data: {
                    verification: 'v1',
                    role: 'designated_broker',
                    brokerage: 'Bayou City Homes LLC',
                    sponsored: 2,
                },
            });
        render(<VerifyBrokerage routes={routes} onVerified={onVerified} />);

        await userEvent.type(
            screen.getByLabelText('Your TREC broker license number'),
            '9033333',
        );
        await userEvent.click(
            screen.getByRole('button', { name: 'Check with TREC' }),
        );
        expect(
            await screen.findByText(/2 agents sponsored on TREC/),
        ).toBeInTheDocument();
        expect(
            screen.getByText('The name on the license matches your account.'),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('radio', { name: /Bayou City Homes LLC/ }),
        ).toBeChecked();

        await userEvent.click(screen.getByRole('button', { name: 'Continue' }));
        expect(api.postJson).toHaveBeenLastCalledWith('/verify/confirm', {
            licence: '9033333',
            brokerage: '0600123',
            role: 'designated_broker',
        });
        expect(onVerified).toHaveBeenCalledWith(
            expect.objectContaining({ verification: 'v1' }),
        );
    });

    it('tells an office admin to ask the designated broker for an invite', async () => {
        const onVerified = vi.fn();
        api.postJson
            .mockResolvedValueOnce({ data: found })
            .mockResolvedValueOnce({
                data: {
                    verification: null,
                    role: 'office_admin',
                    brokerage: null,
                    sponsored: null,
                },
            });
        render(<VerifyBrokerage routes={routes} onVerified={onVerified} />);
        await userEvent.type(
            screen.getByLabelText('Your TREC broker license number'),
            '9033333',
        );
        await userEvent.click(
            screen.getByRole('button', { name: 'Check with TREC' }),
        );
        await userEvent.click(
            await screen.findByRole('radio', { name: 'Office admin' }),
        );
        await userEvent.click(screen.getByRole('button', { name: 'Continue' }));

        expect(await screen.findByRole('status')).toHaveTextContent(
            /Ask Bayou City Homes LLC's designated broker/,
        );
        expect(onVerified).not.toHaveBeenCalled();
    });
});

describe('Setup checklist (A15.1a)', () => {
    it('links each step still to do', () => {
        render(
            <SetupChecklist
                setup={{
                    trec_number: '0600123',
                    done: 1,
                    total: 2,
                    steps: [
                        {
                            key: 'verify',
                            done: true,
                            href: '/organisation/settings',
                        },
                        { key: 'agents', done: false, href: '/agents' },
                    ],
                }}
            />,
        );
        expect(screen.getByText(/1 of 2 done/)).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Start: Bring in your agents' }),
        ).toHaveAttribute('href', '/agents');
        expect(
            screen.queryByRole('link', { name: /Verified with TREC/ }),
        ).not.toBeInTheDocument();
    });
});
