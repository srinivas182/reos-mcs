<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Content\Knowledge\Ingestion;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('reos:knowledge:embed {--limit=2000 : At most this many passages per run}')]
#[Description('Embed knowledge base passages that have no embedding yet (after Titan is switched on, or a failed try)')]
final class KnowledgeEmbedCommand extends Command
{
    public function handle(Ingestion $ingestion): int
    {
        $count = $ingestion->embedMissing(limit: max(1, (int) $this->option('limit')));
        $this->components->info("{$count} passages embedded.");

        return self::SUCCESS;
    }
}
