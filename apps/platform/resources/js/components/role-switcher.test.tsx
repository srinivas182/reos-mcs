import { render as baseRender, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement } from 'react';
import { I18nextProvider } from 'react-i18next';
import { describe, expect, it, vi } from 'vitest';
import { RoleSwitcher } from '@/components/role-switcher';
import { i18n } from '@/lib/i18n';
import type { Roles } from '@/types/auth';

const render = (ui: ReactElement) =>
    baseRender(<I18nextProvider i18n={i18n}>{ui}</I18nextProvider>);

const lindaPark: Roles = {
    held: [
        {
            role: 'broker',
            organisation: {
                id: 2,
                name: 'Lone Star Realty',
                type: 'brokerage',
            },
            admin: true,
        },
        {
            role: 'agent',
            organisation: {
                id: 2,
                name: 'Lone Star Realty',
                type: 'brokerage',
            },
            admin: false,
        },
    ],
    active: { role: 'agent', organisationId: 2 },
    permissions: ['learning.study'],
    twoStepEnabled: false,
    twoStepRequired: false,
};

describe('RoleSwitcher', () => {
    it('shows the role in use and switches to another', async () => {
        const onSwitch = vi.fn();
        render(<RoleSwitcher roles={lindaPark} onSwitch={onSwitch} />);

        const trigger = screen.getByRole('button', {
            name: /Your role: Agent · Lone Star Realty/,
        });
        await userEvent.click(trigger);

        const broker = await screen.findByRole('menuitemradio', {
            name: /Broker · Lone Star Realty/,
        });
        expect(broker).toHaveTextContent('Needs two-step');
        expect(
            screen.getByRole('menuitemradio', { name: /Agent/ }),
        ).toHaveAttribute('aria-checked', 'true');

        await userEvent.click(broker);
        expect(onSwitch).toHaveBeenCalledWith(lindaPark.held[0]);
    });

    it('just names the role when there is only one', () => {
        render(
            <RoleSwitcher
                roles={{
                    ...lindaPark,
                    held: [
                        {
                            role: 'candidate',
                            organisation: {
                                id: 1,
                                name: 'REOS',
                                type: 'platform',
                            },
                            admin: false,
                        },
                    ],
                    active: { role: 'candidate', organisationId: 1 },
                }}
                onSwitch={vi.fn()}
            />,
        );

        expect(screen.queryByRole('button')).toBeNull();
        expect(screen.getByText('Candidate · on your own')).toBeInTheDocument();
    });

    it('shows nothing for someone with no role yet', () => {
        const { container } = render(
            <RoleSwitcher
                roles={{ ...lindaPark, held: [], active: null }}
                onSwitch={vi.fn()}
            />,
        );

        expect(container).toBeEmptyDOMElement();
    });
});
