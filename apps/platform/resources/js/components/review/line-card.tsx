import { router } from '@inertiajs/react';
import { AlertTriangle, MessageSquare } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { statusTone } from '@/components/builder/types';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import {
    comment as commentRoute,
    line as lineRoute,
    suggest as suggestRoute,
} from '@/routes/builder/review';
import type { ReviewLine } from './types';

/**
 * One script line on screen 44 (A8.1): English and Spanish side by side, its
 * source, any legal flag with the rule and its legal source, a suggested
 * rewrite, comments, and Approve, Edit, Use suggestion, Re-record, Comment.
 */
export function LineCard({
    lessonId,
    line,
    locked,
    onEdit,
}: {
    lessonId: number;
    line: ReviewLine;
    locked: boolean;
    onEdit: (line: ReviewLine) => void;
}) {
    const { t } = useTranslation();
    const [commenting, setCommenting] = useState(false);
    const [body, setBody] = useState('');
    const [asking, setAsking] = useState(false);
    const number = line.n + 1;
    const flagged = line.flags.length > 0;
    const act = (action: 'approve' | 'use_suggestion' | 'rerecord') =>
        router.post(
            lineRoute([lessonId, line.n]).url,
            { action },
            { preserveScroll: true },
        );
    const suggest = () => {
        setAsking(true);
        router.post(
            suggestRoute([lessonId, line.n]).url,
            {},
            {
                preserveScroll: true,
                onFinish: () => setAsking(false),
            },
        );
    };

    return (
        <article
            className={cn(
                'space-y-3 rounded-reos-lg border bg-card p-4',
                flagged ? 'border-reos-tone-warning-fg' : 'border-border',
            )}
        >
            <div className="flex flex-wrap items-center gap-2">
                <span className="font-semibold">
                    {t('review.page.line', { n: number })}
                </span>
                <span
                    className={cn(
                        'inline-flex rounded-reos-pill px-2.5 py-0.5 text-sm font-semibold',
                        statusTone(
                            line.status === 'changed'
                                ? 'in_review'
                                : line.status,
                        ),
                    )}
                >
                    {t(`review.page.lineStatus.${line.status}`)}
                </span>
                {line.rerecord ? (
                    <span className="text-sm text-muted-foreground">
                        {t('review.page.willRerecord')}
                    </span>
                ) : null}
                <span className="ml-auto text-sm text-muted-foreground">
                    {line.page === null
                        ? line.source
                        : t('review.page.source', {
                              source: line.source,
                              page: line.page,
                          })}
                </span>
            </div>
            <div className="grid gap-3 md:grid-cols-2">
                <p className="text-base">{line.en}</p>
                <p className="text-base" lang="es">
                    {line.es}
                </p>
            </div>
            {line.flags.map((flag) => (
                <p
                    key={`${flag.rule}-${flag.language}`}
                    className="flex items-start gap-2 rounded-reos-md bg-reos-tone-warning-bg p-3 text-base text-reos-tone-warning-fg"
                >
                    <AlertTriangle
                        aria-hidden
                        className="mt-0.5 size-5 shrink-0"
                    />
                    {t('review.page.flag', {
                        rule: t(`content.checks.rules.${flag.rule}`),
                        language: t(
                            `review.page.${flag.language === 'en' ? 'english' : 'spanish'}`,
                        ),
                        source: flag.source ?? '',
                    })}
                </p>
            ))}
            {line.suggestion ? (
                <div className="space-y-2 rounded-reos-md border border-dashed border-border p-3">
                    <p className="text-sm font-semibold text-muted-foreground">
                        {t('review.page.suggestion')}
                    </p>
                    <div className="grid gap-3 md:grid-cols-2">
                        <p className="text-base">{line.suggestion.en}</p>
                        <p className="text-base" lang="es">
                            {line.suggestion.es}
                        </p>
                    </div>
                </div>
            ) : null}
            {line.comments.map((c) => (
                <p
                    key={c.at}
                    className="flex items-start gap-2 text-base text-muted-foreground"
                >
                    <MessageSquare
                        aria-hidden
                        className="mt-0.5 size-4 shrink-0"
                    />
                    <span>
                        <span className="font-semibold text-foreground">
                            {c.name}:{' '}
                        </span>
                        {c.body}
                    </span>
                </p>
            ))}
            {locked ? null : (
                <div className="flex flex-wrap gap-2">
                    {line.status !== 'approved' ? (
                        <Button
                            size="sm"
                            disabled={flagged}
                            aria-label={`${t('review.page.approve')}: ${t('review.page.line', { n: number })}`}
                            onClick={() => act('approve')}
                        >
                            {t('review.page.approve')}
                        </Button>
                    ) : null}
                    {flagged && !line.suggestion ? (
                        <Button
                            size="sm"
                            variant="outline"
                            disabled={asking}
                            aria-label={`${t('review.page.suggest')}: ${t('review.page.line', { n: number })}`}
                            onClick={suggest}
                        >
                            {t('review.page.suggest')}
                        </Button>
                    ) : null}
                    {line.suggestion ? (
                        <Button
                            size="sm"
                            variant="outline"
                            aria-label={`${t('review.page.useSuggestion')}: ${t('review.page.line', { n: number })}`}
                            onClick={() => act('use_suggestion')}
                        >
                            {t('review.page.useSuggestion')}
                        </Button>
                    ) : null}
                    <Button
                        size="sm"
                        variant="outline"
                        aria-label={`${t('review.page.edit')}: ${t('review.page.line', { n: number })}`}
                        onClick={() => onEdit(line)}
                    >
                        {t('review.page.edit')}
                    </Button>
                    {!line.rerecord ? (
                        <Button
                            size="sm"
                            variant="ghost"
                            aria-label={`${t('review.page.rerecord')}: ${t('review.page.line', { n: number })}`}
                            onClick={() => act('rerecord')}
                        >
                            {t('review.page.rerecord')}
                        </Button>
                    ) : null}
                    <Button
                        size="sm"
                        variant="ghost"
                        aria-label={`${t('review.page.comment')}: ${t('review.page.line', { n: number })}`}
                        onClick={() => setCommenting(!commenting)}
                    >
                        {t('review.page.comment')}
                    </Button>
                </div>
            )}
            {commenting ? (
                <form
                    className="space-y-2"
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.post(
                            commentRoute([lessonId, line.n]).url,
                            { body },
                            {
                                preserveScroll: true,
                                onSuccess: () => {
                                    setBody('');
                                    setCommenting(false);
                                },
                            },
                        );
                    }}
                >
                    <Label htmlFor={`comment-${line.n}`}>
                        {t('review.page.commentLabel', { n: number })}
                    </Label>
                    <Textarea
                        id={`comment-${line.n}`}
                        value={body}
                        maxLength={2000}
                        onChange={(e) => setBody(e.target.value)}
                    />
                    <Button
                        type="submit"
                        size="sm"
                        disabled={body.trim() === ''}
                    >
                        {t('review.page.post')}
                    </Button>
                </form>
            ) : null}
        </article>
    );
}
