<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the admin dashboard / campaign pages (PR-3). Measured on 50k reviews + 20k notifications
 * (MariaDB 10.4): per-campaign aggregate 39.8 -> 9.4 ms, difficulty group-by 20.5 -> 7.5 ms, DISTINCT
 * difficulty 17.6 -> 0.2 ms, filtered campaign count 3.0 -> 0.6 ms, notifications 2.9 -> 0.2 ms and
 * 2.1 -> 0.1 ms. INSERT cost is unchanged (~0.52 ms per review with or without them).
 * Names are explicit because the generated ones would exceed MySQL's 64 character limit.
 *
 * DEPLOYMENT: on InnoDB (MySQL 5.7/8, MariaDB 10.x) ADD INDEX is online (INPLACE, LOCK=NONE) so students can keep
 * submitting, but it waits for a short metadata lock: run it when no long query / open transaction is on these
 * tables (SHOW FULL PROCESSLIST, information_schema.innodb_trx), ideally with a low lock_wait_timeout. DDL is not
 * transactional, so up() skips indexes that already exist and can simply be re-run after a partial failure.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexes('course_reviews', [
            // Per-campaign lists ordered/filtered by date (no filesort) and their COUNT(*).
            'reviews_campaign_created_idx'          => [['review_campaign_id', 'created_at']],
            // Makes the dashboard's per-campaign rating aggregate and totals an index-only scan.
            'reviews_campaign_rating_recommend_idx' => [['review_campaign_id', 'overall_rating', 'would_recommend']],
            // Difficulty distribution and the DISTINCT list of the export wizard.
            'reviews_difficulty_idx'                => ['difficulty_level'],
        ]);

        $this->addIndexes('admin_notifications', [
            // Read on every admin page (latest 10 + unread count); the table grows by one row per review.
            'notifications_created_idx' => ['created_at'],
            'notifications_read_idx'    => ['read_at'],
        ]);
    }

    public function down(): void
    {
        $this->dropIndexes('admin_notifications', ['notifications_read_idx', 'notifications_created_idx']);
        $this->dropIndexes('course_reviews', ['reviews_difficulty_idx', 'reviews_campaign_rating_recommend_idx', 'reviews_campaign_created_idx']);
    }

    /** @param array<string, array|string> $indexes name => column(s); a single-element nested array means "composite". */
    private function addIndexes(string $table, array $indexes): void
    {
        foreach ($indexes as $name => $columns) {
            if (Schema::hasIndex($table, $name)) {
                continue;
            }

            Schema::table($table, fn (Blueprint $t) => $t->index(is_array($columns[0] ?? null) ? $columns[0] : $columns, $name));
        }
    }

    private function dropIndexes(string $table, array $names): void
    {
        foreach ($names as $name) {
            if (Schema::hasIndex($table, $name)) {
                Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
            }
        }
    }
};
