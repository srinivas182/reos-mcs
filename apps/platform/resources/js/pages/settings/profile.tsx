import { Form, Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
/* @chisel-email-verification */
import { Link } from '@inertiajs/react';
/* @end-chisel-email-verification */
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/delete-user';
import { YourData } from '@/components/your-data';
import type { MyPrivacyRequest } from '@/components/your-data';
import Heading from '@/components/heading';
import { LicenceList } from '@/components/licence-list';
import { MembershipList } from '@/components/membership-list';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { putJson } from '@/lib/content';
import { optIn as optInRoute } from '@/routes/practice/groups';
import type { Auth, Licence, LicenceState } from '@/types';
/* @chisel-email-verification */
import { send } from '@/routes/verification';
/* @end-chisel-email-verification */

type PageProps = {
    auth: Auth;
};

export default function Profile(
    /* @chisel-email-verification */
    {
        mustVerifyEmail,
        status,
        privacyRequests = [],
        licences = [],
        licenceStates = [],
        studyGroups = null,
    }: {
        mustVerifyEmail: boolean;
        studyGroups?: { opt_in: boolean } | null;
        status?: string;
        privacyRequests?: MyPrivacyRequest[];
        licences?: Licence[];
        licenceStates?: LicenceState[];
    },
    /* @end-chisel-email-verification */
) {
    const { t } = useTranslation();
    const { auth } = usePage<PageProps>().props;

    return (
        <>
            <Head title={t('settings.profile.title')} />

            {auth.roles ? (
                <div className="space-y-4">
                    <Heading
                        variant="small"
                        title={t('settings.profile.rolesTitle')}
                        description={t('settings.profile.rolesDescription')}
                    />
                    <MembershipList memberships={auth.roles.held} />
                </div>
            ) : null}

            <div className="space-y-4">
                <Heading
                    variant="small"
                    title={t('licences.heading')}
                    description={t('licences.description')}
                />
                <LicenceList licences={licences} states={licenceStates} />
            </div>

            {studyGroups ? (
                <StudyGroupsSetting initial={studyGroups.opt_in} />
            ) : null}

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={t('settings.profile.title')}
                    description={t('settings.profile.description')}
                />

                <Form
                    {...ProfileController.update.form()}
                    options={{
                        preserveScroll: true,
                    }}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">
                                    {t('settings.profile.name')}
                                </Label>

                                <Input
                                    id="name"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user.name ?? ''}
                                    name="name"
                                    required
                                    autoComplete="name"
                                    placeholder={t(
                                        'settings.profile.namePlaceholder',
                                    )}
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.name}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">
                                    {t('settings.profile.email')}
                                </Label>

                                <Input
                                    id="email"
                                    type="email"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user.email ?? ''}
                                    name="email"
                                    required
                                    autoComplete="username"
                                    placeholder={t('settings.profile.email')}
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.email}
                                />
                            </div>

                            {/* @chisel-email-verification */}
                            {mustVerifyEmail &&
                                auth.user.email_verified_at === null && (
                                    <div>
                                        <p className="-mt-4 text-sm text-muted-foreground">
                                            {t('settings.profile.unverified')}{' '}
                                            <Link
                                                href={send()}
                                                as="button"
                                                className="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                                            >
                                                {t('settings.profile.resend')}
                                            </Link>
                                        </p>

                                        {status ===
                                            'verification-link-sent' && (
                                            <div className="mt-2 text-sm font-medium text-reos-success">
                                                {t('settings.profile.sent')}
                                            </div>
                                        )}
                                    </div>
                                )}
                            {/* @end-chisel-email-verification */}

                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={processing}
                                    data-test="update-profile-button"
                                >
                                    {t('settings.profile.save')}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>

            <YourData requests={privacyRequests} />

            <DeleteUser />
        </>
    );
}

/** Study groups (A9.3c): opt in to private groups and their weekly leaderboard; off leaves every group. */
function StudyGroupsSetting({ initial }: { initial: boolean }) {
    const { t } = useTranslation();
    const [on, setOn] = useState(initial);
    const [error, setError] = useState<string | null>(null);

    return (
        <div className="space-y-4">
            <Heading
                variant="small"
                title={t('practice.groups.profileTitle')}
                description={t('practice.groups.description')}
            />
            <div className="flex items-start justify-between gap-4">
                <div className="space-y-1">
                    <Label htmlFor="study-groups">
                        {t('practice.groups.optInLabel')}
                    </Label>
                    <p className="text-sm text-muted-foreground">
                        {t('practice.groups.optInBody')}
                    </p>
                </div>
                <Switch
                    id="study-groups"
                    checked={on}
                    onCheckedChange={(next) => {
                        setOn(next);
                        void putJson(optInRoute().url, { opt_in: next })
                            .then(() => setError(null))
                            .catch((e: unknown) => {
                                setOn(!next);
                                setError(
                                    e instanceof Error ? e.message : String(e),
                                );
                            });
                    }}
                />
            </div>
            {error ? (
                <p role="alert" className="text-body text-destructive">
                    {error}
                </p>
            ) : null}
        </div>
    );
}
