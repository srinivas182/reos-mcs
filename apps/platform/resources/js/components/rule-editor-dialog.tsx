import { router } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
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
import type { Course, RuleRow, RuleValue } from '@/lib/state-rules';
import { discard, draft } from '@/routes/states/rules';

const ROLES = [
    'sponsoring_broker',
    'designated_broker',
    'delegated_supervisor',
] as const;

type Props = {
    state: string;
    rule: RuleRow;
    /** CE mandatory courses, for picking the first renewal's courses. */
    courses: Course[];
    onClose: () => void;
};

/**
 * Edit one rule (screen 62): its value in a form that fits its type, the
 * source, the date it takes effect and a note. Saved as a draft until
 * "Publish changes"; a draft can be thrown away.
 */
export function RuleEditorDialog({ state, rule, courses, onClose }: Props) {
    const { t } = useTranslation();
    const base = rule.draft ?? rule.current;
    const [value, setValue] = useState<RuleValue>(base?.value ?? {});
    const [citation, setCitation] = useState(base?.citation ?? '');
    const [sourceUrl, setSourceUrl] = useState(base?.source_url ?? '');
    const [effective, setEffective] = useState(
        rule.draft?.effective_from ?? new Date().toISOString().slice(0, 10),
    );
    const [note, setNote] = useState(rule.draft?.note ?? '');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const set = (patch: Partial<RuleValue>) =>
        setValue((current) => ({ ...current, ...patch }));
    const list = (value.courses ?? []) as Course[];
    const firstError = (prefix: string) =>
        Object.entries(errors).find(([key]) => key.startsWith(prefix))?.[1];

    const save = () => {
        setProcessing(true);
        router.put(
            draft({ state, key: rule.key }).url,
            {
                value,
                citation,
                source_url: sourceUrl || null,
                effective_from: effective,
                note: note || null,
            },
            {
                preserveScroll: true,
                onError: (e) => setErrors(e),
                onSuccess: onClose,
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <Dialog open onOpenChange={(open) => (open ? null : onClose())}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>
                        {t(
                            `stateRules.keys.${rule.key}` as 'stateRules.keys.ce.hours',
                        )}
                    </DialogTitle>
                    <DialogDescription>
                        {t('stateRules.edit.intro')}
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-4">
                    {rule.type === 'number' ? (
                        <div className="grid gap-2">
                            <Label htmlFor="rule-number">
                                {t('stateRules.edit.value', {
                                    unit: rule.unit
                                        ? t(
                                              `stateRules.unitNames.${rule.unit as 'hours'}`,
                                          )
                                        : '',
                                })}
                            </Label>
                            <Input
                                id="rule-number"
                                type="number"
                                min={0}
                                value={value.number ?? ''}
                                onChange={(e) =>
                                    set({ number: Number(e.target.value) })
                                }
                            />
                        </div>
                    ) : null}

                    {rule.type === 'courses' ? (
                        <fieldset className="space-y-3">
                            <legend className="text-sm font-medium">
                                {t('stateRules.edit.courses')}
                            </legend>
                            {list.map((course, i) => (
                                <div
                                    key={i}
                                    className="grid gap-2 sm:grid-cols-[1fr_6rem_auto]"
                                >
                                    <Input
                                        aria-label={t(
                                            'stateRules.edit.courseTitle',
                                            { n: i + 1 },
                                        )}
                                        value={course.title}
                                        onChange={(e) =>
                                            set({
                                                courses: list.map((c, j) =>
                                                    j === i
                                                        ? {
                                                              ...c,
                                                              title: e.target
                                                                  .value,
                                                              code:
                                                                  c.code ||
                                                                  e.target.value
                                                                      .toLowerCase()
                                                                      .replace(
                                                                          /[^a-z0-9]+/g,
                                                                          '_',
                                                                      )
                                                                      .replace(
                                                                          /^_|_$/g,
                                                                          '',
                                                                      ),
                                                          }
                                                        : c,
                                                ),
                                            })
                                        }
                                    />
                                    <Input
                                        aria-label={t(
                                            'stateRules.edit.courseHours',
                                            { n: i + 1 },
                                        )}
                                        type="number"
                                        min={0}
                                        value={course.hours}
                                        onChange={(e) =>
                                            set({
                                                courses: list.map((c, j) =>
                                                    j === i
                                                        ? {
                                                              ...c,
                                                              hours: Number(
                                                                  e.target
                                                                      .value,
                                                              ),
                                                          }
                                                        : c,
                                                ),
                                            })
                                        }
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="icon"
                                        aria-label={t(
                                            'stateRules.edit.removeCourse',
                                            { title: course.title },
                                        )}
                                        onClick={() =>
                                            set({
                                                courses: list.filter(
                                                    (_, j) => j !== i,
                                                ),
                                            })
                                        }
                                    >
                                        <Trash2 aria-hidden />
                                    </Button>
                                </div>
                            ))}
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    set({
                                        courses: [
                                            ...list,
                                            { code: '', title: '', hours: 0 },
                                        ],
                                    })
                                }
                            >
                                <Plus aria-hidden />
                                {t('stateRules.edit.addCourse')}
                            </Button>
                            <InputError message={firstError('value.courses')} />
                        </fieldset>
                    ) : null}

                    {rule.type === 'role_course' ? (
                        <>
                            <div className="grid gap-4 sm:grid-cols-[1fr_6rem]">
                                <div className="grid gap-2">
                                    <Label htmlFor="rule-title">
                                        {t('stateRules.edit.title')}
                                    </Label>
                                    <Input
                                        id="rule-title"
                                        value={value.title ?? ''}
                                        onChange={(e) =>
                                            set({ title: e.target.value })
                                        }
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="rule-hours">
                                        {t('stateRules.unitNames.hours')}
                                    </Label>
                                    <Input
                                        id="rule-hours"
                                        type="number"
                                        min={0}
                                        value={value.hours ?? ''}
                                        onChange={(e) =>
                                            set({
                                                hours: Number(e.target.value),
                                            })
                                        }
                                    />
                                </div>
                            </div>
                            <fieldset className="space-y-1">
                                <legend className="text-sm font-medium">
                                    {t('stateRules.edit.roles')}
                                </legend>
                                {ROLES.map((role) => (
                                    <label
                                        key={role}
                                        className="flex min-h-12 items-center gap-3"
                                    >
                                        <Checkbox
                                            checked={(
                                                value.roles ?? []
                                            ).includes(role)}
                                            onCheckedChange={(on) =>
                                                set({
                                                    roles: on
                                                        ? [
                                                              ...(value.roles ??
                                                                  []),
                                                              role,
                                                          ]
                                                        : (
                                                              value.roles ?? []
                                                          ).filter(
                                                              (r) => r !== role,
                                                          ),
                                                })
                                            }
                                        />
                                        {t(`licences.roles.${role}`)}
                                    </label>
                                ))}
                            </fieldset>
                        </>
                    ) : null}

                    {rule.type === 'first_renewal' ? (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="rule-qualifying">
                                    {t('stateRules.edit.qualifyingHours')}
                                </Label>
                                <Input
                                    id="rule-qualifying"
                                    type="number"
                                    min={0}
                                    value={value.qualifying_hours ?? ''}
                                    onChange={(e) =>
                                        set({
                                            qualifying_hours: Number(
                                                e.target.value,
                                            ),
                                        })
                                    }
                                />
                            </div>
                            <fieldset className="space-y-1">
                                <legend className="text-sm font-medium">
                                    {t('stateRules.edit.plusCourses')}
                                </legend>
                                {courses.map((course) => {
                                    const codes = (value.courses ??
                                        []) as string[];

                                    return (
                                        <label
                                            key={course.code}
                                            className="flex min-h-12 items-center gap-3"
                                        >
                                            <Checkbox
                                                checked={codes.includes(
                                                    course.code,
                                                )}
                                                onCheckedChange={(on) =>
                                                    set({
                                                        courses: on
                                                            ? [
                                                                  ...codes,
                                                                  course.code,
                                                              ]
                                                            : codes.filter(
                                                                  (c) =>
                                                                      c !==
                                                                      course.code,
                                                              ),
                                                    })
                                                }
                                            />
                                            {course.title}
                                        </label>
                                    );
                                })}
                            </fieldset>
                        </>
                    ) : null}

                    {rule.type === 'fee_days' ? (
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="rule-fee">
                                    {t('stateRules.edit.fee')}
                                </Label>
                                <Input
                                    id="rule-fee"
                                    type="number"
                                    min={0}
                                    value={value.fee_usd ?? ''}
                                    onChange={(e) =>
                                        set({ fee_usd: Number(e.target.value) })
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="rule-days">
                                    {t('stateRules.edit.days')}
                                </Label>
                                <Input
                                    id="rule-days"
                                    type="number"
                                    min={0}
                                    value={value.days ?? ''}
                                    onChange={(e) =>
                                        set({ days: Number(e.target.value) })
                                    }
                                />
                            </div>
                        </div>
                    ) : null}

                    {rule.type === 'text' ? (
                        <div className="grid gap-2">
                            <Label htmlFor="rule-text">
                                {t('stateRules.edit.text')}
                            </Label>
                            <Textarea
                                id="rule-text"
                                value={value.text ?? ''}
                                onChange={(e) => set({ text: e.target.value })}
                                maxLength={2000}
                            />
                        </div>
                    ) : null}
                    <InputError
                        message={
                            firstError('value.') && rule.type !== 'courses'
                                ? firstError('value.')
                                : undefined
                        }
                    />

                    <div className="grid gap-2">
                        <Label htmlFor="rule-citation">
                            {t('stateRules.edit.citation')}
                        </Label>
                        <Input
                            id="rule-citation"
                            value={citation}
                            onChange={(e) => setCitation(e.target.value)}
                            maxLength={200}
                        />
                        <InputError message={errors.citation} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="rule-url">
                            {t('stateRules.edit.sourceUrl')}
                        </Label>
                        <Input
                            id="rule-url"
                            type="url"
                            value={sourceUrl}
                            onChange={(e) => setSourceUrl(e.target.value)}
                        />
                        <InputError message={errors.source_url} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="rule-effective">
                            {t('stateRules.edit.effective')}
                        </Label>
                        <Input
                            id="rule-effective"
                            type="date"
                            value={effective}
                            onChange={(e) => setEffective(e.target.value)}
                        />
                        <p className="text-sm text-muted-foreground">
                            {t('stateRules.edit.effectiveHint')}
                        </p>
                        <InputError message={errors.effective_from} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="rule-note">
                            {t('stateRules.edit.note')}
                        </Label>
                        <Textarea
                            id="rule-note"
                            value={note}
                            onChange={(e) => setNote(e.target.value)}
                            maxLength={500}
                        />
                    </div>
                </div>

                <DialogFooter className="gap-2">
                    {rule.draft ? (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() =>
                                router.delete(
                                    discard({ state, rule: rule.draft!.id })
                                        .url,
                                    {
                                        preserveScroll: true,
                                        onSuccess: onClose,
                                    },
                                )
                            }
                        >
                            {t('stateRules.edit.discard')}
                        </Button>
                    ) : null}
                    <Button type="button" variant="outline" onClick={onClose}>
                        {t('common.cancel')}
                    </Button>
                    <Button type="button" onClick={save} disabled={processing}>
                        {t('stateRules.edit.save')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
