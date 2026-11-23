<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Licences\Certificates\Certificates;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** Daily (B10.3b): certificates read but not saved within a day are deleted. */
#[Signature('reos:certificates:prune')]
#[Description('Delete certificate uploads that were read but not saved within a day')]
final class CertificatesPruneCommand extends Command
{
    public function handle(Certificates $certificates): int
    {
        $this->components->info($certificates->prune().' unsaved certificate(s) deleted.');

        return self::SUCCESS;
    }
}
