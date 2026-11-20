import type { Meta, StoryObj } from '@storybook/react-vite';
import { CredentialField } from '@/components/credential-field';

/**
 * Keys for a connected service (Settings › Integrations). Secrets are never
 * shown again after saving; leaving the box blank keeps the saved value.
 */
const meta = {
    title: 'Forms/Credential field',
    component: CredentialField,
    parameters: { layout: 'padded' },
    decorators: [
        (Story) => (
            <div className="max-w-xl">
                <Story />
            </div>
        ),
    ],
} satisfies Meta<typeof CredentialField>;

export default meta;
type Story = StoryObj<typeof meta>;

export const PlainValue: Story = {
    args: {
        name: 'values[client_id]',
        label: 'Client ID',
        required: true,
        saved: true,
        defaultValue: '418202517-reos.apps.googleusercontent.com',
    },
};

export const NewSecret: Story = {
    args: {
        name: 'values[client_secret]',
        label: 'Client secret',
        secret: true,
        required: true,
    },
};

export const SavedSecret: Story = {
    args: {
        name: 'values[secret_key]',
        label: 'Secret key',
        secret: true,
        required: true,
        saved: true,
        last4: '9f2c',
    },
};

export const OptionalAddress: Story = {
    args: {
        name: 'values[browser_dsn]',
        label: 'Web app DSN',
        kind: 'url',
    },
};

export const PrivateKey: Story = {
    args: {
        name: 'values[private_key]',
        label: 'RSA private key',
        kind: 'multiline',
        secret: true,
    },
};

export const WithError: Story = {
    args: {
        name: 'values[dsn]',
        label: 'Server DSN',
        kind: 'url',
        required: true,
        error: 'The Server DSN field must be a valid URL.',
    },
};
