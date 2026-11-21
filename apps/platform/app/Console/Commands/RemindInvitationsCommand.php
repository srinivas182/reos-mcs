<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Tenancy\Invitations;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('reos:invites:remind')]
#[Description('Send the one reminder for invitations still open after 7 days')]
final class RemindInvitationsCommand extends Command
{
    public function handle(Invitations $invitations): int
    {
        $this->components->info($invitations->remind().' reminders sent.');

        return self::SUCCESS;
    }
}
