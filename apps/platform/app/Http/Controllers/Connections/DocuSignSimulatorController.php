<?php

declare(strict_types=1);

namespace App\Http\Controllers\Connections;

use App\Connections\Drivers\DocuSign\DocuSignDriver;
use App\Connections\Drivers\DocuSign\DocuSignSimulator;
use App\Connections\Drivers\DocuSign\EnvelopeRecords;
use App\Http\Controllers\Controller;
use App\Integrations\IntegrationMode;
use App\Models\Connection;
use App\Models\SimulatorRecord;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The demo-data stand-in for DocuSign's sender view (B9.3, Simulator mode
 * only). Plainly a REOS demo page: it lists the documents and signers and
 * offers Send, which sends the demo envelope and goes back to REOS, as
 * DocuSign would with `event=Send`.
 */
final class DocuSignSimulatorController extends Controller
{
    public function show(Request $request, string $envelope, #[CurrentUser] User $user): Response
    {
        [$connection, $row, $return] = $this->guard($request, $envelope, $user);
        $data = $row->data;
        $record = EnvelopeRecords::envelope(['envelopeId' => $envelope, 'status' => $data['status'] ?? 'created', 'recipients' => ['signers' => $data['signers'] ?? []]]);

        return Inertia::render('connections/docusign-sender', [
            'subject' => is_string($data['emailSubject'] ?? null) ? $data['emailSubject'] : '',
            'documents' => array_values(array_filter(array_map(static fn (mixed $d): ?string => is_array($d) && is_string($d['name'] ?? null) ? $d['name'] : null, is_array($data['documents'] ?? null) ? $data['documents'] : []))),
            'signers' => array_map(static fn (array $s): array => ['name' => $s['name'], 'email' => $s['email'], 'routing_order' => $s['routing_order']], $record['signers']),
            'sendUrl' => $request->fullUrl(),
            'cancelUrl' => $return.'?event=Cancel',
            'csrfToken' => csrf_token(),
            'account' => $connection->account_label,
        ]);
    }

    public function send(Request $request, string $envelope, #[CurrentUser] User $user): RedirectResponse
    {
        [$connection, , $return] = $this->guard($request, $envelope, $user);
        DocuSignSimulator::send($connection, $envelope);

        return redirect()->away($return.'?event=Send');
    }

    /** @return array{0: Connection, 1: SimulatorRecord, 2: string} */
    private function guard(Request $request, string $envelope, User $user): array
    {
        $connection = Connection::query()->find($request->integer('connection'));
        abort_unless($connection !== null && $connection->integration_id === DocuSignDriver::INTEGRATION && $connection->mode === IntegrationMode::Simulator
            && $connection->owner_type === Connection::OWNER_USER && $connection->owner_id === $user->id, 404);
        $row = DocuSignSimulator::row($connection, $envelope);
        abort_if($row === null, 404);
        $return = $request->string('return')->toString();
        // Only back to REOS's own return address, never an open redirect.
        abort_unless(preg_match('#^'.preg_quote(url('/deals/signatures').'/', '#').'[0-9a-f-]{36}/returned$#', $return) === 1, 400);

        return [$connection, $row, $return];
    }
}
