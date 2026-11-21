import type { Meta, StoryObj } from '@storybook/react-vite';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';

/** The title, purpose line and main actions every web journey page starts with. */
const meta = {
    title: 'Navigation/Page header',
    component: PageHeader,
    parameters: { layout: 'padded' },
} satisfies Meta<typeof PageHeader>;

export default meta;
type Story = StoryObj<typeof meta>;

/** Web journey screen 64. */
export const IntegrationsCatalogue: Story = {
    args: {
        title: 'Integrations catalogue',
        description:
            'Every tool agents, brokers and providers can connect · status, availability and health',
        actions: (
            <>
                <Button variant="outline">Notify-me demand</Button>
                <Button>Add integration</Button>
            </>
        ),
    },
};

/** Web journey screen 23. */
export const Settings: Story = {
    args: {
        title: 'Settings',
        description: 'Account, roles, privacy and notifications',
    },
};
