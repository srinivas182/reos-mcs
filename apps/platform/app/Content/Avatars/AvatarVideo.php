<?php

declare(strict_types=1);

namespace App\Content\Avatars;

/**
 * An avatar video vendor (A7.3, ADR 0026; decision 1: HeyGen and Synthesia
 * behind one driver). REOS asks for a video, checks it every two minutes
 * until it's ready, copies it into its own storage and video pipeline, then
 * deletes the vendor's copy. The simulator "renders" a short placeholder.
 *
 * @phpstan-type Check array{status: 'rendering'|'ready'|'failed', url: string|null, seconds: int|null, error: string|null}
 */
interface AvatarVideo
{
    /** heygen, synthesia or simulated. */
    public function vendor(): string;

    public function simulated(): bool;

    /** The stock presenter's avatar id set in Settings › Integrations. */
    public function stockAvatar(): string;

    /** Asks for a video; returns the vendor's id for it. */
    public function start(string $avatar, string $language, string $script, string $title, string $reference): string;

    /** @return Check */
    public function check(string $id): array;

    /** Deletes the vendor's copy once REOS has its own. */
    public function delete(string $id): void;
}
