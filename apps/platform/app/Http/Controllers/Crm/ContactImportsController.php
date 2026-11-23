<?php

declare(strict_types=1);

namespace App\Http\Controllers\Crm;

use App\Authorization\Role;
use App\Crm\CsvImports;
use App\Crm\Sources;
use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\ContactImportRequest;
use App\Models\ContactImport;
use App\Models\User;
use App\Services\Authorization\ActiveRole;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CSV import, the last resort (screen 13, B8.3b): upload, map the columns,
 * preview who is new and who joins a contact, import, and undo. The agent's
 * own imports only.
 */
final class ContactImportsController extends Controller
{
    public function index(Request $request, #[CurrentUser] User $user, ActiveRole $roles, Sources $sources): Response
    {
        $this->agent($user, $roles);
        $current = is_string($request->query('import')) ? $this->mine($user)->where('uuid', $request->query('import'))->where('status', 'uploaded')->first() : null;

        return Inertia::render('contacts/import', [
            'fields' => ContactImport::FIELDS,
            'max_rows' => CsvImports::MAX_ROWS,
            'current' => $current === null ? null : [
                'id' => $current->uuid, 'file_name' => $current->file_name, 'rows' => $current->rows, 'headers' => $current->headers, 'mapping' => $current->mapping ?? [],
            ],
            'preview' => $request->session()->get('import_preview'),
            'imports' => $this->mine($user)->whereIn('status', ['imported', 'undone'])->latest('id')->limit(20)->get()->map(static fn (ContactImport $i): array => [
                'id' => $i->uuid, 'file_name' => $i->file_name, 'name' => $sources->name($i->source()), 'status' => $i->status,
                'counts' => $i->counts, 'imported_at' => $i->imported_at?->toIso8601String(), 'undone_at' => $i->undone_at?->toIso8601String(),
            ])->values()->all(),
        ]);
    }

    public function upload(ContactImportRequest $request, #[CurrentUser] User $user, ActiveRole $roles, CsvImports $imports): RedirectResponse
    {
        $this->agent($user, $roles);
        $file = $request->file('file');
        abort_unless($file instanceof UploadedFile, 422);
        $import = $imports->upload($user, $file);

        return to_route('contacts.import.index', ['import' => $import->uuid]);
    }

    public function preview(ContactImportRequest $request, string $import, #[CurrentUser] User $user, ActiveRole $roles, CsvImports $imports): RedirectResponse
    {
        $found = $this->find($user, $roles, $import);

        return to_route('contacts.import.index', ['import' => $found->uuid])->with('import_preview', $imports->preview($found, $request->mapping()));
    }

    public function import(string $import, #[CurrentUser] User $user, ActiveRole $roles, CsvImports $imports): RedirectResponse
    {
        $imports->import($this->find($user, $roles, $import));

        return to_route('contacts.import.index')->with('success', __('crm.import.started'));
    }

    public function undo(string $import, #[CurrentUser] User $user, ActiveRole $roles, CsvImports $imports): RedirectResponse
    {
        $imports->undo($this->find($user, $roles, $import));

        return to_route('contacts.import.index')->with('success', __('crm.import.undone'));
    }

    /** @return Builder<ContactImport> */
    private function mine(User $user): Builder
    {
        return ContactImport::query()->where('user_id', $user->id);
    }

    private function find(User $user, ActiveRole $roles, string $uuid): ContactImport
    {
        $this->agent($user, $roles);

        return $this->mine($user)->where('uuid', $uuid)->first() ?? abort(404);
    }

    private function agent(User $user, ActiveRole $roles): void
    {
        abort_unless($roles->for($user)?->role === Role::Agent, 404);
    }
}
