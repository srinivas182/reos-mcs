<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Content\ExamSuccess;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Installs REOS's Exam success course for review (A9.2f, decision 5): the
 * eight scripts become lessons through the AI lesson pipeline, waiting on
 * screen 44 for the reviewers named here (the course's instructors).
 * Safe to run again: lessons already in review or approved are left alone.
 */
#[Signature('reos:content:exam-success {--reviewer=* : Email of a reviewer (an instructor or school admin)}')]
#[Description('Installs the Exam success course for review')]
final class ExamSuccessCommand extends Command
{
    public function handle(ExamSuccess $examSuccess): int
    {
        $emails = array_values(array_filter((array) $this->option('reviewer'), is_string(...)));
        $reviewers = User::query()->whereIn('email', $emails)->get();

        if ($reviewers->count() !== count(array_unique($emails))) {
            $this->components->error('Every reviewer must be an existing user.');

            return self::FAILURE;
        }

        $course = $examSuccess->install(array_values($reviewers->all()));
        $this->components->info("Exam success course {$course->id} is waiting for review: {$course->lessons()->count()} lessons.");

        return self::SUCCESS;
    }
}
