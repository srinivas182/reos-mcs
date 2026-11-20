import { Form, usePage } from '@inertiajs/react';
import { useRef } from 'react';
import { useTranslation } from 'react-i18next';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import type { Auth } from '@/types';

/**
 * Delete account (required by both app stores). Signs out everywhere now and
 * deletes after 30 days; signing in again keeps the account. People with a
 * password confirm with it; people without one tick "I understand".
 */
export default function DeleteUser() {
    const { t } = useTranslation();
    const { auth } = usePage<{ auth: Auth }>().props;
    const hasPassword = auth.hasPassword;
    const passwordInput = useRef<HTMLInputElement>(null);

    return (
        <div className="space-y-6">
            <Heading
                variant="small"
                title={t('settings.deleteAccount.title')}
                description={t('settings.deleteAccount.description')}
            />
            <Dialog>
                <DialogTrigger asChild>
                    <Button
                        variant="destructive"
                        data-test="delete-user-button"
                    >
                        {t('settings.deleteAccount.button')}
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <DialogTitle>
                        {t('settings.deleteAccount.confirmTitle')}
                    </DialogTitle>
                    <DialogDescription>
                        {t('settings.deleteAccount.confirmBody')}
                    </DialogDescription>

                    <Form
                        {...ProfileController.destroy.form()}
                        options={{ preserveScroll: true }}
                        onError={() => passwordInput.current?.focus()}
                        resetOnSuccess
                        className="space-y-6"
                    >
                        {({ resetAndClearErrors, processing, errors }) => (
                            <>
                                {hasPassword ? (
                                    <div className="grid gap-2">
                                        <Label htmlFor="password">
                                            {t(
                                                'settings.deleteAccount.passwordLabel',
                                            )}
                                        </Label>
                                        <PasswordInput
                                            id="password"
                                            name="password"
                                            ref={passwordInput}
                                            autoComplete="current-password"
                                        />
                                        <InputError message={errors.password} />
                                    </div>
                                ) : (
                                    <div className="grid gap-2">
                                        <div className="flex items-center gap-3">
                                            <Checkbox
                                                id="confirm"
                                                name="confirm"
                                                value="1"
                                            />
                                            <Label htmlFor="confirm">
                                                {t(
                                                    'settings.deleteAccount.understand',
                                                )}
                                            </Label>
                                        </div>
                                        <InputError message={errors.confirm} />
                                    </div>
                                )}

                                <DialogFooter className="gap-2">
                                    <DialogClose asChild>
                                        <Button
                                            variant="secondary"
                                            onClick={() =>
                                                resetAndClearErrors()
                                            }
                                        >
                                            {t('settings.deleteAccount.cancel')}
                                        </Button>
                                    </DialogClose>
                                    <Button
                                        type="submit"
                                        variant="destructive"
                                        disabled={processing}
                                        data-test="confirm-delete-user-button"
                                    >
                                        {t('settings.deleteAccount.submit')}
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>
        </div>
    );
}
