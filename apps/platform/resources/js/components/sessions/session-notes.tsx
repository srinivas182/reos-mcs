import { router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import type { Session } from '@/components/sessions/types';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { notes as notesRoute } from '@/routes/sessions';

/**
 * Session notes (instructor screen 16): one set of notes kept with the
 * session, which everyone in it can read and edit (the whiteboard joins
 * them with B6.3b).
 */
export function SessionNotes({ session }: { session: Session }) {
    const { t, i18n } = useTranslation();
    const [text, setText] = useState(session.notes ?? '');
    const [error, setError] = useState<string | undefined>();
    const [saving, setSaving] = useState(false);

    if (session.status === 'cancelled') {
        return null;
    }

    return (
        <section className="space-y-4">
            <Heading
                variant="small"
                title={t('sessions.notes.title')}
                description={t('sessions.notes.description')}
            />
            <form
                className="space-y-3"
                onSubmit={(e) => {
                    e.preventDefault();
                    router.put(
                        notesRoute(session.id).url,
                        { notes: text },
                        {
                            preserveScroll: true,
                            onStart: () => setSaving(true),
                            onFinish: () => setSaving(false),
                            onError: (errors) => setError(errors.notes),
                            onSuccess: () => setError(undefined),
                        },
                    );
                }}
            >
                <Label htmlFor="session-notes" className="sr-only">
                    {t('sessions.notes.title')}
                </Label>
                <Textarea
                    id="session-notes"
                    rows={8}
                    value={text}
                    placeholder={t('sessions.notes.placeholder')}
                    onChange={(e) => setText(e.target.value)}
                />
                <InputError message={error} />
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <p className="text-sm text-muted-foreground">
                        {session.notes_updated_at && session.notes_updated_by
                            ? t('sessions.notes.updated', {
                                  name: session.notes_updated_by,
                                  when: new Date(
                                      session.notes_updated_at,
                                  ).toLocaleString(i18n.language, {
                                      dateStyle: 'medium',
                                      timeStyle: 'short',
                                  }),
                              })
                            : t('sessions.notes.shared')}
                    </p>
                    <Button type="submit" disabled={saving}>
                        {t('sessions.notes.save')}
                    </Button>
                </div>
            </form>
        </section>
    );
}
