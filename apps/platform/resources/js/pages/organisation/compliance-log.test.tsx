import { render as baseRender, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { i18n } from '@/lib/i18n';
import ComplianceLogPage from '@/pages/organisation/compliance-log';

const { get } = vi.hoisted(() => ({ get: vi.fn() }));

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    router: { get },
}));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

describe('the compliance log (B13.2)', () => {
    afterEach(() => vi.clearAllMocks());

    it('lists events and keeps the filters in the export link', async () => {
        render(
            <ComplianceLogPage
                brokerage="Lone Star Realty"
                events={[
                    {
                        id: 1,
                        at: '2026-11-27T15:00:00Z',
                        agent: 'James Miller',
                        actor: 'James Miller',
                        channel: 'text',
                        action: 'fixed',
                        subject: null,
                        issues: [
                            {
                                code: 'banned',
                                phrase: 'motivated seller',
                                citation: 'brokerage',
                            },
                        ],
                        note: null,
                    },
                ]}
                filters={{
                    agent: null,
                    channel: 'text',
                    action: null,
                    from: null,
                    to: null,
                }}
                agents={[{ id: 4, name: 'James Miller' }]}
                channels={['post', 'video', 'page', 'email', 'text']}
                actions={[
                    'checked',
                    'blocked',
                    'fixed',
                    'sent_to_broker',
                    'approved',
                    'sent_back',
                ]}
            />,
        );
        expect(
            screen.getByRole('cell', { name: 'Fixed automatically' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('link', { name: 'Export CSV' }),
        ).toHaveAttribute(
            'href',
            '/organisation/compliance-log/export?channel=text',
        );
        await userEvent.type(screen.getByLabelText('To'), '2026-11-30');
        await userEvent.click(screen.getByRole('button', { name: 'Show' }));
        expect(get).toHaveBeenCalledWith(
            '/organisation/compliance-log',
            { channel: 'text', to: '2026-11-30' },
            expect.anything(),
        );
    });
});
