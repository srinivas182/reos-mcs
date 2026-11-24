<?php

declare(strict_types=1);

namespace App\Http\Controllers\VirtualBroker;

use App\Http\Controllers\Controller;
use App\Http\Requests\VirtualBroker\BrokerReplyRequest;
use App\Http\Requests\VirtualBroker\PolicyManualRequest;
use App\Http\Requests\VirtualBroker\VirtualBrokerSettingsRequest;
use App\Models\User;
use App\VirtualBroker\PolicyKnowledge;
use App\VirtualBroker\VirtualBroker;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The virtual broker, broker side (A12.1, ADR 0047; broker web screen 34,
 * broker journey screens 5, 11 and 12): the policy manual and its
 * versions, TREC conflicts, the FAQ, who answers and how fast, and the
 * agents' questions with the AI draft. Every change is audited.
 */
final class BrokerVirtualBrokerController extends Controller
{
    public function index(Request $request, #[CurrentUser] User $user, VirtualBroker $broker): Response
    {
        $open = $request->query('question');

        return Inertia::render('organisation/virtual-broker', [
            'broker' => $broker->forBroker($user),
            'open' => is_string($open) && preg_match('/^[0-9a-f-]{36}$/', $open) === 1 ? $open : null,
        ]);
    }

    public function manual(PolicyManualRequest $request, #[CurrentUser] User $user, VirtualBroker $broker, PolicyKnowledge $knowledge): RedirectResponse
    {
        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile, 422);
        $manual = $knowledge->upload($broker->brokerageOfBroker($user), $file, $user);

        return back()->with('success', __('virtualBroker.broker.uploaded', ['version' => (string) $manual->version]));
    }

    public function settings(VirtualBrokerSettingsRequest $request, #[CurrentUser] User $user, VirtualBroker $broker): RedirectResponse
    {
        $answerer = $request->integer('answerer_id');
        $broker->saveSettings($broker->brokerageOfBroker($user), $answerer > 0 ? $answerer : null, $request->integer('reply_hours'), $user);

        return back()->with('success', __('virtualBroker.broker.saved'));
    }

    public function reply(BrokerReplyRequest $request, string $question, #[CurrentUser] User $user, VirtualBroker $broker): RedirectResponse
    {
        $broker->reply($broker->brokerageQuestion($user, $question), $user, $request->string('answer')->toString(), $request->boolean('add_to_knowledge'));

        return back()->with('success', __('virtualBroker.broker.sent'));
    }

    public function removeFaq(int $entry, #[CurrentUser] User $user, VirtualBroker $broker, PolicyKnowledge $knowledge): RedirectResponse
    {
        $knowledge->removeFaq($broker->brokerageOfBroker($user), $entry, false, $user);

        return back();
    }

    public function restoreFaq(int $entry, #[CurrentUser] User $user, VirtualBroker $broker, PolicyKnowledge $knowledge): RedirectResponse
    {
        $knowledge->removeFaq($broker->brokerageOfBroker($user), $entry, true, $user);

        return back();
    }
}
