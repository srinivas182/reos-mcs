import { useTranslation } from 'react-i18next';
import { Switch } from '@/components/ui/switch';
import type { TenantFeature, TenantFeatureKey } from '@/types';

/**
 * "Features for this tenant" (screen 60). Beta features show their badge
 * instead of a switch: they're turned on from Features and releases.
 */
export function TenantFeatureSwitches({
    features,
    onChange,
}: {
    features: TenantFeature[];
    onChange: (key: TenantFeatureKey, on: boolean) => void;
}) {
    const { t } = useTranslation();

    return (
        <section
            className="rounded-reos-lg border border-border bg-card p-5"
            aria-labelledby="features-title"
        >
            <h2 id="features-title" className="mb-2 font-semibold">
                {t('tenants.features.title')}
            </h2>
            <ul className="divide-y divide-border">
                {features.map((feature) => {
                    const name = t(`tenants.features.names.${feature.key}`);
                    const hint =
                        feature.availability === 'beta'
                            ? t('tenants.features.betaHint')
                            : feature.availability === 'add_on'
                              ? t('tenants.features.addOn')
                              : feature.default
                                ? t('tenants.features.defaultOn')
                                : t('tenants.features.defaultOff');

                    return (
                        <li
                            key={feature.key}
                            className="flex min-h-14 items-center justify-between gap-4 py-1"
                        >
                            <span>
                                <span className="block font-medium">
                                    {name}
                                </span>
                                <span className="block text-sm text-muted-foreground">
                                    {hint}
                                </span>
                            </span>
                            {feature.availability === 'beta' ? (
                                <span className="rounded-reos-pill bg-reos-tone-info-bg px-2.5 py-0.5 text-sm font-semibold text-reos-tone-info-fg">
                                    {t('tenants.features.beta')}
                                </span>
                            ) : (
                                <Switch
                                    checked={feature.on}
                                    onCheckedChange={(on) =>
                                        onChange(feature.key, on)
                                    }
                                    aria-label={name}
                                />
                            )}
                        </li>
                    );
                })}
            </ul>
        </section>
    );
}
