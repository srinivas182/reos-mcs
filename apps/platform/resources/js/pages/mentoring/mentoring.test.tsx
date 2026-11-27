import { render as baseRender, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { menteeDetail, mentorToday } from '@/components/mentoring/fixtures';
import { i18n } from '@/lib/i18n';
import MentorWorkspacePage from '@/pages/mentoring/index';
import MenteePage from '@/pages/mentoring/show';

const { reload, toastMock } = vi.hoisted(() => ({
    reload: vi.fn(),
    toastMock: vi.fn(),
}));

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    router: { reload },
}));
vi.mock('sonner', () => ({
    toast: Object.assign(toastMock, { success: vi.fn(), error: vi.fn() }),
}));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

describe('the mentor workspace on the web (A13.1b)', () => {
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

    it('lists who needs the mentor, linking to each mentee', () => {
        render(
            <MentorWorkspacePage
                today={mentorToday()}
                mentees={mentorToday().needs}
            />,
        );
        expect(
            screen.getAllByRole('link', { name: /Priya Shah/ })[0],
        ).toHaveAttribute('href', '/mentoring/21');
        expect(
            screen.getByText(/Pricing my first listing/),
        ).toBeInTheDocument();
    });

    it("removes a private note with Undo, and adds a goal to the mentee's to-dos", async () => {
        const user = userEvent.setup();
        render(<MenteePage detail={menteeDetail()} />);

        await user.click(screen.getByRole('button', { name: 'Note removed' }));
        await waitFor(() =>
            expect(fetchMock).toHaveBeenCalledWith(
                '/mentoring/notes/n-1',
                expect.objectContaining({ method: 'DELETE' }),
            ),
        );
        await waitFor(() =>
            expect(toastMock).toHaveBeenCalledWith(
                'Note removed',
                expect.objectContaining({ action: expect.anything() }),
            ),
        );
        (
            toastMock.mock.calls[0][1] as { action: { onClick: () => void } }
        ).action.onClick();
        await waitFor(() =>
            expect(fetchMock).toHaveBeenCalledWith(
                '/mentoring/notes/n-1/restore',
                expect.objectContaining({ method: 'POST' }),
            ),
        );

        await user.type(
            screen.getByLabelText('Goal'),
            'Call three past clients',
        );
        await user.click(screen.getByRole('button', { name: 'Add a goal' }));
        await waitFor(() =>
            expect(fetchMock).toHaveBeenCalledWith(
                '/mentoring/21/goals',
                expect.objectContaining({
                    method: 'POST',
                    body: JSON.stringify({
                        title: 'Call three past clients',
                        due_date: null,
                    }),
                }),
            ),
        );
    });
});
