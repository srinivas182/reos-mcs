import { render as baseRender, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { brief, briefSettings } from '@/components/agents/fixtures';
import { i18n } from '@/lib/i18n';
import BriefPage from '@/pages/agent/brief';

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    usePage: () => ({ url: '/agent/brief', props: { errors: {} } }),
}));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

describe('the morning brief on the web (A11.1c)', () => {
    let fetchMock: ReturnType<typeof vi.fn>;
    const spoken: string[] = [];

    beforeEach(() => {
        fetchMock = vi.fn(async (_url: string, init?: RequestInit) => {
            const body =
                typeof init?.body === 'string'
                    ? (JSON.parse(init.body) as unknown)
                    : {};
            return new Response(JSON.stringify({ data: body }), {
                status: 200,
                headers: { 'Content-Type': 'application/json' },
            });
        });
        vi.stubGlobal('fetch', fetchMock);
        // Radix checkboxes measure themselves; jsdom has no ResizeObserver.
        vi.stubGlobal(
            'ResizeObserver',
            class {
                observe() {}
                unobserve() {}
                disconnect() {}
            },
        );
        spoken.length = 0;
        vi.stubGlobal(
            'SpeechSynthesisUtterance',
            class {
                lang = '';
                onend: (() => void) | null = null;
                constructor(public text: string) {}
            },
        );
        vi.stubGlobal('speechSynthesis', {
            speak: (u: { text: string }) => spoken.push(u.text),
            cancel: vi.fn(),
        });
    });

    afterEach(() => vi.unstubAllGlobals());

    it('reads the script aloud, offers the commands and saves when it goes out', async () => {
        const user = userEvent.setup();
        render(
            <BriefPage
                brief={brief()}
                settings={briefSettings()}
                sections={['todos', 'appointments', 'deadlines', 'leads', 'ce']}
            />,
        );

        expect(
            screen.getByRole('link', { name: 'Call Ana Garcia' }),
        ).toHaveAttribute('href', 'tel:+15125550142');
        expect(
            screen.getByRole('link', { name: 'Open the Oak Ridge deal' }),
        ).toHaveAttribute('href', '/deals?deal=d-oak');
        await user.click(screen.getByRole('button', { name: 'Play' }));
        expect(spoken).toEqual(['Good morning, James.']);
        await user.click(screen.getByRole('button', { name: 'Pause' }));

        await user.click(screen.getByRole('checkbox', { name: 'CE progress' }));
        await user.click(screen.getByRole('button', { name: 'Save' }));
        await waitFor(() => expect(fetchMock).toHaveBeenCalled());
        const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
        expect(url).toContain('/agent/brief/settings');
        expect(
            JSON.parse(typeof init.body === 'string' ? init.body : '{}'),
        ).toMatchObject({
            enabled: true,
            at: '07:00',
            sections: ['todos', 'appointments', 'deadlines', 'leads'],
        });
    });
});
