import { router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { phone as sendCode, remove, update, verify } from '@/routes/texts';

export type TextsStatus = {
    /** The verified number, masked (••• ••• 0100), or null. */
    phone: string | null;
    on: boolean;
    available: boolean;
};

/**
 * Settings › Notifications › Texts (B6.2c): a verified US mobile number,
 * then the switch that records the person's SMS consent (with the words
 * carriers require). Which alerts come by text is chosen per kind below it.
 */
export function NotificationTexts({ texts }: { texts: TextsStatus }) {
    const { t } = useTranslation();
    const [editing, setEditing] = useState(texts.phone === null);
    const [number, setNumber] = useState('');
    const [sentTo, setSentTo] = useState<string | null>(null);
    const [code, setCode] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);

    const options = (onSuccess: () => void) => ({
        preserveScroll: true,
        onStart: () => setBusy(true),
        onFinish: () => setBusy(false),
        onError: setErrors,
        onSuccess: () => {
            setErrors({});
            onSuccess();
        },
    });

    if (!texts.available) {
        return (
            <section className="space-y-4">
                <Heading
                    variant="small"
                    title={t('notifications.texts.title')}
                    description={t('notifications.texts.unavailable')}
                />
            </section>
        );
    }

    return (
        <section className="space-y-4">
            <Heading
                variant="small"
                title={t('notifications.texts.title')}
                description={t('notifications.texts.description')}
            />
            <div className="space-y-4 rounded-reos-lg border border-border bg-card p-5">
                {texts.phone !== null && !editing ? (
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <p className="text-base">
                            {t('notifications.texts.number', {
                                phone: texts.phone,
                            })}
                        </p>
                        <div className="flex flex-wrap gap-2">
                            <Button
                                variant="outline"
                                onClick={() => setEditing(true)}
                            >
                                {t('notifications.texts.change')}
                            </Button>
                            <Button
                                variant="ghost"
                                disabled={busy}
                                onClick={() =>
                                    router.delete(
                                        remove().url,
                                        options(() => undefined),
                                    )
                                }
                            >
                                {t('notifications.texts.remove')}
                            </Button>
                        </div>
                    </div>
                ) : (
                    <form
                        className="space-y-4"
                        onSubmit={(e) => {
                            e.preventDefault();

                            if (sentTo === null) {
                                router.post(
                                    sendCode().url,
                                    { phone: number },
                                    options(() => setSentTo(number)),
                                );
                            } else {
                                router.post(
                                    verify().url,
                                    { phone: sentTo, code },
                                    options(() => {
                                        setEditing(false);
                                        setSentTo(null);
                                        setCode('');
                                    }),
                                );
                            }
                        }}
                    >
                        {texts.phone === null ? (
                            <p className="text-base text-muted-foreground">
                                {t('notifications.texts.noNumber')}
                            </p>
                        ) : null}
                        <div className="grid gap-2">
                            <Label htmlFor="texts-phone">
                                {t('notifications.texts.phone')}
                            </Label>
                            <Input
                                id="texts-phone"
                                type="tel"
                                inputMode="tel"
                                autoComplete="tel"
                                value={number}
                                readOnly={sentTo !== null}
                                aria-describedby="texts-phone-hint"
                                onChange={(e) => setNumber(e.target.value)}
                            />
                            <p
                                id="texts-phone-hint"
                                className="text-sm text-muted-foreground"
                            >
                                {t('notifications.texts.phoneHint')}
                            </p>
                            <InputError message={errors.phone} />
                        </div>
                        {sentTo !== null ? (
                            <div className="grid gap-2">
                                <Label htmlFor="texts-code">
                                    {t('notifications.texts.code')}
                                </Label>
                                <Input
                                    id="texts-code"
                                    inputMode="numeric"
                                    autoComplete="one-time-code"
                                    value={code}
                                    onChange={(e) => setCode(e.target.value)}
                                />
                                <InputError message={errors.code} />
                            </div>
                        ) : null}
                        <Button type="submit" disabled={busy}>
                            {sentTo === null
                                ? t('notifications.texts.sendCode')
                                : t('notifications.texts.verify')}
                        </Button>
                    </form>
                )}

                <div className="flex items-start justify-between gap-4 border-t border-border pt-4">
                    <div>
                        <span id="texts-on-label" className="font-medium">
                            {t('notifications.texts.on')}
                        </span>
                        <p
                            id="texts-on-description"
                            className="text-sm text-muted-foreground"
                        >
                            {t('notifications.texts.onDescription')}
                        </p>
                        <InputError message={errors.on} />
                    </div>
                    <Switch
                        checked={texts.on}
                        disabled={busy || texts.phone === null}
                        aria-labelledby="texts-on-label"
                        aria-describedby="texts-on-description"
                        onCheckedChange={(on) =>
                            router.put(
                                update().url,
                                { on },
                                options(() => undefined),
                            )
                        }
                    />
                </div>
            </div>
        </section>
    );
}
