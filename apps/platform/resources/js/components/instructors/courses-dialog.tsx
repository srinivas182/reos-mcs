import { router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
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
import { courses as coursesRoute } from '@/routes/school/instructors';
import type { InstructorRow } from './types';

/** Edit on screen 52: the courses an instructor teaches (and so may edit, A7.2). */
export function CoursesDialog({
    instructor,
    courses,
    onClose,
}: {
    instructor: InstructorRow | null;
    courses: { id: number; title: string }[];
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
                            ? t('instructors.coursesTitle', {
                                  name: instructor.name,
                              })
                            : ''}
                    </DialogTitle>
                    <DialogDescription>
                        {t('instructors.coursesIntro')}
                    </DialogDescription>
                </DialogHeader>
                {instructor ? (
                    <CoursesForm
                        key={instructor.id}
                        instructor={instructor}
                        courses={courses}
                        onDone={onClose}
                    />
                ) : null}
            </DialogContent>
        </Dialog>
    );
}

function CoursesForm({
    instructor,
    courses,
    onDone,
}: {
    instructor: InstructorRow;
    courses: { id: number; title: string }[];
    onDone: () => void;
}) {
    const { t } = useTranslation();
    const [picked, setPicked] = useState<number[]>(
        instructor.courses.map((c) => c.id),
    );
    const [processing, setProcessing] = useState(false);

    return (
        <form
            className="space-y-4"
            onSubmit={(e) => {
                e.preventDefault();
                router.put(
                    coursesRoute(instructor.id).url,
                    { courses: picked },
                    {
                        preserveScroll: true,
                        onStart: () => setProcessing(true),
                        onFinish: () => setProcessing(false),
                        onSuccess: onDone,
                    },
                );
            }}
        >
            <fieldset className="space-y-1">
                <legend className="sr-only">
                    {t('instructors.columns.courses')}
                </legend>
                {courses.map((course) => (
                    <label
                        key={course.id}
                        className="flex min-h-12 items-center gap-3 text-base"
                    >
                        <Checkbox
                            checked={picked.includes(course.id)}
                            onCheckedChange={(on) =>
                                setPicked(
                                    on
                                        ? [...picked, course.id]
                                        : picked.filter(
                                              (id) => id !== course.id,
                                          ),
                                )
                            }
                        />
                        {course.title}
                    </label>
                ))}
            </fieldset>
            <DialogFooter>
                <Button type="button" variant="outline" onClick={onDone}>
                    {t('instructors.cancel')}
                </Button>
                <Button type="submit" disabled={processing}>
                    {t('instructors.save')}
                </Button>
            </DialogFooter>
        </form>
    );
}
