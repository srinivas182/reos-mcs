import { Head, Link } from '@inertiajs/react';
import { CalendarCheck, Handshake, Inbox, Phone } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { BriefPlayer } from '@/components/agents/brief-player';
import { BriefSettingsForm } from '@/components/agents/brief-settings-form';
import type {
    Brief,
    BriefCommand,
    BriefSection,
    BriefSettings,
} from '@/components/agents/types';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { putJson } from '@/lib/content';
import { dashboard } from '@/routes';
import { settings as settingsRoute } from '@/routes/agent/brief';
import { index as deals } from '@/routes/deals';

function commandHref(command: BriefCommand): string {
    if (command.kind === 'call' && command.phone)
        return `tel:${command.phone.replace(/[^\d+]/g, '')}`;
    if (command.kind === 'deal' && command.deal)
        return deals({ query: { deal: command.deal } }).url;
    return dashboard().url;
}

const ICONS = { call: Phone, deal: Handshake, today: CalendarCheck };

/**
 * The morning brief on the web (A11.1c; agent screen 184, in the web
 * shell): the same words as the phone reads aloud, read aloud here too,
 * the commands offered at the end, and when it goes out.
 */
export default function BriefPage({
    brief,
    settings,
    sections,
}: {
    brief: Brief;
    settings: BriefSettings;
    sections: BriefSection[];
}) {
    const { t, i18n } = useTranslation();
    const [saved, setSaved] = useState(settings);
    const [error, setError] = useState<string | null>(null);
    const save = async (value: BriefSettings) => {
        try {
            setSaved(
                (
                    await putJson<{ data: BriefSettings }>(
                        settingsRoute().url,
                        value,
                    )
                ).data,
            );
            setError(null);
            toast.success(t('agents.brief.settings.saved'));
        } catch (e) {
            setError(e instanceof Error ? e.message : String(e));
        }
    };

    return (
        <>
            <Head title={t('agents.brief.title')} />
            <div className="space-y-6 px-4 py-6 md:px-8">
                <PageHeader
                    title={t('agents.brief.title')}
                    description={t('agents.brief.description')}
                />
                {brief.sections.length === 0 ? (
                    <EmptyState
                        icon={Inbox}
                        title={t('agents.brief.empty.title')}
                        description={t('agents.brief.empty.body')}
                    />
                ) : null}
                <BriefPlayer script={brief.script} lang={i18n.language} />
                <section aria-labelledby="brief-commands" className="space-y-3">
                    <h2 id="brief-commands" className="text-lg font-semibold">
                        {t('agents.brief.commands.title')}
                    </h2>
                    <div className="flex flex-wrap gap-3">
                        {brief.commands.map((command) => {
                            const Icon = ICONS[command.kind];
                            const href = commandHref(command);
                            return (
                                <Button
                                    key={command.label}
                                    asChild
                                    variant={
                                        command.kind === 'call'
                                            ? 'default'
                                            : 'outline'
                                    }
                                >
                                    {command.kind === 'call' ? (
                                        <a href={href}>
                                            <Icon aria-hidden />
                                            {command.label}
                                        </a>
                                    ) : (
                                        <Link href={href}>
                                            <Icon aria-hidden />
                                            {command.label}
                                        </Link>
                                    )}
                                </Button>
                            );
                        })}
                    </div>
                </section>
                {error ? (
                    <p role="alert" className="text-base text-destructive">
                        {error}
                    </p>
                ) : null}
                <BriefSettingsForm
                    key={JSON.stringify(saved)}
                    settings={saved}
                    sections={sections}
                    onSave={(value) => void save(value)}
                />
            </div>
        </>
    );
}
