import { Head } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

/**
 * The terms and the privacy notice (A6.3), public at /terms and /privacy,
 * with the version people agree to when they sign up.
 */
export default function Legal({
    title,
    version,
    intro,
    sections,
}: {
    document: 'terms' | 'privacy';
    title: string;
    version: string;
    intro: string[];
    sections: { heading: string; paragraphs: string[] }[];
}) {
    const { t, i18n } = useTranslation();
    const date = new Date(`${version}T12:00:00`).toLocaleDateString(
        i18n.language,
        { dateStyle: 'long' },
    );

    return (
        <>
            <Head title={title} />
            <article className="mx-auto max-w-3xl space-y-6 px-4 py-12">
                <header className="space-y-2">
                    <h1 className="text-title font-semibold">{title}</h1>
                    <p className="text-body text-muted-foreground">
                        {t('consents.documents.versionOf', { date })}
                    </p>
                </header>
                {intro.map((paragraph) => (
                    <p key={paragraph} className="text-body">
                        {paragraph}
                    </p>
                ))}
                {sections.map((section) => (
                    <section key={section.heading} className="space-y-3">
                        <h2 className="text-lg font-semibold">
                            {section.heading}
                        </h2>
                        {section.paragraphs.map((paragraph) => (
                            <p key={paragraph} className="text-body">
                                {paragraph}
                            </p>
                        ))}
                    </section>
                ))}
            </article>
        </>
    );
}
