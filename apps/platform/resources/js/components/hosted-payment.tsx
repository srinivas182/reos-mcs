import { FlaskConical } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { GatewayId, StartedCheckout } from '@/lib/billing';
import { money } from '@/lib/billing';

/**
 * Paying at a gateway's own hosted page (PayPal, Authorize.net; B5.2a). REOS
 * needs the ZIP code first to work out the sales tax these gateways charge,
 * then sends the buyer to the gateway (card details never touch REOS). On
 * the Simulator, a clearly marked test "Pay" stands in for the gateway.
 */
export function HostedPayment({
    gateway,
    simulator,
    onStart,
    onPay,
}: {
    gateway: GatewayId;
    simulator: boolean;
    /** Starts the order with the ZIP code; resolves with what the gateway needs. */
    onStart: (postalCode: string) => Promise<StartedCheckout>;
    /** Pays on the Simulator, or goes to the gateway's page. */
    onPay: (started: StartedCheckout) => void;
}) {
    const { t, i18n } = useTranslation();
    const [postalCode, setPostalCode] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [started, setStarted] = useState<StartedCheckout | null>(null);
    const name = t(`gateways.names.${gateway}`);

    const submit = async (event: React.FormEvent) => {
        event.preventDefault();

        if (!/^\d{5}$/.test(postalCode)) {
            setError(t('billing.errors.postalCode'));

            return;
        }

        setBusy(true);
        setError(null);

        try {
            const result = await onStart(postalCode);
            setStarted(result);

            if (!result.simulator) {
                onPay(result);
            } else {
                setBusy(false);
            }
        } catch (e) {
            setError(
                e instanceof Error && !e.message.startsWith('HTTP')
                    ? e.message
                    : t('billing.checkout.startFailed'),
            );
            setBusy(false);
        }
    };

    return (
        <div className="space-y-4">
            {simulator ? (
                <p className="flex gap-2 rounded-reos-md bg-reos-tone-info-bg p-3 text-sm text-reos-tone-info-fg">
                    <FlaskConical aria-hidden className="size-5 shrink-0" />
                    {t('billing.checkout.simulatorFor', { gateway: name })}
                </p>
            ) : null}

            {started === null ? (
                <form
                    onSubmit={(e) => void submit(e)}
                    className="space-y-3"
                    noValidate
                >
                    <div className="space-y-2">
                        <Label htmlFor="postal_code">
                            {t('billing.checkout.postalCode')}
                        </Label>
                        <Input
                            id="postal_code"
                            inputMode="numeric"
                            autoComplete="postal-code"
                            maxLength={5}
                            value={postalCode}
                            onChange={(e) =>
                                setPostalCode(e.target.value.replace(/\D/g, ''))
                            }
                            aria-describedby="postal_code_help"
                            aria-invalid={error !== null}
                            className="max-w-40"
                        />
                        <p
                            id="postal_code_help"
                            className="text-sm text-muted-foreground"
                        >
                            {t('billing.checkout.postalCodeHelp')}
                        </p>
                    </div>
                    <Button type="submit" disabled={busy} className="w-full">
                        {t('billing.checkout.continueTo', { gateway: name })}
                    </Button>
                </form>
            ) : (
                <Button
                    onClick={() => {
                        setBusy(true);
                        onPay(started);
                    }}
                    disabled={busy}
                    className="w-full"
                >
                    {t('billing.checkout.simulatorPay', {
                        amount: money(started.total, i18n.language),
                    })}
                </Button>
            )}
            {error ? (
                <p role="alert" className="text-body text-destructive">
                    {error}
                </p>
            ) : null}
        </div>
    );
}
