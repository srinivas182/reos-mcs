import { router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { line as lineRoute } from '@/routes/builder/review';
import type { ReviewLine } from './types';

/** Edit a line in both languages (screen 44): it's checked again and recorded again before publishing. */
export function EditLineDialog({
    lessonId,
    line,
    onClose,
}: {
    lessonId: number;
    line: ReviewLine | null;
    onClose: () => void;
}) {
    const { t } = useTranslation();

    return (
        <Dialog
            open={line !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {line
                            ? t('review.page.editTitle', { n: line.n + 1 })
                            : ''}
                    </DialogTitle>
                    <DialogDescription>
                        {t('review.page.editIntro')}
                    </DialogDescription>
                </DialogHeader>
                {line ? (
                    <EditForm
                        key={line.n}
                        lessonId={lessonId}
                        line={line}
                        onDone={onClose}
                    />
                ) : null}
            </DialogContent>
        </Dialog>
    );
}

function EditForm({
    lessonId,
    line,
    onDone,
}: {
    lessonId: number;
    line: ReviewLine;
    onDone: () => void;
}) {
    const { t } = useTranslation();
    const [en, setEn] = useState(line.en);
    const [es, setEs] = useState(line.es);

    return (
        <form
            className="space-y-4"
            onSubmit={(e) => {
                e.preventDefault();
                router.post(
                    lineRoute([lessonId, line.n]).url,
                    { action: 'edit', en, es },
                    { preserveScroll: true, onSuccess: onDone },
                );
            }}
        >
            <div className="grid gap-2">
                <Label htmlFor="line-en">{t('review.page.english')}</Label>
                <Textarea
                    id="line-en"
                    value={en}
                    maxLength={600}
                    onChange={(e) => setEn(e.target.value)}
                />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="line-es">{t('review.page.spanish')}</Label>
                <Textarea
                    id="line-es"
                    lang="es"
                    value={es}
                    maxLength={600}
                    onChange={(e) => setEs(e.target.value)}
                />
            </div>
            <DialogFooter>
                <Button type="button" variant="outline" onClick={onDone}>
                    {t('review.page.cancel')}
                </Button>
                <Button
                    type="submit"
                    disabled={en.trim().length < 2 || es.trim().length < 2}
                >
                    {t('review.page.save')}
                </Button>
            </DialogFooter>
        </form>
    );
}
