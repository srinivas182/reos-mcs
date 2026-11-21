import { Form, Head, router, usePage } from '@inertiajs/react';
import { Monitor, Smartphone } from 'lucide-react';
import { useRef } from 'react';
import { useTranslation } from 'react-i18next';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import { EmptyState } from '@/components/empty-state';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { TwoStepNotice } from '@/components/two-step-notice';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { i18n } from '@/lib/i18n';
import { activeMembership, roleName } from '@/lib/roles';
import { destroy as destroyDevice } from '@/routes/devices';
import { edit } from '@/routes/security';
/* @chisel-passkeys */
import type { Props as ManagePasskeysProps } from '@/components/manage-passkeys';
import ManagePasskeys from '@/components/manage-passkeys';
/* @end-chisel-passkeys */
/* @chisel-2fa */
import type { Props as ManageTwoFactorProps } from '@/components/manage-two-factor';
import ManageTwoFactor from '@/components/manage-two-factor';
/* @end-chisel-2fa */

type Device = {
    id: number;
    name: string;
    platform: string | null;
    app_version: string | null;
    last_used_at: string | null;
    signed_in_at: string | null;
    current: boolean;
};

// oxfmt-ignore
type Props = {
    passwordRules: string;
    hasPassword: boolean;
    devices: Device[];
    browsers: { name: string; last_seen_at: string }[];
} /* @chisel-passkeys */ & ManagePasskeysProps /* @end-chisel-passkeys */ /* @chisel-2fa */ &
    ManageTwoFactorProps; /* @end-chisel-2fa */

export default function Security(props: Props) {
    const { t } = useTranslation();
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);
    const { auth } = usePage().props;
    const adminRole = auth.roles?.twoStepRequired
        ? activeMembership(auth.roles)
        : null;

    return (
        <>
            <Head title={t('settings.security.title')} />

            <h1 className="sr-only">{t('settings.security.title')}</h1>

            {/* An admin role is waiting: the two-step choices come first. */}
            {adminRole ? (
                <>
                    <TwoStepNotice
                        mode="security"
                        role={roleName(t, adminRole.role)}
                    />
                    <TwoStepOptions {...props} />
                </>
            ) : null}

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={
                        props.hasPassword
                            ? t('settings.security.password.title')
                            : t('settings.security.password.addTitle')
                    }
                    description={
                        props.hasPassword
                            ? t('settings.security.password.description')
                            : t('settings.security.password.addDescription')
                    }
                />

                <Form
                    {...SecurityController.update.form()}
                    options={{
                        preserveScroll: true,
                    }}
                    resetOnError={[
                        'password',
                        'password_confirmation',
                        'current_password',
                    ]}
                    resetOnSuccess
                    onError={(errors) => {
                        if (errors.password) {
                            passwordInput.current?.focus();
                        }

                        if (errors.current_password) {
                            currentPasswordInput.current?.focus();
                        }
                    }}
                    className="space-y-6"
                >
                    {({ errors, processing }) => (
                        <>
                            {props.hasPassword ? (
                                <div className="grid gap-2">
                                    <Label htmlFor="current_password">
                                        {t(
                                            'settings.security.password.current',
                                        )}
                                    </Label>

                                    <PasswordInput
                                        id="current_password"
                                        ref={currentPasswordInput}
                                        name="current_password"
                                        className="mt-1 block w-full"
                                        autoComplete="current-password"
                                    />

                                    <InputError
                                        message={errors.current_password}
                                    />
                                </div>
                            ) : null}

                            <div className="grid gap-2">
                                <Label htmlFor="password">
                                    {t('settings.security.password.new')}
                                </Label>

                                <PasswordInput
                                    id="password"
                                    ref={passwordInput}
                                    name="password"
                                    className="mt-1 block w-full"
                                    autoComplete="new-password"
                                    passwordrules={props.passwordRules}
                                />

                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">
                                    {t('signIn.common.confirmPassword')}
                                </Label>

                                <PasswordInput
                                    id="password_confirmation"
                                    name="password_confirmation"
                                    className="mt-1 block w-full"
                                    autoComplete="new-password"
                                    passwordrules={props.passwordRules}
                                />

                                <InputError
                                    message={errors.password_confirmation}
                                />
                            </div>

                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={processing}
                                    data-test="update-password-button"
                                >
                                    {t('settings.security.password.save')}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>

            <YourDevices devices={props.devices} browsers={props.browsers} />

            {adminRole ? null : <TwoStepOptions {...props} />}
        </>
    );
}

/** Two-step with an authenticator app, and passkeys. */
function TwoStepOptions(props: Props) {
    return (
        <>
            {/* @chisel-2fa */}
            <ManageTwoFactor
                canManageTwoFactor={props.canManageTwoFactor}
                requiresConfirmation={props.requiresConfirmation}
                twoFactorEnabled={props.twoFactorEnabled}
            />
            {/* @end-chisel-2fa */}

            {/* @chisel-passkeys */}
            <ManagePasskeys
                canManagePasskeys={props.canManagePasskeys}
                passkeys={props.passkeys}
            />
            {/* @end-chisel-passkeys */}
        </>
    );
}

/** Phones and tablets signed in to REOS, and browsers used (A3.1). */
function YourDevices({
    devices,
    browsers,
}: {
    devices: Device[];
    browsers: Props['browsers'];
}) {
    const { t, i18n: language } = useTranslation();
    const when = (value: string | null): string =>
        value === null
            ? ''
            : new Intl.DateTimeFormat(language.language, {
                  dateStyle: 'medium',
                  timeStyle: 'short',
              }).format(new Date(value));

    return (
        <section className="space-y-6">
            <Heading
                variant="small"
                title={t('settings.security.devices.title')}
                description={t('settings.security.devices.description')}
            />

            {devices.length === 0 ? (
                <EmptyState
                    icon={Smartphone}
                    title={t('settings.security.devices.empty')}
                    description={t(
                        'settings.security.devices.emptyDescription',
                    )}
                />
            ) : (
                <ul className="space-y-3">
                    {devices.map((device) => (
                        <li
                            key={device.id}
                            className="flex flex-wrap items-center gap-4 rounded-reos-md border border-border bg-card p-4"
                        >
                            <Smartphone
                                aria-hidden
                                className="size-6 text-muted-foreground"
                            />
                            <div className="min-w-0 flex-1">
                                <p className="font-semibold">{device.name}</p>
                                <p className="text-sm text-muted-foreground">
                                    {device.last_used_at
                                        ? t(
                                              'settings.security.devices.lastUsed',
                                              {
                                                  time: when(
                                                      device.last_used_at,
                                                  ),
                                              },
                                          )
                                        : t(
                                              'settings.security.devices.signedIn',
                                              {
                                                  time: when(
                                                      device.signed_in_at,
                                                  ),
                                              },
                                          )}
                                </p>
                            </div>
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.delete(
                                        destroyDevice(device.id).url,
                                        {
                                            preserveScroll: true,
                                        },
                                    )
                                }
                            >
                                {t('settings.security.devices.signOut')}
                            </Button>
                        </li>
                    ))}
                </ul>
            )}

            {browsers.length > 0 ? (
                <div className="space-y-2">
                    <h3 className="font-semibold">
                        {t('settings.security.devices.browsers')}
                    </h3>
                    <ul className="divide-y divide-border rounded-reos-md border border-border bg-card">
                        {browsers.map((browser) => (
                            <li
                                key={`${browser.name}-${browser.last_seen_at}`}
                                className="flex items-center gap-4 p-4"
                            >
                                <Monitor
                                    aria-hidden
                                    className="size-5 text-muted-foreground"
                                />
                                <span className="flex-1">{browser.name}</span>
                                <span className="text-sm text-muted-foreground">
                                    {t(
                                        'settings.security.devices.browserSeen',
                                        { time: when(browser.last_seen_at) },
                                    )}
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
            ) : null}
        </section>
    );
}

Security.layout = {
    breadcrumbs: [
        {
            get title() {
                return i18n.t('settings.security.title');
            },
            href: edit(),
        },
    ],
};
