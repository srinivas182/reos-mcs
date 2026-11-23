import { MessageSquareText, Phone } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import QRCode from 'react-qr-code';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

type Props = {
    channel: 'call' | 'text';
    name: string;
    phone: string;
    /** The AI's suggested text, editable before it's scanned. */
    draft?: string | null;
    /** "I called" or "I sent it": logs the touch to the CRMs. */
    onLogged: (body: string | null) => void;
};

/** `tel:` or `sms:` with the message; the phone opens it in its own app. */
export function reachUri(
    channel: 'call' | 'text',
    phone: string,
    body = '',
): string {
    if (channel === 'call') {
        return `tel:${phone}`;
    }

    return body.trim() === ''
        ? `sms:${phone}`
        : `sms:${phone}?&body=${encodeURIComponent(body)}`;
}

/**
 * Call or text from the agent's own phone (screen 13, B8.3): REOS never
 * calls or texts. On the web the agent scans a QR code with their phone,
 * or opens the link on a device that can call; then logs it, which writes
 * a note to every CRM that knows the person.
 */
export function ReachDialog({ channel, name, phone, draft, onLogged }: Props) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [body, setBody] = useState(draft ?? '');
    const uri = reachUri(channel, phone, body);
    const Icon = channel === 'call' ? Phone : MessageSquareText;

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                if (next) {
                    setBody(draft ?? '');
                }
            }}
        >
            <DialogTrigger asChild>
                <Button variant="outline">
                    <Icon aria-hidden />
                    {channel === 'call' ? t('crm.hub.call') : t('crm.hub.text')}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {channel === 'call'
                            ? t('crm.hub.callTitle', { name })
                            : t('crm.hub.textTitle', { name })}
                    </DialogTitle>
                    <DialogDescription>
                        {channel === 'call'
                            ? t('crm.hub.scanCall')
                            : t('crm.hub.scanText')}
                    </DialogDescription>
                </DialogHeader>
                {channel === 'text' ? (
                    <div className="space-y-2">
                        <Label htmlFor="reach-body">
                            {t('crm.hub.messageLabel')}
                        </Label>
                        <Textarea
                            id="reach-body"
                            value={body}
                            maxLength={1000}
                            onChange={(e) => setBody(e.target.value)}
                        />
                    </div>
                ) : null}
                <div className="flex flex-col items-center gap-3">
                    <div className="rounded-reos-md bg-white p-3">
                        <QRCode
                            value={uri}
                            size={176}
                            title={phone}
                            aria-label={phone}
                        />
                    </div>
                    <p className="font-medium tabular-nums">{phone}</p>
                    <a
                        href={uri}
                        className="text-body text-primary underline underline-offset-4"
                    >
                        {t('crm.hub.openHere')}
                    </a>
                </div>
                <DialogFooter>
                    <Button
                        onClick={() => {
                            onLogged(channel === 'text' ? body : null);
                            setOpen(false);
                        }}
                    >
                        {channel === 'call'
                            ? t('crm.hub.logCall')
                            : t('crm.hub.logText')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
