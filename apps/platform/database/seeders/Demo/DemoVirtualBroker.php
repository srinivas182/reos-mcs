<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

use App\Models\Organisation;
use App\Models\PolicyManual;
use App\Models\User;
use App\VirtualBroker\PolicyKnowledge;
use Illuminate\Http\UploadedFile;

/**
 * The virtual broker (A12.1): a short sample policy manual for Lone Star
 * Realty, uploaded by Linda Park, so James can ask about advertising,
 * splits, earnest money and showings. Its earnest money line contradicts
 * TREC on purpose, as in the broker journey ("reviews 3 conflicts with
 * TREC rules"). Safe to run again.
 */
final readonly class DemoVirtualBroker
{
    private const MANUAL = <<<'TEXT'
    # 1. Welcome
    Lone Star Realty supervises every sales agent it sponsors. Read this manual in your first week and ask Linda Park about anything that isn't clear.

    # 4. Advertising
    Every ad shows Lone Star Realty's name and logo, and the broker name at least half the size of your largest contact details. Agents send new ad templates to the office for approval before their first use. Agents in their first 90 days send every post for approval.

    # 6. Earnest money
    Earnest money and option fee checks go to the title company named in the contract within three business days. Never hold a client's check overnight in your car.

    # 7. Commission splits
    The standard split is 70/30 until the annual cap of $18,000, then 100%. Referral fees are paid only through Lone Star Realty, never directly.

    # 8. Transaction coordinators
    Use the office's transaction coordinator for every contract. Send the executed contract and the IABS to the coordinator within one business day.

    # 9. Showings
    Agents confirm showings through ShowingTime and leave a business card on the kitchen counter after every showing.

    # 11. Fair housing
    Never describe a neighborhood by who lives there. Describe the property, the schools by name and the commute instead.
    TEXT;

    public function __construct(private PolicyKnowledge $knowledge) {}

    public function seed(): void
    {
        $lone = Organisation::query()->where('name', 'Lone Star Realty')->first();
        $linda = User::query()->where('email', 'linda.park@'.Personas::DOMAIN)->first();

        if ($lone === null || $linda === null || PolicyManual::query()->where('organisation_id', $lone->id)->exists()) {
            return;
        }

        $path = tempnam(sys_get_temp_dir(), 'manual');

        if ($path === false) {
            return;
        }

        file_put_contents($path, self::MANUAL);
        $this->knowledge->upload($lone, new UploadedFile($path, 'Lone Star policy manual 2026.md', 'text/markdown', null, true), $linda);
        @unlink($path);
    }
}
