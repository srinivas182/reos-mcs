import { Form, Head, Link, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { AgreementNote } from '@/components/consents';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { type Move, MoveSummary } from '@/components/moves/move-summary';
import { i18n } from '@/lib/i18n';
import { join, login, logout } from '@/routes';
import { accept, register, signIn } from '@/routes/invite';
import type { RoleName } from '@/types/auth';

type Props = {
    state: 'open' | 'expired' | 'accepted' | 'revoked' | 'invalid';
    token: string | null;
    invite: {
        tenant: string;
        team: string | null;
        role: RoleName;
        inviter: string | null;
        contact: string;
        channel: 'email' | 'text';
        name: string | null;
        expires_at: string;
    } | null;
    account: 'match' | 'other' | 'existing' | 'new' | null;
    signedInAs: string | null;
    /** An agent at another brokerage or team (A14.3): what moving does. */
    move?: Move | null;
};

/**
 * Accepting an invitation (A4.3a): who invited them, to what and until
 * when; then one tap to join, sign in first, or create the account. On the
 * tenant's own address, so the brand panel shows the tenant.
 */
export default function Invite({
    state,
    token,
    invite,
    account,
    signedInAs,
    move = null,
}: Props) {
    const { t } = useTranslation();

    if (state !== 'open' || invite === null || token === null) {
        return (
            <>
                <Head title={t('invitePage.heading')} />
                <Alert>
                    <AlertDescription>
                        {
                            {
                                open: t('invitePage.invalid'),
                                invalid: t('invitePage.invalid'),
                                expired: t('invitePage.expired'),
                                accepted: t('invitePage.accepted'),
                                revoked: t('invitePage.revoked'),
                            }[state]
                        }
                    </AlertDescription>
                </Alert>
                <div className="flex flex-wrap gap-3">
                    {state === 'invalid' ? (
                        <Button asChild>
                            <Link href={join()}>
                                {t('invitePage.enterCode')}
                            </Link>
                        </Button>
                    ) : null}
                    <Button asChild variant="outline">
                        <Link href={login()}>{t('invitePage.signIn')}</Link>
                    </Button>
                </div>
            </>
        );
    }

    const role = t(`roles.names.${invite.role}`);
    const words = {
        tenant: invite.tenant,
        role,
        team: invite.team,
        inviter: invite.inviter,
    };
    const until = new Intl.DateTimeFormat(i18n.language, {
        dateStyle: 'long',
    }).format(new Date(invite.expires_at));

    return (
        <>
            <Head title={t('invitePage.title', { tenant: invite.tenant })} />
            <div className="space-y-6">
                <div className="space-y-2">
                    <p className="text-lg">
                        {invite.inviter === null
                            ? t('invitePage.invitedNoInviter', words)
                            : invite.team
                              ? t('invitePage.invitedTeam', words)
                              : t('invitePage.invited', words)}
                    </p>
                    <p className="text-sm text-muted-foreground">
                        {t('invitePage.until', { date: until })}
                    </p>
                </div>

                {account === 'match' ? (
                    <Form {...accept.form(token)}>
                        {({ processing, errors }) => (
                            <>
                                {move ? (
                                    <>
                                        <MoveSummary move={move} />
                                        <input
                                            type="hidden"
                                            name="move"
                                            value="1"
                                        />
                                    </>
                                ) : null}
                                <InputError
                                    message={errors.invite ?? errors.move}
                                />
                                <Button
                                    type="submit"
                                    className="w-full"
                                    disabled={processing}
                                >
                                    {processing
                                        ? t('invitePage.joining')
                                        : move
                                          ? t('moves.join', {
                                                to: move.to.name,
                                            })
                                          : t('invitePage.join', {
                                                tenant: invite.tenant,
                                            })}
                                </Button>
                            </>
                        )}
                    </Form>
                ) : null}

                {account === 'other' ? (
                    <div className="space-y-3">
                        <Alert>
                            <AlertDescription>
                                {t('invitePage.other', {
                                    contact: invite.contact,
                                    account: signedInAs ?? '',
                                })}
                            </AlertDescription>
                        </Alert>
                        <Button
                            variant="outline"
                            onClick={() => router.post(logout().url)}
                        >
                            {t('invitePage.signOut')}
                        </Button>
                    </div>
                ) : null}

                {account === 'existing' ? (
                    <div className="space-y-3">
                        <p>
                            {t('invitePage.existing', {
                                contact: invite.contact,
                            })}
                        </p>
                        <Button asChild className="w-full">
                            <a href={signIn(token).url}>
                                {t('invitePage.signInToJoin')}
                            </a>
                        </Button>
                    </div>
                ) : null}

                {account === 'new' ? (
                    <Form
                        {...register.form(token)}
                        resetOnError={['password']}
                        className="space-y-5"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="space-y-1">
                                    <h2 className="font-semibold">
                                        {t('invitePage.createTitle')}
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        {t('invitePage.createFor', {
                                            contact: invite.contact,
                                        })}
                                    </p>
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="invite-name">
                                        {t('invitePage.nameLabel')}
                                    </Label>
                                    <Input
                                        id="invite-name"
                                        name="name"
                                        required
                                        autoComplete="name"
                                        defaultValue={invite.name ?? ''}
                                        aria-invalid={Boolean(errors.name)}
                                    />
                                    <InputError message={errors.name} />
                                </div>
                                {invite.channel === 'email' ? (
                                    <div className="grid gap-2">
                                        <Label htmlFor="invite-password">
                                            {t('invitePage.password')}
                                        </Label>
                                        <PasswordInput
                                            id="invite-password"
                                            name="password"
                                            required
                                            autoComplete="new-password"
                                            aria-invalid={Boolean(
                                                errors.password,
                                            )}
                                        />
                                        <InputError message={errors.password} />
                                    </div>
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        {t('invitePage.phoneNote', {
                                            contact: invite.contact,
                                        })}
                                    </p>
                                )}
                                <InputError message={errors.invite} />
                                <Button
                                    type="submit"
                                    className="w-full"
                                    disabled={processing}
                                >
                                    {processing
                                        ? t('invitePage.creating')
                                        : t('invitePage.create')}
                                </Button>
                                <AgreementNote />
                                <p className="text-center">
                                    <a
                                        href={signIn(token).url}
                                        className="text-foreground underline underline-offset-4"
                                    >
                                        {t('invitePage.haveAccount')}
                                    </a>
                                </p>
                            </>
                        )}
                    </Form>
                ) : null}
            </div>
        </>
    );
}

Invite.layout = {
    get title() {
        return i18n.t('invitePage.heading');
    },
};
