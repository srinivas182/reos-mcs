import { router } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { destroy } from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyRegistrationController';
import { EmptyState } from '@/components/empty-state';
import Heading from '@/components/heading';
import PasskeyItem from '@/components/passkey-item';
import PasskeyRegistration from '@/components/passkey-register';
import type { Passkey } from '@/types/auth';

export type Props = {
    canManagePasskeys?: boolean;
    passkeys?: Passkey[];
};

export default function ManagePasskeys(props: Props) {
    const { t } = useTranslation();
    const passkeys = props.passkeys ?? [];

    const handleDelete = (id: number, onError: () => void) => {
        router.delete(destroy.url(id), {
            preserveScroll: true,
            onError,
        });
    };

    const handleRegisterSuccess = () => {
        router.reload();
    };

    if (!(props.canManagePasskeys ?? false)) {
        return null;
    }

    return (
        <div className="space-y-6">
            <Heading
                variant="small"
                title={t('settings.passkeys.title')}
                description={t('settings.passkeys.description')}
            />

            {passkeys.length > 0 ? (
                <div className="overflow-hidden rounded-reos-md border border-border bg-card">
                    {passkeys.map((passkey) => (
                        <PasskeyItem
                            key={passkey.id}
                            passkey={passkey}
                            onDelete={handleDelete}
                        />
                    ))}
                </div>
            ) : (
                <EmptyState
                    icon={KeyRound}
                    title={t('settings.passkeys.emptyTitle')}
                    description={t('settings.passkeys.emptyDescription')}
                />
            )}

            <PasskeyRegistration onSuccess={handleRegisterSuccess} />
        </div>
    );
}
