<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Authorization\Membership;
use App\Authorization\Role;
use App\Enums\Locale;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Authorization\Memberships;
use App\Support\Preview;
use Database\Seeders\Demo\Personas;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Creates pilot user accounts for a usability check (P7.0, docs/preview.md).
 *
 * Reads a CSV (`participant,name,email,role,language`, from a file or `-`
 * for standard input). Each person joins the same organisation as the
 * journey persona for their role, with that persona's roles, so their
 * relationships work as in the journeys. Prints `participant,email,password`
 * for the moderator. Only in the preview, never in production.
 */
#[Signature('reos:preview:pilots {file : CSV file, or - for standard input}')]
#[Description('Create pilot user accounts in the preview from a CSV')]
final class PreviewPilotsCommand extends Command
{
    public function handle(Memberships $memberships): int
    {
        if (! Preview::on()) {
            $this->error('Pilot accounts are only for the preview (REOS_PREVIEW=true).');

            return self::FAILURE;
        }

        $file = (string) $this->argument('file');
        $handle = $file === '-' ? STDIN : @fopen($file, 'r');

        if ($handle === false) {
            $this->error("Can't read {$file}.");

            return self::FAILURE;
        }

        $header = fgetcsv($handle, escape: '\\');
        $columns = is_array($header) ? array_map(static fn ($c): string => strtolower(trim((string) $c)), $header) : [];
        $missing = array_diff(['participant', 'name', 'email', 'role', 'language'], $columns);

        if ($missing !== []) {
            $this->error('The CSV needs the columns participant, name, email, role and language (missing: '.implode(', ', $missing).').');

            return self::FAILURE;
        }

        $personas = collect(Personas::all())->keyBy(static fn ($persona): string => $persona->role);
        $rows = [];
        $line = 1;

        while (($values = fgetcsv($handle, escape: '\\')) !== false) {
            $line++;

            if ($values === [null]) {
                continue;
            }

            $row = array_combine($columns, array_pad(array_map(static fn ($v): string => trim((string) $v), $values), count($columns), ''));
            $persona = $personas->get($row['role']);
            $locale = Locale::tryFrom($row['language']);

            if ($persona === null || $row['role'] === Role::SuperAdmin->value) {
                $this->error("Line {$line}: role must be one of ".implode(', ', $personas->keys()->reject(static fn (string $r): bool => $r === Role::SuperAdmin->value)->all()).'.');

                return self::FAILURE;
            }

            if ($locale === null || filter_var($row['email'], FILTER_VALIDATE_EMAIL) === false || $row['name'] === '' || $row['participant'] === '') {
                $this->error("Line {$line}: needs a participant id, a name, a valid email and a language (en or es).");

                return self::FAILURE;
            }

            $rows[] = [$row, $persona, $locale];
        }

        if ($file !== '-') {
            fclose($handle);
        }

        $this->line('participant,email,password');

        foreach ($rows as [$row, $persona, $locale]) {
            $password = Str::password(14, symbols: false);

            DB::transaction(function () use ($row, $persona, $locale, $password, $memberships): void {
                $user = User::query()->firstOrNew(['email' => strtolower($row['email'])]);
                $user->forceFill(['name' => $row['name'], 'password' => Hash::make($password), 'email_verified_at' => now(), 'locale' => $locale])->save();
                $organisation = Organisation::query()->where('name', $persona->organisation)->firstOrFail();

                foreach ($persona->roles() as $role) {
                    $held = $memberships->of($user);

                    if (! collect($held)->contains(static fn (Membership $m): bool => $m->is(Role::from($role), $organisation->id))) {
                        $memberships->grant($user, Role::from($role), $organisation);
                    }
                }
            });

            $this->line("{$row['participant']},".strtolower($row['email']).",{$password}");
        }

        return self::SUCCESS;
    }
}
