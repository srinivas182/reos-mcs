import type { TenantType } from './tenants';

/** A tenant's brand as the web app shows it (A4.2a). */
export type BrandProps = {
    organisation_id: number;
    type: TenantType;
    name: string;
    app_label: string | null;
    logo_url: string | null;
    primary: string | null;
    palette: { action: string; pressed: string; tint: string } | null;
};
