<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data repair: sub assignments created before the sub registered (or written
 * by the mobile assignSub path, which never resolved accounts) carry
 * user_id NULL and are invisible on the sub's calendar — visibility keys on
 * event_members.user_id / rehearsal_subs.user_id.
 *
 * Links those rows to accounts by email, creates the band_subs rows the
 * mobile band-access middleware needs, and grants the global (team 0) `sub`
 * role that UserEventsService requires for the sub-only calendar path.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Link event_members to accounts by email. The (event_id, user_id)
        // unique index still contains soft-deleted rows, so skip any row
        // whose user already appears on the same event.
        DB::statement(<<<'SQL'
            UPDATE event_members em
            JOIN users u ON u.email = em.email
            LEFT JOIN event_members dup
                ON dup.event_id = em.event_id
                AND dup.user_id = u.id
            SET em.user_id = u.id, em.updated_at = NOW()
            WHERE em.user_id IS NULL
              AND em.deleted_at IS NULL
              AND dup.id IS NULL
        SQL);

        // Same for rehearsal_subs, guarded on (rehearsal_id, user_id).
        DB::statement(<<<'SQL'
            UPDATE rehearsal_subs rs
            JOIN users u ON u.email = rs.email
            LEFT JOIN rehearsal_subs dup
                ON dup.rehearsal_id = rs.rehearsal_id
                AND dup.user_id = u.id
            SET rs.user_id = u.id, rs.updated_at = NOW()
            WHERE rs.user_id IS NULL
              AND rs.deleted_at IS NULL
              AND dup.id IS NULL
        SQL);

        // band_subs for linked sub rows (custom subs only — roster-backed
        // event_members rows belong to band personnel). Owners/members of the
        // band are excluded: they don't sub for their own band.
        DB::statement(<<<'SQL'
            INSERT INTO band_subs (user_id, band_id, created_at, updated_at)
            SELECT DISTINCT em.user_id, em.band_id, NOW(), NOW()
            FROM event_members em
            LEFT JOIN band_subs bs ON bs.user_id = em.user_id AND bs.band_id = em.band_id
            LEFT JOIN band_members bm ON bm.user_id = em.user_id AND bm.band_id = em.band_id
            LEFT JOIN band_owners bo ON bo.user_id = em.user_id AND bo.band_id = em.band_id
            WHERE em.user_id IS NOT NULL
              AND em.roster_member_id IS NULL
              AND em.deleted_at IS NULL
              AND bs.id IS NULL
              AND bm.id IS NULL
              AND bo.id IS NULL
        SQL);

        DB::statement(<<<'SQL'
            INSERT INTO band_subs (user_id, band_id, created_at, updated_at)
            SELECT DISTINCT rs.user_id, rs.band_id, NOW(), NOW()
            FROM rehearsal_subs rs
            LEFT JOIN band_subs bs ON bs.user_id = rs.user_id AND bs.band_id = rs.band_id
            LEFT JOIN band_members bm ON bm.user_id = rs.user_id AND bm.band_id = rs.band_id
            LEFT JOIN band_owners bo ON bo.user_id = rs.user_id AND bo.band_id = rs.band_id
            WHERE rs.user_id IS NOT NULL
              AND rs.deleted_at IS NULL
              AND bs.id IS NULL
              AND bm.id IS NULL
              AND bo.id IS NULL
        SQL);

        // Every band_subs user needs the `sub` role at team 0 — that is the
        // team UserEventsService pins before hasRole('sub'). Historic flows
        // assigned it under the band's team id (or not at all), which fails
        // that check. Skip on fresh installs where the role isn't seeded yet.
        $subRoleId = DB::table('roles')
            ->where('name', 'sub')
            ->where(function ($q) {
                $q->where('team_id', 0)->orWhereNull('team_id');
            })
            ->value('id');

        if ($subRoleId !== null) {
            DB::statement(<<<'SQL'
                INSERT INTO model_has_roles (role_id, model_type, model_id, team_id)
                SELECT DISTINCT ?, 'App\\Models\\User', bs.user_id, 0
                FROM band_subs bs
                LEFT JOIN model_has_roles mhr
                    ON mhr.role_id = ?
                    AND mhr.model_type = 'App\\Models\\User'
                    AND mhr.model_id = bs.user_id
                    AND mhr.team_id = 0
                WHERE mhr.role_id IS NULL
            SQL, [$subRoleId, $subRoleId]);
        }
    }

    public function down(): void
    {
        // Data repair — not reversible.
    }
};
