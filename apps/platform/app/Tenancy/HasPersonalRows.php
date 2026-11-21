<?php

declare(strict_types=1);

namespace App\Tenancy;

/**
 * A tenant model whose table also holds a person's own rows (no
 * organisation), for example their own Google Calendar connection. Those
 * rows stay visible to the tenant scope; who may see them is decided in the
 * app (ADR 0015).
 */
interface HasPersonalRows {}
