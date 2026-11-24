import {
    render as baseRender,
    screen,
    waitFor,
    within,
} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { calendarWeek, celebrationList } from '@/components/studio/fixtures';
import { i18n } from '@/lib/i18n';
import StudioCalendarPage from '@/pages/studio/calendar';

const { reload, get, toastMock } = vi.hoisted(() => ({
    reload: vi.fn(),
    get: vi.fn(),
    toastMock: vi.fn(),
}));

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    router: { reload, get },
}));
vi.mock('sonner', () => ({
    toast: Object.assign(toastMock, { success: vi.fn(), error: vi.fn() }),
}));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

describe('the content calendar (B12.3)', () => {
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
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        vi.clearAllMocks();
    });

    it('shows a week of posts, moves one with Undo, and plans a post on a day', async () => {
        const user = userEvent.setup();
        render(
            <StudioCalendarPage
                calendar={calendarWeek()}
                celebrations={celebrationList()}
            />,
        );

        expect(screen.getByText(/6 posts scheduled/)).toBeInTheDocument();
        await user.click(screen.getAllByRole('button', { name: 'Move' })[0]);
        const date = screen.getByLabelText('Move to');
        await user.clear(date);
        await user.type(date, '2026-12-03');
        await user.click(
            within(date.closest('form') as HTMLElement).getByRole('button', {
                name: 'Move',
            }),
        );

        await waitFor(() => expect(fetchMock).toHaveBeenCalled());
        const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
        expect(url).toBe(
            '/studio/posts/0193f1c2-7a10-7d43-9b1e-2f6a4c1d8f01/move',
        );
        expect(
            JSON.parse(typeof init.body === 'string' ? init.body : '{}'),
        ).toEqual({ date: '2026-12-03' });
        await waitFor(() =>
            expect(toastMock).toHaveBeenCalledWith(
                'Moved to December 3',
                expect.objectContaining({ action: expect.anything() }),
            ),
        );
        expect(reload).toHaveBeenCalledWith({ only: ['calendar'] });

        // Undo moves it back to its day.
        (
            toastMock.mock.calls[0][1] as { action: { onClick: () => void } }
        ).action.onClick();
        await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(2));
        const undo = (fetchMock.mock.calls[1] as [string, RequestInit])[1].body;
        expect(JSON.parse(typeof undo === 'string' ? undo : '{}')).toEqual({
            date: '2026-11-30',
        });

        await user.click(
            screen.getAllByRole('button', { name: 'Plan a post' })[2],
        );
        expect(get).toHaveBeenCalledWith('/studio?at=2026-12-01T10%3A00');
        expect(screen.getByText('First closing')).toBeInTheDocument();
    });
});
