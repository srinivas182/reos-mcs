import { render as baseRender, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { reporting } from '@/components/ce/reporting-fixtures';
import { i18n } from '@/lib/i18n';
import CeReportsPage from '@/pages/school/ce-reports';

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    usePage: () => ({ url: '/school/ce-reports', props: { errors: {} } }),
}));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

describe('CE reporting to TREC (A11.3)', () => {
    let fetchMock: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchMock = vi.fn(async () => {
            const done = reporting();
            done.reports[0] = {
                ...done.reports[0],
                status: 'reported',
                reference: 'CE-2026-200001',
            };
            return new Response(JSON.stringify({ data: done }), {
                status: 200,
                headers: { 'Content-Type': 'application/json' },
            });
        });
        vi.stubGlobal('fetch', fetchMock);
        vi.stubGlobal(
            'ResizeObserver',
            class {
                observe() {}
                unobserve() {}
                disconnect() {}
            },
        );
    });

    afterEach(() => vi.unstubAllGlobals());

    it("shows Ready, Rejected and Reports, and saves TREC's reference", async () => {
        const user = userEvent.setup();
        render(<CeReportsPage reporting={reporting()} />);

        expect(
            screen.getByText('No Texas license on file'),
        ).toBeInTheDocument();
        expect(
            screen.getAllByText('License number not found').length,
        ).toBeGreaterThan(0);
        expect(screen.getByText('Pending upload')).toBeInTheDocument();

        await user.type(
            screen.getByLabelText('TREC reference'),
            'CE-2026-200001',
        );
        await user.click(
            screen.getByRole('button', { name: 'Save reference' }),
        );
        await waitFor(() =>
            expect(screen.getByText('CE-2026-200001')).toBeInTheDocument(),
        );
        const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
        expect(url).toContain(
            '/school/ce-reports/a1b2c3d4-0000-7000-8000-000000000001/reference',
        );
        expect(
            JSON.parse(typeof init.body === 'string' ? init.body : '{}'),
        ).toEqual({ reference: 'CE-2026-200001' });
    });
});
