import { Link, router, useForm } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { nativeSelect } from '@/lib/tenants';
import { index as connections } from '@/routes/connections';
import { defaultCrm, store } from '@/routes/contacts';
import type { CrmOption } from '@/types';

/**
 * Add contact (screen 13, B8.3b): saved in REOS and in the default CRM
 * (Connections and Sync, screen 24), which the agent can change here. A
 * person already known by email or phone is joined, not duplicated.
 */
export function AddContactDialog({ crms }: { crms: CrmOption[] }) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const form = useForm({
        first_name: '',
        last_name: '',
        email: '',
        phone: '',
        stage: '',
    });
    const takers = crms.filter((crm) => crm.takes_new_people);
    const current = takers.find((crm) => crm.is_default);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <UserPlus aria-hidden />
                    {t('crm.add.action')}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('crm.add.title')}</DialogTitle>
                    <DialogDescription>
                        {t('crm.add.description')}
                    </DialogDescription>
                </DialogHeader>
                <form
                    id="add-contact"
                    className="grid gap-4 sm:grid-cols-2"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post(store().url, {
                            preserveScroll: true,
                            onSuccess: () => {
                                form.reset();
                                setOpen(false);
                            },
                        });
                    }}
                >
                    {(
                        ['first_name', 'last_name', 'email', 'phone'] as const
                    ).map((field) => (
                        <div key={field} className="space-y-2">
                            <Label htmlFor={`add-${field}`}>
                                {t(
                                    `crm.add.${field === 'first_name' ? 'firstName' : field === 'last_name' ? 'lastName' : field}`,
                                )}
                            </Label>
                            <Input
                                id={`add-${field}`}
                                type={
                                    field === 'email'
                                        ? 'email'
                                        : field === 'phone'
                                          ? 'tel'
                                          : 'text'
                                }
                                autoComplete="off"
                                value={form.data[field]}
                                onChange={(e) =>
                                    form.setData(field, e.target.value)
                                }
                            />
                            <InputError message={form.errors[field]} />
                        </div>
                    ))}
                    <div className="space-y-2 sm:col-span-2">
                        <Label htmlFor="add-stage">{t('crm.add.stage')}</Label>
                        <Input
                            id="add-stage"
                            value={form.data.stage}
                            maxLength={60}
                            onChange={(e) =>
                                form.setData('stage', e.target.value)
                            }
                        />
                    </div>
                    <div className="space-y-2 sm:col-span-2">
                        <Label htmlFor="add-goes-to">
                            {t('crm.add.goesTo')}
                        </Label>
                        {takers.length === 0 ? (
                            <p className="text-body text-muted-foreground">
                                {t('crm.add.noCrm')}{' '}
                                <Link
                                    href={connections()}
                                    className="text-primary underline underline-offset-4"
                                >
                                    {t('crm.hub.empty.action')}
                                </Link>
                            </p>
                        ) : (
                            <select
                                id="add-goes-to"
                                className={nativeSelect}
                                value={current?.connection ?? ''}
                                onChange={(e) =>
                                    router.put(
                                        defaultCrm().url,
                                        {
                                            connection:
                                                e.target.value === ''
                                                    ? null
                                                    : Number(e.target.value),
                                        },
                                        {
                                            preserveScroll: true,
                                            preserveState: true,
                                        },
                                    )
                                }
                            >
                                <option value="">
                                    {t('crm.add.stayInReos')}
                                </option>
                                {takers.map((crm) => (
                                    <option
                                        key={crm.connection}
                                        value={crm.connection}
                                    >
                                        {crm.account
                                            ? `${crm.name} (${crm.account})`
                                            : crm.name}
                                    </option>
                                ))}
                            </select>
                        )}
                        <p className="text-sm text-muted-foreground">
                            {t('crm.add.rule')}
                        </p>
                    </div>
                </form>
                <DialogFooter>
                    <Button
                        type="submit"
                        form="add-contact"
                        disabled={form.processing}
                    >
                        {t('crm.add.save')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
