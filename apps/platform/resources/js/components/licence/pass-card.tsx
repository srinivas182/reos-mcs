import { Heart } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import type { PassRow } from '@/components/licence/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/** "Jordan from Katy passed the Texas exam!" with their tip and Send congrats (f06). */
export function PassCard({
    pass,
    onCongrats,
}: {
    pass: PassRow;
    onCongrats: (message: string) => void;
}) {
    const { t } = useTranslation();
    const [writing, setWriting] = useState(false);
    const [message, setMessage] = useState(
        t('licence.news.passes.defaultMessage', { name: pass.first_name }),
    );
    const name = pass.mine ? t('licence.news.passes.you') : pass.first_name;

    return (
        <article className="space-y-2 rounded-reos-lg border border-border bg-card p-4">
            <h3 className="text-base font-semibold">
                {pass.city
                    ? t('licence.news.passes.lineCity', {
                          name,
                          city: pass.city,
                          state: pass.state,
                      })
                    : t('licence.news.passes.line', {
                          name,
                          state: pass.state,
                      })}
            </h3>
            {pass.tip ? <p className="text-base">“{pass.tip}”</p> : null}
            <p className="flex items-center gap-1 text-sm text-muted-foreground">
                <Heart aria-hidden className="size-4" />
                {t('licence.news.passes.congrats', { count: pass.congrats })}
            </p>
            {pass.mine ? null : pass.sent ? (
                <p className="text-sm font-medium text-reos-tone-success-fg">
                    {t('licence.news.passes.sent')}
                </p>
            ) : writing ? (
                <form
                    className="flex flex-wrap items-end gap-2"
                    onSubmit={(e) => {
                        e.preventDefault();
                        onCongrats(message.trim());
                        setWriting(false);
                    }}
                >
                    <div className="grid min-w-60 flex-1 gap-2">
                        <Label htmlFor={`congrats-${pass.id}`}>
                            {t('licence.news.passes.messageLabel', {
                                name: pass.first_name,
                            })}
                        </Label>
                        <Input
                            id={`congrats-${pass.id}`}
                            value={message}
                            maxLength={140}
                            onChange={(e) => setMessage(e.target.value)}
                        />
                    </div>
                    <Button type="submit" disabled={message.trim().length < 2}>
                        {t('licence.news.passes.send')}
                    </Button>
                </form>
            ) : (
                <Button variant="outline" onClick={() => setWriting(true)}>
                    {t('licence.news.passes.send')}
                </Button>
            )}
        </article>
    );
}
