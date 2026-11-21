import { Head, Link } from '@inertiajs/react';
import { Mail } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type { QuizTopic } from '@/components/quiz-card';
import type { TopicLevel } from '@/components/score-summary';
import { ScoreSummary } from '@/components/score-summary';
import type { SignInOptionsAvailable } from '@/components/sign-in-options';
import { PhoneSignIn, ProviderButtons } from '@/components/sign-in-options';
import { Button } from '@/components/ui/button';
import { login, register } from '@/routes';

type Props = {
    correct: number;
    total: number;
    topics: { topic: QuizTopic; level: TopicLevel }[];
    signInOptions: SignInOptionsAvailable;
};

/**
 * Score, then sign up (candidate journey screen 7, on the web). Whichever way
 * they sign up, the score and choices are saved to the new account.
 */
export default function Score({
    correct,
    total,
    topics,
    signInOptions,
}: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('site.getStarted.scoreTitle')} />
            <div className="mx-auto max-w-xl space-y-8 px-4 py-10 md:py-16">
                <ScoreSummary correct={correct} total={total} topics={topics} />

                <section aria-labelledby="save-score" className="space-y-3">
                    <h2
                        id="save-score"
                        className="text-center text-muted-foreground"
                    >
                        {t('onboarding.score.save')}
                    </h2>
                    <ProviderButtons available={signInOptions} />
                    {signInOptions.phone ? <PhoneSignIn /> : null}
                    <Button asChild variant="outline" className="w-full">
                        <Link href={register()}>
                            <Mail aria-hidden />
                            {t('site.getStarted.signUpEmail')}
                        </Link>
                    </Button>
                    <p className="text-center text-muted-foreground">
                        <Link
                            href={login()}
                            className="font-semibold text-foreground underline underline-offset-4"
                        >
                            {t('onboarding.welcome.haveAccount')}
                        </Link>
                    </p>
                </section>
            </div>
        </>
    );
}
