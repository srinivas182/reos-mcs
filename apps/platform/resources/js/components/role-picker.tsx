import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { AUDIENCES } from '@/components/public-header';
import { audience } from '@/routes';

/**
 * "I am …": the home page's six roles (web journey screen 1). Each opens that
 * role's landing page and sign-up.
 */
export function RolePicker({
    state,
}: {
    /** The only live state, or null when several are live. */
    state: string | null;
}) {
    const { t } = useTranslation();

    return (
        <section aria-labelledby="role-picker" id="roles" className="space-y-3">
            <h2
                id="role-picker"
                className="text-base font-semibold text-foreground"
            >
                {t('site.home.iAm')}
            </h2>
            <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                {AUDIENCES.map((key, index) => (
                    <li key={key}>
                        <Link
                            href={audience(key)}
                            className={
                                'flex h-full min-h-18 items-center rounded-reos-md border bg-card px-4 py-3 transition-colors hover:border-primary focus-visible:border-primary ' +
                                (index === 0
                                    ? 'border-primary ring-1 ring-primary'
                                    : 'border-border')
                            }
                        >
                            <span className="flex-1">
                                <span className="block font-semibold text-foreground">
                                    {t(`site.home.roles.${key}.label`)}
                                </span>
                                <span className="block text-sm text-muted-foreground">
                                    {key === 'service-providers' && !state
                                        ? t(
                                              'site.home.roles.service-providers.descriptionAny',
                                          )
                                        : t(
                                              `site.home.roles.${key}.description`,
                                              { state: state ?? '' },
                                          )}
                                </span>
                            </span>
                        </Link>
                    </li>
                ))}
            </ul>
        </section>
    );
}
