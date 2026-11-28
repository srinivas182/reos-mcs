<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Tenancy\RowLevelSecurity;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Proves tenant isolation can't be skipped (ADR 0015): every table with an
 * `organisation_id` has the forced row-level security policy (or is on the
 * reviewed list below), and the app's database user can't bypass it.
 * Runs in CI after the migrations, and in the deploy health check.
 */
#[Signature('reos:tenancy:check')]
#[Description('Check that tenant tables have row-level security and the database user cannot bypass it')]
final class CheckTenancy extends Command
{
    /**
     * Tables with an organisation column that are not tenant data, and why.
     *
     * @var array<string, string>
     */
    public const EXEMPT = [
        'roles' => 'Role names per organisation (Spatie teams); read to check any permission.',
        'model_has_roles' => 'Who holds which role where; the role switcher lists all of a person\'s organisations.',
        'model_has_permissions' => 'Direct permissions per organisation; same as model_has_roles.',
        'conversations' => 'Messages (B6.1, ADR 0023): a conversation spans tenants (a team agent and the brokerage\'s broker); only its participants can open it, checked in Conversations and on the live channel. organisation_id is only where it started.',
        'quiz_questions' => 'Question bank (A8.1, ADR 0031): a school\'s approved questions are practised by its learners in any tenant, like its courses; reviewing and editing is checked against the owner school in QuestionBank. organisation_id is the owner, empty for REOS\'s bank.',
        'courses' => 'Courses (A7.1, ADR 0025): a school\'s courses are sold to learners in any tenant (an agent at a brokerage takes a school\'s CE course); editing is checked against the owner school in CourseApprovals and the course builder. organisation_id is the owner, empty for REOS courses.',
        'study_groups' => 'Study groups (A9.3c, ADR 0036): a group spans tenants (a school\'s class group joined by candidates studying through REOS or the school), found by its unguessable invite link; membership is checked in StudyGroups, and a school sees only its own groups\' member counts. organisation_id is the school that made it, empty for a candidate\'s own group.',
        'ce_reports' => 'CE reporting (A11.3, ADR 0045): a school reports completions by learners in any tenant (an agent at a brokerage took the school\'s CE course), and each learner sees their own completion\'s status and TREC reference in their CE wallet. The school\'s list, file, reference and rejections are checked against organisation_id in CeReporting and CeReportsController. organisation_id is the reporting school.',
        'referrals' => 'Referrals (B14.3b, ADR 0067): a referral goes from an agent at one brokerage to an agent at another (or outside REOS), so it spans tenants; only the sending and receiving agents can open it, checked in Referrals. organisation_id is only where it started.',
        'video_sessions' => 'Video sessions (B6.3, ADR 0024): a session spans tenants (a school\'s instructor and a brokerage\'s agent); only its participants can open it, checked in VideoSessions and when minting the room token. organisation_id is only the host\'s organisation.',
    ];

    public function handle(): int
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->components->warn('Row-level security is PostgreSQL only; skipped on '.DB::getDriverName().'.');

            return self::SUCCESS;
        }

        $problems = [];

        $role = (array) DB::selectOne('select current_user as name, rolsuper, rolbypassrls from pg_roles where rolname = current_user');
        if (($role['rolsuper'] ?? false) === true || ($role['rolbypassrls'] ?? false) === true) {
            $name = is_string($role['name'] ?? null) ? $role['name'] : 'in use';
            $problems[] = "The database user {$name} is a superuser or has BYPASSRLS, so row-level security does not apply to it. Connect as a normal user (see docs/adr/0015-tenancy.md).";
        }

        $tables = DB::select(<<<'SQL'
            select c.relname as name, c.relrowsecurity as enabled, c.relforcerowsecurity as forced,
                   exists(select 1 from pg_policy p where p.polrelid = c.oid and p.polname = ?) as has_policy
            from pg_attribute a
            join pg_class c on c.oid = a.attrelid
            join pg_namespace n on n.oid = c.relnamespace
            where a.attname = 'organisation_id' and not a.attisdropped
              and c.relkind = 'r' and n.nspname = current_schema()
            order by c.relname
            SQL, [RowLevelSecurity::POLICY]);

        foreach ($tables as $row) {
            $table = (array) $row;
            $name = is_string($table['name'] ?? null) ? $table['name'] : '';

            if (isset(self::EXEMPT[$name])) {
                continue;
            }

            if (($table['enabled'] ?? false) !== true || ($table['forced'] ?? false) !== true || ($table['has_policy'] ?? false) !== true) {
                $problems[] = "Table {$name} has organisation_id but no forced row-level security policy. Call RowLevelSecurity::protect('{$name}') in its migration, or add it to CheckTenancy::EXEMPT with the reason.";
            }
        }

        if ($problems !== []) {
            foreach ($problems as $problem) {
                $this->components->error($problem);
            }

            return self::FAILURE;
        }

        $this->components->info(count($tables).' tables checked; tenant isolation is enforced by the database.');

        return self::SUCCESS;
    }
}
