import { render as baseRender, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { agentPlan, scorecard } from '@/components/agents/fixtures';
import { i18n } from '@/lib/i18n';
import AgentPlanPage from '@/pages/agent/plan';

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    usePage: () => ({ url: '/agent/plan', props: { errors: {} } }),
}));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

describe('the 90-day plan and scorecard (A11.1b)', () => {
    let fetchMock: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchMock = vi.fn(async (_url: string, init?: RequestInit) => {
            const body = JSON.parse(
                typeof init?.body === 'string' ? init.body : '{}',
            ) as {
                done?: boolean;
            };
            const plan =
                body.done === undefined
                    ? agentPlan({ promised: true })
                    : agentPlan({
                          done: 6,
                          tasks: agentPlan().tasks.map((t) =>
                              t.code === 'call_sphere'
                                  ? { ...t, done: body.done === true }
                                  : t,
                          ),
                      });
            return new Response(JSON.stringify({ data: plan }), {
                status: 200,
                headers: { 'Content-Type': 'application/json' },
            });
        });
        vi.stubGlobal('fetch', fetchMock);
    });

    afterEach(() => vi.unstubAllGlobals());

    it('ticks a task for its week and makes the promise', async () => {
        const user = userEvent.setup();
        render(<AgentPlanPage plan={agentPlan()} scorecard={scorecard()} />);

        expect(screen.getByText('Week 3 of 13')).toBeInTheDocument();
        expect(
            screen.getByText(/Conversations are behind/),
        ).toBeInTheDocument();
        await user.click(
            screen.getByRole('button', {
                name: 'Done: Call 10 people you know',
            }),
        );
        await waitFor(() =>
            expect(
                screen.getByRole('button', {
                    name: 'Done: Call 10 people you know',
                }),
            ).toHaveAttribute('aria-pressed', 'true'),
        );
        const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
        expect(url).toContain('/agent/plan/tasks/call_sphere');
        expect(
            JSON.parse(typeof init.body === 'string' ? init.body : '{}'),
        ).toEqual({ week: 3, done: true });

        await user.click(screen.getByRole('button', { name: 'I’m in' }));
        expect(await screen.findByText(/Promise made/)).toBeInTheDocument();
    });
});
