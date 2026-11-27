<?php

declare(strict_types=1);

namespace App\Http\Controllers\Studio;

use App\Authorization\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Authorization\ActiveRole;
use App\Social\SocialAccounts;
use App\Studio\Celebrations\CandidateStudio;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** The free candidate studio on the web (B13.3): moments to post, with the candidate's connected accounts. */
final class CandidateStudioPageController extends Controller
{
    public function __invoke(#[CurrentUser] User $user, ActiveRole $roles, CandidateStudio $studio, SocialAccounts $accounts): Response
    {
        if ($roles->for($user)?->role !== Role::Candidate) {
            throw new NotFoundHttpException;
        }

        $targets = [];

        foreach ($accounts->for($user) as $account) {
            $connection = $account['connection'];

            foreach ($connection === null ? [] : $account['targets'] as $target) {
                $targets[] = ['connection' => $connection['id'] ?? 0, 'channel' => $target['channel'], 'id' => $target['id'], 'name' => $target['name']];
            }
        }

        return Inertia::render('candidate-studio/index', ['moments' => $studio->moments($user), 'targets' => $targets]);
    }
}
