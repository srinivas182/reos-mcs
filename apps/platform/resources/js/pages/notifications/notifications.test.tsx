import { render as baseRender, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { i18n } from '@/lib/i18n';
import Notifications from '@/pages/notifications/index';
import NotificationSettings from '@/pages/settings/notifications';

const router = vi.hoisted(() => ({
    post: vi.fn(),
    put: vi.fn(),
    on: vi.fn(() => () => {}),
}));

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    Head: () => null,
    router,
    usePage: () => ({ url: '/notifications', props: { errors: {} } }),
}));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

describe('Notifications (B6.2)', () => {
    beforeEach(() => {
        router.post.mockClear();
        router.put.mockClear();
    });

    it('lists the inbox and marks all read', async () => {
        render(
            <Notifications
                unread={1}
                items={[
                    {
                        id: 'n-1',
                        kind: 'announcement',
                        title: 'Office closed Friday afternoon',
                        body: 'The office closes at 1 PM.',
                        url: null,
                        read: false,
                        at: new Date().toISOString(),
                    },
                    {
                        id: 'n-2',
                        kind: 'purchase_receipt',
                        title: 'Your receipt',
                        body: null,
                        url: null,
                        read: true,
                        at: new Date().toISOString(),
                    },
                ]}
            />,
        );
        expect(
            screen.getByRole('button', {
                name: /Office closed Friday afternoon.*unread/,
            }),
        ).toBeInTheDocument();
        await userEvent.click(
            screen.getByRole('button', {
                name: /Office closed Friday afternoon/,
            }),
        );
        expect(router.post).toHaveBeenCalledWith(
            '/notifications/n-1/read',
            {},
            expect.anything(),
        );
        await userEvent.click(
            screen.getByRole('button', { name: 'Mark all read' }),
        );
        expect(router.post).toHaveBeenCalledWith(
            '/notifications/read',
            {},
            expect.anything(),
        );
    });

    it('sets channels per kind, digests and quiet hours', async () => {
        render(
            <NotificationSettings
                kinds={[
                    {
                        kind: 'announcement',
                        group: 'messages',
                        locked: false,
                        urgent: false,
                        digestible: true,
                        channels: { inbox: true, push: true, email: false },
                        digest: null,
                    },
                    {
                        kind: 'new_device',
                        group: 'account',
                        locked: true,
                        urgent: true,
                        digestible: false,
                        channels: { inbox: true, push: true, email: true },
                        digest: null,
                    },
                ]}
                channels={['inbox', 'push', 'email']}
                phones={1}
                quiet={{ enabled: true, start: '21:00', end: '07:00' }}
                timezone="America/Chicago"
                browserTimezone="America/Chicago"
            />,
        );
        expect(
            screen.getByText('Push goes to 1 phone with the REOS app.'),
        ).toBeInTheDocument();
        await userEvent.click(
            screen.getByRole('switch', { name: 'Announcements by Email' }),
        );
        await userEvent.click(
            screen.getByRole('switch', { name: 'Announcements by Push' }),
        );
        expect(router.put).toHaveBeenCalledWith(
            '/settings/notifications',
            { channels: { announcement: { push: false } } },
            expect.anything(),
        );
        expect(router.put).toHaveBeenCalledWith(
            '/settings/notifications',
            { channels: { announcement: { email: true } } },
            expect.anything(),
        );
        expect(
            screen.getByRole('switch', { name: 'New device sign-in by Email' }),
        ).toBeDisabled();

        await userEvent.selectOptions(
            screen.getByLabelText('Digest for Announcements'),
            'daily',
        );
        expect(router.put).toHaveBeenCalledWith(
            '/settings/notifications',
            { digests: { announcement: 'daily' } },
            expect.anything(),
        );

        await userEvent.click(
            screen.getByRole('button', { name: 'Save quiet hours' }),
        );
        expect(router.put).toHaveBeenCalledWith(
            '/settings/notifications',
            { quiet: { enabled: true, start: '21:00', end: '07:00' } },
            expect.anything(),
        );
    });
});
