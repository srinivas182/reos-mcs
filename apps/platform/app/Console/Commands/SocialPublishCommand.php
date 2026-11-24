<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Social\SocialPosts;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** Every minute: scheduled social posts whose time has come go out (B11). */
#[Signature('reos:social:publish')]
#[Description('Publish scheduled social posts that are due (B11)')]
final class SocialPublishCommand extends Command
{
    public function handle(SocialPosts $posts): int
    {
        $this->info('Publishing '.$posts->publishDue().' posts.');

        return self::SUCCESS;
    }
}
