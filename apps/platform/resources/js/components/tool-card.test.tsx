import { render as baseRender, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { ToolCard } from '@/components/tool-card';
import { calendarConnection, tool } from '@/components/tool-fixtures';
import { i18n } from '@/lib/i18n';

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

afterEach(async () => {
    await i18n.changeLanguage('en');
});

describe('ToolCard (web journey screen 19)', () => {
    it('offers Connect through the tool’s own sign-in', () => {
        render(<ToolCard tool={tool()} />);

        expect(
            screen.getByRole('heading', { name: 'Google Calendar and Gmail' }),
        ).toBeInTheDocument();
        expect(screen.getByText('Calendar and email')).toBeInTheDocument();
        expect(screen.getByText('Available')).toBeInTheDocument();
        expect(
            screen.getByRole('link', {
                name: 'Connect Google Calendar and Gmail',
            }),
        ).toHaveAttribute('href', '/connections/google-workspace/connect');
    });

    it('shows Connected with Manage', async () => {
        const onManage = vi.fn();
        render(
            <ToolCard
                tool={tool({ connected: 1 })}
                connection={calendarConnection()}
                onManage={onManage}
            />,
        );

        expect(screen.getByText('Connected')).toBeInTheDocument();
        await userEvent.click(
            screen.getByRole('button', {
                name: 'Manage Google Calendar and Gmail',
            }),
        );
        expect(onManage).toHaveBeenCalledWith(
            expect.objectContaining({ id: 7 }),
        );
    });

    it('asks to sign in again when the tool stopped accepting REOS', () => {
        render(
            <ToolCard
                tool={tool({ connected: 1 })}
                connection={calendarConnection({ status: 'reconnect_needed' })}
            />,
        );

        expect(screen.getByText('Needs sign-in')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Reconnect' })).toHaveAttribute(
            'href',
            '/connections/google-workspace/connect',
        );
    });

    it('takes Notify me and Join beta, then says it’s noted', async () => {
        const onFollow = vi.fn();
        const { rerender } = render(
            <ToolCard
                tool={tool({
                    id: 'lofty',
                    name: 'Lofty',
                    category: 'crm',
                    status: 'coming_soon',
                    action: 'notify',
                })}
                onFollow={onFollow}
            />,
        );

        expect(screen.getByText('Coming soon')).toBeInTheDocument();
        await userEvent.click(
            screen.getByRole('button', { name: 'Notify me · Lofty' }),
        );
        expect(onFollow).toHaveBeenCalledWith(
            expect.objectContaining({ id: 'lofty' }),
        );

        rerender(
            <I18nextProvider i18n={i18n}>
                <ToolCard
                    tool={tool({
                        id: 'lofty',
                        name: 'Lofty',
                        status: 'coming_soon',
                        action: 'notify',
                        interested: true,
                    })}
                />
            </I18nextProvider>,
        );
        expect(screen.getByText("We'll tell you")).toBeInTheDocument();

        rerender(
            <I18nextProvider i18n={i18n}>
                <ToolCard
                    tool={tool({
                        id: 'linkedin',
                        name: 'LinkedIn',
                        category: 'social',
                        status: 'beta',
                        action: 'join_beta',
                    })}
                />
            </I18nextProvider>,
        );
        expect(
            screen.getByRole('button', { name: 'Join beta · LinkedIn' }),
        ).toBeInTheDocument();
    });

    it('says when a tool is temporarily unavailable or set up by the brokerage', () => {
        const { rerender } = render(
            <ToolCard tool={tool({ status: 'paused', action: 'none' })} />,
        );
        expect(screen.getByText('Temporarily unavailable')).toBeInTheDocument();
        expect(screen.getByText('Back soon')).toBeInTheDocument();

        rerender(
            <I18nextProvider i18n={i18n}>
                <ToolCard
                    tool={tool({
                        id: 'skyslope',
                        name: 'SkySlope',
                        accounts: 'tenant',
                    })}
                />
            </I18nextProvider>,
        );
        expect(
            screen.getByText('Set up by your brokerage'),
        ).toBeInTheDocument();
        expect(screen.queryByRole('link')).toBeNull();
    });

    it('speaks Spanish', async () => {
        await i18n.changeLanguage('es');
        render(
            <ToolCard
                tool={tool({ connected: 1 })}
                connection={calendarConnection()}
            />,
        );

        expect(screen.getByText('Conectada')).toBeInTheDocument();
        expect(
            screen.getByRole('button', {
                name: 'Administrar Google Calendar and Gmail',
            }),
        ).toBeInTheDocument();
    });

    it('explains a brokerage tool and asks the broker (A4.3a)', async () => {
        const onFollow = vi.fn();
        const loneWolf = tool({
            id: 'lone-wolf',
            name: 'Lone Wolf Transactions',
            category: 'transactions',
            status: 'coming_soon',
            action: 'ask_broker',
            accounts: 'tenant',
        });
        const { rerender } = render(
            <ToolCard tool={loneWolf} onFollow={onFollow} />,
        );

        expect(
            screen.getByText(
                'Your brokerage sets this up once for all its agents.',
            ),
        ).toBeInTheDocument();
        await userEvent.click(
            screen.getByRole('button', {
                name: 'Ask my broker · Lone Wolf Transactions',
            }),
        );
        expect(onFollow).toHaveBeenCalledWith(loneWolf);

        rerender(
            <I18nextProvider i18n={i18n}>
                <ToolCard tool={{ ...loneWolf, interested: true }} />
            </I18nextProvider>,
        );
        expect(screen.getByText('Asked your broker')).toBeInTheDocument();
    });
});
