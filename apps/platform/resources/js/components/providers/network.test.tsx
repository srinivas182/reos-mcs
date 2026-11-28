import { render as baseRender, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { i18n } from '@/lib/i18n';
import { ConnectionCard, ProviderRequestsInbox } from './network';
import {
    bothShared,
    jamesNetwork,
    jamesShared,
    mayaSide,
} from './network-fixtures';

const api = vi.hoisted(() => ({ postJson: vi.fn(), putJson: vi.fn() }));
vi.mock('@/lib/content', () => api);
const toast = vi.hoisted(() =>
    Object.assign(vi.fn(), { success: vi.fn(), error: vi.fn() }),
);
vi.mock('sonner', () => ({ toast }));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);
const routes = {
    accept: (id: string) => `/providers/requests/${id}/accept`,
    decline: (id: string) => `/providers/requests/${id}/decline`,
    share: (id: string) => `/provider-connections/${id}/share`,
    block: (id: string) => `/provider-connections/${id}/block`,
    preferences: '/providers/preferences',
    chat: (c: string) => `/messages?c=${c}`,
};

describe('The private network (B16.2)', () => {
    beforeEach(() => vi.clearAllMocks());

    it('shows contacts only once both have shared', async () => {
        api.putJson.mockResolvedValue({
            data: {
                ...bothShared,
                side: 'provider',
                with: 'James Miller',
                contacts: {
                    email: 'james.miller@demo.reos.test',
                    phone: '+1 713 555 0101',
                },
            },
        });
        render(
            <ul>
                <ConnectionCard connection={mayaSide} routes={routes} />
            </ul>,
        );

        expect(
            screen.getByText(/James Miller shared. Press Share/),
        ).toBeInTheDocument();
        expect(screen.queryByText(/james.miller@/)).not.toBeInTheDocument();
        await userEvent.click(
            screen.getByRole('button', { name: 'Share my phone and email' }),
        );
        expect(api.putJson).toHaveBeenCalledWith(
            `/provider-connections/${mayaSide.id}/share`,
            { share: true },
        );
        expect(
            await screen.findByText('Email: james.miller@demo.reos.test'),
        ).toBeInTheDocument();
    });

    it('says who is still to share, and links to the chat', () => {
        render(
            <ul>
                <ConnectionCard connection={jamesShared} routes={routes} />
            </ul>,
        );

        expect(
            screen.getByText(/You shared. Maya Chen hasn't shared yet/),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Message Maya Chen' }),
        ).toHaveAttribute('href', `/messages?c=${jamesShared.conversation}`);
    });

    it('accepts or declines and blocks a waiting request', async () => {
        api.postJson.mockResolvedValueOnce({
            data: {
                ...jamesShared,
                id: 'new',
                with: 'Ravi Patel',
                provider: jamesNetwork.pending[0]!.provider,
            },
        });
        const { unmount } = render(
            <ProviderRequestsInbox network={jamesNetwork} routes={routes} />,
        );

        await userEvent.click(
            screen.getByRole('button', { name: 'Accept Patel Inspections' }),
        );
        expect(api.postJson).toHaveBeenCalledWith(
            `/providers/requests/${jamesNetwork.pending[0]!.id}/accept`,
        );
        expect(toast.success).toHaveBeenCalledWith(
            'Connected with Patel Inspections. Your chat is in Messages.',
        );
        expect(screen.getByText('No requests waiting')).toBeInTheDocument();
        unmount();

        api.postJson.mockResolvedValueOnce({ declined: true });
        render(
            <ProviderRequestsInbox network={jamesNetwork} routes={routes} />,
        );
        await userEvent.click(
            screen.getByRole('button', {
                name: 'Decline and block Patel Inspections',
            }),
        );
        expect(api.postJson).toHaveBeenLastCalledWith(
            `/providers/requests/${jamesNetwork.pending[0]!.id}/decline`,
            { block: true },
        );
    });

    it('saves which categories can ask', async () => {
        api.putJson.mockResolvedValue({ data: jamesNetwork.preferences });
        render(
            <ProviderRequestsInbox network={jamesNetwork} routes={routes} />,
        );

        await userEvent.click(screen.getByLabelText('Loan officer'));
        await userEvent.click(screen.getByRole('button', { name: 'Save' }));
        expect(api.putJson).toHaveBeenCalledWith('/providers/preferences', {
            all: true,
            off: ['loan-officer', 'title-company'],
        });
    });
});
