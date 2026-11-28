import { Head } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { FilePanel, FilesBoard } from '@/components/coordinators/files-board';
import type {
    FileDetail,
    FilesBoardView,
} from '@/components/coordinators/types';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { index as messagesIndex } from '@/routes/messages';
import { index, message, show, tick } from '@/routes/provider-files';

/** My files (B17.1, provider screens 14–15): deals agents shared, until closing, and one file. */
export default function ProviderFiles({
    board,
    file,
}: {
    board: FilesBoardView;
    file: FileDetail | null;
}) {
    const { t } = useTranslation();

    return (
        <>
            <Head
                title={file === null ? t('coordinators.title') : file.property}
            />
            <div className="space-y-6 px-4 py-6 md:px-8">
                {file === null ? (
                    <>
                        <PageHeader
                            title={t('coordinators.title')}
                            description={t('coordinators.purpose')}
                        />
                        <FilesBoard board={board} href={(id) => show(id).url} />
                    </>
                ) : (
                    <>
                        <PageHeader
                            title={file.property}
                            description={[file.city, file.agent]
                                .filter(Boolean)
                                .join(' · ')}
                            actions={
                                <Button asChild variant="outline">
                                    <a href={index().url}>
                                        <ArrowLeft aria-hidden />
                                        {t('coordinators.back')}
                                    </a>
                                </Button>
                            }
                        />
                        <FilePanel
                            key={file.id}
                            file={file}
                            routes={{
                                tick: tick(file.id).url,
                                message: message(file.id).url,
                                chat: (c) =>
                                    messagesIndex({ query: { c } }).url,
                            }}
                        />
                    </>
                )}
            </div>
        </>
    );
}
