import { render as baseRender, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { modeState } from '@/components/agents/fixtures';
import { i18n } from '@/lib/i18n';
import AgentSetup from '@/pages/agent/setup';

const router = vi.hoisted(() => ({
    visit: vi.fn(),
    reload: vi.fn(),
    on: vi.fn(() => () => {}),
}));

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    router,
    usePage: () => ({ url: '/agent/setup', props: { errors: {} } }),
}));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

describe('the agent welcome, goal and flags (A11.1a)', () => {
    let fetchMock: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchMock = vi.fn(
            async () =>
                new Response(JSON.stringify({ data: modeState() }), {
                    status: 200,
                    headers: { 'Content-Type': 'application/json' },
                }),
        );
        vi.stubGlobal('fetch', fetchMock);
        router.visit.mockClear();
    });

    afterEach(() => vi.unstubAllGlobals());

    it('confirms the licence, saves the goal and flags, then opens the dashboard', async () => {
        const user = userEvent.setup();
        render(
            <AgentSetup
                mode={modeState({ goal: null, needs_setup: true, flags: [] })}
            />,
        );

        expect(screen.getByText('License verified')).toBeInTheDocument();
        expect(screen.getByText('TX license #0798765')).toBeInTheDocument();
        await user.click(screen.getByRole('button', { name: 'Get started' }));
        expect(
            screen.getByRole('radio', { name: /Get my first closing/ }),
        ).toHaveAttribute('aria-checked', 'true');
        await user.click(screen.getByRole('button', { name: 'Continue' }));

        await user.click(
            await screen.findByRole('switch', { name: /Part-time/ }),
        );
        await user.click(
            screen.getByRole('button', { name: 'Build my home screen' }),
        );
        await waitFor(() =>
            expect(router.visit).toHaveBeenCalledWith('/dashboard'),
        );

        const bodies = fetchMock.mock.calls.map((call) => {
            const init = call[1] as RequestInit | undefined;

            return JSON.parse(
                typeof init?.body === 'string' ? init.body : '{}',
            );
        });
        expect(bodies).toEqual([
            { goal: 'launch', reason: 'setup' },
            { flags: ['part_time'] },
        ]);
    });
});
