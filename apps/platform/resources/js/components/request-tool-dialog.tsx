import { Form } from '@inertiajs/react';
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
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { request } from '@/routes/connections';

/**
 * "Request a tool" (screen 19): the tool's name and what it's used for.
 * Every request counts when REOS chooses what to connect next.
 */
export function RequestToolDialog({
    open,
    onOpenChange,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { t } = useTranslation();

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {t('connections.web.requestTool')}
                    </DialogTitle>
                    <DialogDescription>
                        {t('connections.request.intro')}
                    </DialogDescription>
                </DialogHeader>
                <Form
                    {...request.form()}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    onSuccess={() => onOpenChange(false)}
                    className="space-y-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="space-y-2">
                                <Label htmlFor="tool-name">
                                    {t('connections.request.name')}
                                </Label>
                                <Input
                                    id="tool-name"
                                    name="name"
                                    required
                                    maxLength={120}
                                    autoComplete="off"
                                    aria-invalid={Boolean(errors.name)}
                                />
                                <InputError message={errors.name} />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="tool-note">
                                    {t('connections.request.note')}
                                </Label>
                                <Textarea
                                    id="tool-note"
                                    name="note"
                                    maxLength={500}
                                    rows={3}
                                />
                                <InputError message={errors.note} />
                            </div>
                            <DialogFooter>
                                <Button type="submit" disabled={processing}>
                                    {t('connections.request.send')}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
