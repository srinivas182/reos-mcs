import { render as baseRender, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { ProviderLicences } from '@/components/provider-licences';
import { i18n } from '@/lib/i18n';

const router = vi.hoisted(() => ({ reload: vi.fn(), post: vi.fn() }));

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    router,
}));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

describe("a provider's professional license (B10.3)", () => {
    let fetchMock: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchMock = vi.fn(
            async () =>
                new Response(JSON.stringify({ data: {} }), {
                    status: 200,
                    headers: { 'Content-Type': 'application/json' },
                }),
        );
        vi.stubGlobal('fetch', fetchMock);
        router.reload.mockClear();
    });

    afterEach(() => vi.unstubAllGlobals());

    it('checks a TREC inspector number', async () => {
        const user = userEvent.setup();
        render(<ProviderLicences licences={[]} />);

        expect(screen.getByText('No license added yet.')).toBeInTheDocument();
        await user.type(screen.getByLabelText('License number'), '0024567');
        await user.click(screen.getByRole('button', { name: 'Check license' }));

        await waitFor(() =>
            expect(router.reload).toHaveBeenCalledWith({
                only: ['providerLicences'],
            }),
        );
        const [url, init] = fetchMock.mock.calls[0] as [
            string,
            RequestInit | undefined,
        ];
        expect(url).toContain('/settings/provider-licences');
        expect(
            JSON.parse(typeof init?.body === 'string' ? init.body : '{}'),
        ).toEqual({ kind: 'trec_inspector', number: '0024567' });
    });
});
