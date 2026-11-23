import { render as baseRender, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { CertificateDialog } from '@/components/certificate-dialog';
import { i18n } from '@/lib/i18n';

const router = vi.hoisted(() => ({ reload: vi.fn() }));

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    router,
}));
vi.mock('sonner', () => ({ toast: { success: vi.fn() } }));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);
const json = (body: unknown, status = 200) =>
    new Response(JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json' },
    });

describe('adding a CE certificate (B10.3b)', () => {
    let fetchMock: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchMock = vi.fn();
        vi.stubGlobal('fetch', fetchMock);
    });

    afterEach(() => vi.unstubAllGlobals());

    it('reads the upload, lets the person correct it, and saves it', async () => {
        const user = userEvent.setup();
        fetchMock
            .mockResolvedValueOnce(
                json({
                    data: {
                        upload: 'u1',
                        reader: 'simulator',
                        name: 'James Miller',
                        course: 'Legal Update II',
                        hours: 4,
                        completed_on: '2026-11-21',
                        provider: 'Texas Realty Institute (demo)',
                        confidence: {},
                        name_matches: true,
                        states: ['TX'],
                    },
                }),
            )
            .mockResolvedValueOnce(json({ data: { id: 1 } }, 201));
        render(<CertificateDialog open onOpenChange={vi.fn()} />);

        await user.upload(
            screen.getByLabelText(
                'Certificate (photo or one-page PDF, up to 10 MB)',
            ),
            new File(['%PDF'], 'legal-update.pdf', { type: 'application/pdf' }),
        );
        await user.click(
            screen.getByRole('button', { name: 'Read certificate' }),
        );
        const hours = await screen.findByLabelText('Hours');
        await user.clear(hours);
        await user.type(hours, '3');
        await user.click(
            screen.getByRole('button', { name: 'Save certificate' }),
        );

        await waitFor(() =>
            expect(router.reload).toHaveBeenCalledWith({ only: ['licences'] }),
        );
        expect(String(fetchMock.mock.calls[0]?.[0])).toContain(
            '/settings/certificates/read',
        );
        const init = fetchMock.mock.calls[1]?.[1] as RequestInit | undefined;
        expect(
            JSON.parse(typeof init?.body === 'string' ? init.body : '{}'),
        ).toEqual({
            upload: 'u1',
            state: 'TX',
            title: 'Legal Update II',
            hours: 3,
            completed_on: '2026-11-21',
            provider: 'Texas Realty Institute (demo)',
        });
    });
});
