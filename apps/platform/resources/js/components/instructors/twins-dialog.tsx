import { router } from '@inertiajs/react';
import { useId, useState } from 'react';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { twins as twinsRoute } from '@/routes/school/instructors';
import type { InstructorRow } from './types';

/**
 * Avatar ids on screen 52 (A7.3): the instructor's digital twin, made in
 * HeyGen's or Synthesia's studio from the consented sample. AI lessons use
 * it only while the consent is in force.
 */
export function TwinsDialog({
    instructor,
    onClose,
}: {
    instructor: InstructorRow | null;
    onClose: () => void;
}) {
    const { t } = useTranslation();

    return (
        <Dialog
            open={instructor !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {instructor
                            ? t('instructors.twins.title', {
                                  name: instructor.name,
                              })
                            : ''}
                    </DialogTitle>
                    <DialogDescription>
                        {t('instructors.twins.intro')}
                    </DialogDescription>
                </DialogHeader>
                {instructor ? (
                    <TwinsForm
                        key={instructor.id}
                        instructor={instructor}
                        onDone={onClose}
                    />
                ) : null}
            </DialogContent>
        </Dialog>
    );
}

function TwinsForm({
    instructor,
    onDone,
}: {
    instructor: InstructorRow;
    onDone: () => void;
}) {
    const { t } = useTranslation();
    const id = useId();
    const [heygen, setHeygen] = useState(instructor.avatar.twins.heygen ?? '');
    const [synthesia, setSynthesia] = useState(
        instructor.avatar.twins.synthesia ?? '',
    );
    const [processing, setProcessing] = useState(false);

    return (
        <form
            className="space-y-4"
            onSubmit={(e) => {
                e.preventDefault();
                router.put(
                    twinsRoute(instructor.id).url,
                    { heygen: heygen || null, synthesia: synthesia || null },
                    {
                        preserveScroll: true,
                        onStart: () => setProcessing(true),
                        onFinish: () => setProcessing(false),
                        onSuccess: onDone,
                    },
                );
            }}
        >
            <div className="grid gap-2">
                <Label htmlFor={`${id}-heygen`}>
                    {t('instructors.twins.heygen')}
                </Label>
                <Input
                    id={`${id}-heygen`}
                    value={heygen}
                    maxLength={120}
                    autoComplete="off"
                    onChange={(e) => setHeygen(e.target.value.trim())}
                />
            </div>
            <div className="grid gap-2">
                <Label htmlFor={`${id}-synthesia`}>
                    {t('instructors.twins.synthesia')}
                </Label>
                <Input
                    id={`${id}-synthesia`}
                    value={synthesia}
                    maxLength={120}
                    autoComplete="off"
                    onChange={(e) => setSynthesia(e.target.value.trim())}
                />
            </div>
            <DialogFooter>
                <Button type="button" variant="outline" onClick={onDone}>
                    {t('instructors.cancel')}
                </Button>
                <Button type="submit" disabled={processing}>
                    {t('instructors.twins.save')}
                </Button>
            </DialogFooter>
        </form>
    );
}
