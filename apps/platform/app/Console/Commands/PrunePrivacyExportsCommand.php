<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PrivacyRequest;
use App\Services\Privacy\PrivacyRequests;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('reos:privacy:prune')]
#[Description('Delete "see my data" exports once their 7-day link has expired')]
final class PrunePrivacyExportsCommand extends Command
{
    public function handle(): int
    {
        $count = 0;

        PrivacyRequest::query()->whereNotNull('export_path')->where('completed_at', '<=', now()->subDays(PrivacyRequests::LINK_DAYS))
            ->lazyById()->each(function (PrivacyRequest $request) use (&$count): void {
                Storage::delete((string) $request->export_path);
                $request->forceFill(['export_path' => null])->save();
                $count++;
            });

        $this->components->info("{$count} exports deleted.");

        return self::SUCCESS;
    }
}
