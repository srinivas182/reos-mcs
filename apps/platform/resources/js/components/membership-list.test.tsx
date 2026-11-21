import { render as baseRender, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { describe, expect, it, vi } from 'vitest';
import { MembershipList } from '@/components/membership-list';
import { i18n } from '@/lib/i18n';
import type { Membership } from '@/types/auth';

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

const instructor: Membership = {
    role: 'instructor',
    organisation: { id: 3, name: 'Texas Realty School', type: 'school' },
    admin: false,
};

describe('MembershipList', () => {
    it('lists roles with their organisation and removes one', async () => {
        const onRemove = vi.fn();
        render(
            <MembershipList memberships={[instructor]} onRemove={onRemove} />,
        );

        expect(screen.getByText('Instructor')).toBeInTheDocument();
        expect(
            screen.getByText(/Texas Realty School · School/),
        ).toBeInTheDocument();

        await userEvent.click(
            screen.getByRole('button', {
                name: 'Remove Instructor at Texas Realty School',
            }),
        );
        expect(onRemove).toHaveBeenCalledWith(instructor);
    });

    it('shows an empty state with no roles', () => {
        render(<MembershipList memberships={[]} />);

        expect(
            screen.getByRole('region', { name: 'No roles yet' }),
        ).toBeInTheDocument();
    });
});
