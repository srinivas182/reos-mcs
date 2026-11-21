import type { Meta, StoryObj } from '@storybook/react-vite';
import { SsoCard, SsoStatusNote } from '@/components/sso-card';
import type { SsoScreen } from '@/types';

const base = 'https://learn.texasrealestate.com/sso/texas-realtors';
const empty: SsoScreen = {
    settings: null,
    service_provider: {
        entity_id: `${base}/metadata`,
        acs_url: `${base}/callback`,
        metadata_url: `${base}/metadata`,
        redirect_uri: `${base}/callback`,
    },
    members: 0,
    test_provider_available: true,
};

const configured = (status: 'draft' | 'tested' | 'on'): SsoScreen => ({
    ...empty,
    test_provider_available: false,
    members: status === 'on' ? 1284 : 0,
    settings: {
        protocol: 'saml',
        status,
        entity_id: 'https://members.texasrealtors.example/idp',
        sso_url: 'https://members.texasrealtors.example/idp/sso',
        metadata_url: 'https://members.texasrealtors.example/idp/metadata',
        has_secret: false,
        certificate_expires: '2028-06-30T00:00:00+00:00',
        test_provider: false,
        last_test:
            status === 'draft'
                ? null
                : {
                      at: '2026-11-21T15:02:00Z',
                      passed: true,
                      detail: 'karen.webb@texasrealtors.example (Karen Webb)',
                  },
        enabled_at: status === 'on' ? '2026-11-21T15:05:00Z' : null,
    },
});

/** Association single sign-on on Branding and domain (screen 57). */
const meta = {
    title: 'Branding/Single sign-on',
    component: SsoCard,
    args: { sso: empty, tenant: 'Texas REALTORS®' },
} satisfies Meta<typeof SsoCard>;

export default meta;
type Story = StoryObj<typeof meta>;

export const NotSetUp: Story = {};
export const NotTested: Story = { args: { sso: configured('draft') } };
export const TestPassed: Story = { args: { sso: configured('tested') } };
export const On: Story = { args: { sso: configured('on') } };
export const OpenIdConnect: Story = {
    args: {
        sso: {
            ...empty,
            test_provider_available: false,
            settings: {
                protocol: 'oidc',
                status: 'tested',
                issuer: 'https://login.texasrealtors.example',
                client_id: 'reos-learning',
                has_secret: true,
                certificate_expires: null,
                test_provider: false,
                last_test: {
                    at: '2026-11-21T15:02:00Z',
                    passed: true,
                    detail: 'karen.webb@texasrealtors.example (Karen Webb)',
                },
                enabled_at: null,
            },
        },
    },
};
export const StatusUnderTheDomain: Story = {
    render: () => (
        <SsoStatusNote sso={configured('on')} tenant="Texas REALTORS®" />
    ),
};
