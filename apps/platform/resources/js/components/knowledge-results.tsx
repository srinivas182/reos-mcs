import { Link } from '@inertiajs/react';
import { FileText, Presentation, SearchX, Video } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { EmptyState } from '@/components/empty-state';
import type { SearchHit } from '@/lib/content';
import { marks, where } from '@/lib/content';
import { show as showLesson } from '@/routes/content/lessons';

const icons = { text: FileText, slide: Presentation, transcript: Video };

/**
 * Knowledge base results (A5.3): each passage with its course and lesson,
 * where it opens (heading, page or moment in the video) and the matching
 * words highlighted. Used by the content library's search; learners' search
 * on Learn reuses it in A5.3b.
 */
export function KnowledgeResults({
    query,
    results,
}: {
    query: string;
    results: SearchHit[];
}) {
    const { t } = useTranslation();

    if (results.length === 0) {
        return (
            <EmptyState
                icon={SearchX}
                title={t('content.search.noResults', { query })}
                description={t('content.search.noResultsHelp')}
            />
        );
    }

    return (
        <ol
            className="divide-y divide-border"
            aria-label={t('content.search.results', { query })}
        >
            {results.map((hit) => {
                const Icon = icons[hit.kind];

                return (
                    <li key={hit.passage_id} className="flex gap-3 py-3">
                        <Icon
                            aria-hidden
                            className="mt-1 size-5 shrink-0 text-muted-foreground"
                        />
                        <div className="min-w-0 space-y-1">
                            <p className="text-sm text-muted-foreground">
                                {hit.course_title} ·{' '}
                                <Link
                                    href={showLesson(hit.lesson_id)}
                                    className="font-medium text-primary underline-offset-4 hover:underline"
                                >
                                    {hit.lesson_title}
                                </Link>{' '}
                                · {where(t, hit)}
                                {hit.language !== 'en' ? (
                                    <>
                                        {' · '}
                                        {t(`content.languages.${hit.language}`)}
                                    </>
                                ) : null}
                            </p>
                            <p className="text-body text-foreground">
                                {marks(hit.snippet).map((part, index) =>
                                    part.match ? (
                                        <mark
                                            key={index}
                                            className="rounded-sm bg-reos-tone-warning-bg px-0.5 text-reos-tone-warning-fg"
                                        >
                                            {part.text}
                                        </mark>
                                    ) : (
                                        <span key={index}>{part.text}</span>
                                    ),
                                )}
                            </p>
                            {hit.match !== 'keyword' ? (
                                <p className="text-sm text-muted-foreground">
                                    {t(`content.search.match.${hit.match}`)}
                                </p>
                            ) : null}
                        </div>
                    </li>
                );
            })}
        </ol>
    );
}
