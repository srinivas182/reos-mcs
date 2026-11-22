import { router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import type { Contact } from '@/components/messages/types';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { roleName } from '@/lib/roles';
import { store } from '@/routes/messages';
import type { RoleName } from '@/types/auth';

/**
 * New message (B6.1): pick one person or several (a group of up to 20, with
 * an optional name) from the people you work with, and write the first
 * message. Only people REOS lets you message are listed.
 */
export function NewMessageDialog({
    open,
    contacts,
    onOpenChange,
}: {
    open: boolean;
    contacts: Contact[] | undefined;
    onOpenChange: (open: boolean) => void;
}) {
    const { t } = useTranslation();

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>{t('messages.new')}</DialogTitle>
                    <DialogDescription>
                        {t('messages.newDescription')}
                    </DialogDescription>
                </DialogHeader>
                {open ? (
                    <NewMessageForm
                        contacts={contacts}
                        onDone={() => onOpenChange(false)}
                    />
                ) : null}
            </DialogContent>
        </Dialog>
    );
}

function NewMessageForm({
    contacts,
    onDone,
}: {
    contacts: Contact[] | undefined;
    onDone: () => void;
}) {
    const { t } = useTranslation();
    const [query, setQuery] = useState('');
    const [chosen, setChosen] = useState<number[]>([]);
    const [title, setTitle] = useState('');
    const [body, setBody] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);
    const shown = useMemo(
        () =>
            (contacts ?? []).filter((c) =>
                `${c.name} ${c.organisation}`
                    .toLowerCase()
                    .includes(query.trim().toLowerCase()),
            ),
        [contacts, query],
    );

    const submit = () =>
        router.post(
            store().url,
            { people: chosen, title: chosen.length > 1 ? title : null, body },
            {
                onStart: () => setBusy(true),
                onFinish: () => setBusy(false),
                onError: setErrors,
                onSuccess: onDone,
            },
        );

    return (
        <form
            className="space-y-4"
            onSubmit={(e) => {
                e.preventDefault();
                submit();
            }}
        >
            <div className="grid gap-2">
                <Label htmlFor="message-search">{t('messages.search')}</Label>
                <div className="relative">
                    <Search
                        aria-hidden
                        className="absolute top-1/2 left-3 size-5 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        id="message-search"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        className="pl-10"
                    />
                </div>
            </div>
            <fieldset className="grid gap-2">
                <legend className="text-sm font-medium">
                    {t('messages.to')}
                </legend>
                {contacts === undefined ? (
                    <p className="text-body text-muted-foreground">
                        {t('messages.loading')}
                    </p>
                ) : shown.length === 0 ? (
                    <p className="text-body text-muted-foreground">
                        {contacts.length === 0
                            ? t('messages.noContacts')
                            : t('messages.noMatch')}
                    </p>
                ) : (
                    <ul className="max-h-64 divide-y divide-border overflow-y-auto rounded-reos-md border border-border">
                        {shown.map((contact) => {
                            const id = `contact-${contact.id}`;

                            return (
                                <li key={contact.id}>
                                    <label
                                        htmlFor={id}
                                        className="flex min-h-12 items-center gap-3 px-3 py-2 hover:bg-accent"
                                    >
                                        <Checkbox
                                            id={id}
                                            checked={chosen.includes(
                                                contact.id,
                                            )}
                                            onCheckedChange={(on) =>
                                                setChosen(
                                                    on === true
                                                        ? [
                                                              ...chosen,
                                                              contact.id,
                                                          ]
                                                        : chosen.filter(
                                                              (c) =>
                                                                  c !==
                                                                  contact.id,
                                                          ),
                                                )
                                            }
                                        />
                                        <span className="min-w-0">
                                            <span className="block font-medium">
                                                {contact.name}
                                            </span>
                                            <span className="block text-sm text-muted-foreground">
                                                {roleName(
                                                    t,
                                                    contact.role as RoleName,
                                                )}{' '}
                                                · {contact.organisation}
                                            </span>
                                        </span>
                                    </label>
                                </li>
                            );
                        })}
                    </ul>
                )}
                <InputError message={errors.people} />
            </fieldset>
            {chosen.length > 1 ? (
                <div className="grid gap-2">
                    <Label htmlFor="message-title">
                        {t('messages.groupName')}
                    </Label>
                    <Input
                        id="message-title"
                        value={title}
                        maxLength={120}
                        onChange={(e) => setTitle(e.target.value)}
                    />
                </div>
            ) : null}
            <div className="grid gap-2">
                <Label htmlFor="message-first">{t('messages.write')}</Label>
                <Textarea
                    id="message-first"
                    rows={3}
                    maxLength={4000}
                    value={body}
                    onChange={(e) => setBody(e.target.value)}
                />
                <InputError message={errors.body} />
            </div>
            <DialogFooter>
                <Button type="button" variant="outline" onClick={onDone}>
                    {t('messages.cancel')}
                </Button>
                <Button
                    type="submit"
                    disabled={busy || chosen.length === 0 || body.trim() === ''}
                >
                    {t('messages.send')}
                </Button>
            </DialogFooter>
        </form>
    );
}
