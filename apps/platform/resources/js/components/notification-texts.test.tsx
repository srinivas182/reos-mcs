import { render as baseRender, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { NotificationTexts } from '@/components/notification-texts';
import { i18n } from '@/lib/i18n';

const router = vi.hoisted(() => ({
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
}));

vi.mock('@inertiajs/react', async (original) => ({
    ...(await original<typeof import('@inertiajs/react')>()),
    router,
}));

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

describe('Texts in notification settings (B6.2c)', () => {
    beforeEach(() => {
        router.post.mockReset();
        router.put.mockReset();
    });

    it('verifies a number with a code first', async () => {
        router.post.mockImplementation(
            (
                _url: string,
                _data: unknown,
                options: { onSuccess: () => void },
            ) => options.onSuccess(),
        );
        render(
            <NotificationTexts
                texts={{ phone: null, on: false, available: true }}
            />,
        );
        await userEvent.type(
            screen.getByLabelText('Mobile number'),
            '+15125550100',
        );
        await userEvent.click(
            screen.getByRole('button', { name: 'Send code' }),
        );
        expect(router.post).toHaveBeenCalledWith(
            '/settings/notifications/phone',
            { phone: '+15125550100' },
            expect.anything(),
        );

        await userEvent.type(screen.getByLabelText('Code'), '123456');
        await userEvent.click(
            screen.getByRole('button', { name: 'Verify number' }),
        );
        expect(router.post).toHaveBeenCalledWith(
            '/settings/notifications/phone/verify',
            { phone: '+15125550100', code: '123456' },
            expect.anything(),
        );
    });

    it('turns texts on with the consent words shown', async () => {
        render(
            <NotificationTexts
                texts={{ phone: '••• ••• 0100', on: false, available: true }}
            />,
        );
        expect(
            screen.getByText('Texts go to ••• ••• 0100.'),
        ).toBeInTheDocument();
        const toggle = screen.getByRole('switch', {
            name: 'Get texts from REOS',
        });
        expect(toggle).toHaveAccessibleDescription(
            /Reply HELP for help, STOP to stop\./,
        );
        await userEvent.click(toggle);
        expect(router.put).toHaveBeenCalledWith(
            '/settings/notifications/texts',
            { on: true },
            expect.anything(),
        );
    });

    it('says so when texts are not set up yet', () => {
        render(
            <NotificationTexts
                texts={{ phone: null, on: false, available: false }}
            />,
        );
        expect(
            screen.getByText('Texts aren’t available yet.'),
        ).toBeInTheDocument();
        expect(
            screen.queryByLabelText('Mobile number'),
        ).not.toBeInTheDocument();
    });
});
