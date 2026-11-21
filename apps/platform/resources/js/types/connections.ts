import type { Schemas } from '@reos/api-client';
import type { IntegrationCategory } from './integrations';

/**
 * A tool in this person's catalogue (`CatalogueItemResource`, generated from
 * the API). The register's schema limits `category` to the known categories.
 */
export type CatalogueItem = Omit<
    Schemas['CatalogueItemResource'],
    'category'
> & { category: IntegrationCategory };

/** A connected account (`ConnectionResource`, generated from the API). */
export type ToolConnection = Schemas['ConnectionResource'];
