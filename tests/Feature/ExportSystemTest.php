<?php

namespace Tests\Feature;

use App\Exports\ExportCriteria;
use App\Exports\ExportService;
use App\Exports\ReportStatistics;
use App\Exports\ReviewExportQuery;
use App\Exports\Writers\CsvWriter;
use App\Jobs\GenerateExportJob;
use App\Models\CourseReview;
use App\Models\ExportLog;
use App\Models\ExportTemplate;
use App\Models\ReviewCampaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class ExportSystemTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->admin = User::factory()->create();
    }

    private function base(array $overrides = []): array
    {
        return array_merge([
            'report_type'     => 'detailed',
            'format'          => 'csv',
            'campaign_scope'  => 'all',
            'campaign_ids'    => [],
            'period'          => 'all',
            'date_basis'      => 'submitted',
            'recommend'       => 'any',
            'campaign_status' => 'any',
            'columns'         => ['review_id', 'student_name', 'overall_rating'],
        ], $overrides);
    }

    private function review(ReviewCampaign $campaign, array $attrs = [], ?string $createdAt = null): CourseReview
    {
        $review = CourseReview::factory()->for($campaign, 'campaign')->create($attrs);
        if ($createdAt) {
            DB::table('course_reviews')->where('id', $review->id)->update(['created_at' => $createdAt]);
        }

        return $review->refresh();
    }

    private function matching(array $data): int
    {
        return ReviewExportQuery::build(ExportCriteria::fromArray($this->base($data)))->count();
    }

    /** Generate through the HTTP endpoint and return the stored file contents. */
    private function export(array $data): array
    {
        $res = $this->actingAs($this->admin)->postJson(route('admin.exports.store'), $this->base($data));
        $res->assertOk()->assertJsonPath('status', 'completed');
        $log = ExportLog::findOrFail($res->json('id'));

        return [$log, Storage::disk('local')->get($log->file_path)];
    }

    private function csvRows(string $csv): array
    {
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $lines = array_filter(preg_split("/\r?\n/", substr($csv, 3)), 'strlen');

        return array_map(fn ($l) => str_getcsv($l, ',', '"', ''), array_values($lines));
    }

    // ───────────────────────── Access & permissions ─────────────────────────

    public function test_guests_cannot_reach_export_pages(): void
    {
        $this->get(route('admin.exports.index'))->assertRedirect(route('login'));
        $this->postJson(route('admin.exports.store'), $this->base())->assertUnauthorized();
    }

    public function test_wizard_page_and_dashboard_button_render(): void
    {
        ReviewCampaign::factory()->create(['course_name' => 'دورة الاختبار']);

        $this->actingAs($this->admin)->get(route('admin.exports.index'))
            ->assertOk()->assertSee('معالج التصدير')->assertSee('سجل التصدير')->assertSee('دورة الاختبار');
        $this->actingAs($this->admin)->get(route('admin.dashboard'))
            ->assertOk()->assertSee(route('admin.exports.index'));
    }

    public function test_personal_data_is_restricted_by_permission(): void
    {
        config(['exports.pii_allowed_emails' => ['owner@emc.test']]);
        $this->review(ReviewCampaign::factory()->create());

        $this->actingAs($this->admin)->postJson(route('admin.exports.store'), $this->base(['columns' => ['student_name', 'overall_rating']]))
            ->assertStatus(422)->assertJsonValidationErrors('columns');
        $this->actingAs($this->admin)->postJson(route('admin.exports.store'), $this->base(['columns' => ['overall_rating']]))
            ->assertOk();

        $owner = User::factory()->create(['email' => 'owner@emc.test']);
        $this->actingAs($owner)->postJson(route('admin.exports.store'), $this->base(['columns' => ['student_name']]))
            ->assertOk();
    }

    public function test_users_cannot_access_other_users_exports(): void
    {
        $this->review(ReviewCampaign::factory()->create());
        [$log] = $this->export([]);

        $other = User::factory()->create();
        $this->actingAs($other)->get(route('admin.exports.download', $log))->assertForbidden();
        $this->actingAs($other)->getJson(route('admin.exports.status', $log))->assertForbidden();
        $this->actingAs($other)->post(route('admin.exports.regenerate', $log))->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.exports.download', $log))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    // ───────────────────────── Validation ─────────────────────────

    public function test_input_validation(): void
    {
        $this->actingAs($this->admin);

        $this->postJson(route('admin.exports.preview'), $this->base(['period' => 'custom', 'date_from' => '2026-05-10', 'date_to' => '2026-05-01']))
            ->assertStatus(422)->assertJsonValidationErrors('date_to');
        $this->postJson(route('admin.exports.preview'), $this->base(['period' => 'custom', 'date_from' => '10/05/2026', 'date_to' => '2026-05-20']))
            ->assertStatus(422)->assertJsonValidationErrors('date_from');
        $this->postJson(route('admin.exports.preview'), $this->base(['campaign_scope' => 'selected', 'campaign_ids' => [999]]))
            ->assertStatus(422)->assertJsonValidationErrors('campaign_ids.0');
        $this->postJson(route('admin.exports.preview'), $this->base(['campaign_scope' => 'selected', 'campaign_ids' => []]))
            ->assertStatus(422)->assertJsonValidationErrors('campaign_ids');
        $this->postJson(route('admin.exports.preview'), $this->base(['columns' => []]))
            ->assertStatus(422)->assertJsonValidationErrors('columns');
        $this->postJson(route('admin.exports.preview'), $this->base(['columns' => ['password']]))
            ->assertStatus(422)->assertJsonValidationErrors('columns.0');
        $this->postJson(route('admin.exports.preview'), $this->base(['report_type' => 'professional', 'format' => 'xlsx']))
            ->assertStatus(422)->assertJsonValidationErrors('format');
        $this->postJson(route('admin.exports.preview'), $this->base(['ratings' => [6]]))
            ->assertStatus(422)->assertJsonValidationErrors('ratings.0');
    }

    // ───────────────────────── Periods & filters ─────────────────────────

    public function test_relative_and_custom_periods_use_submission_date(): void
    {
        $c = ReviewCampaign::factory()->create();
        $this->review($c, [], now()->subDays(3)->toDateTimeString());
        $this->review($c, [], now()->subDays(20)->toDateTimeString());
        $this->review($c, [], now()->subDays(60)->toDateTimeString());
        $this->review($c, [], now()->subDays(120)->toDateTimeString());

        $this->assertSame(1, $this->matching(['period' => '7']));
        $this->assertSame(2, $this->matching(['period' => '30']));
        $this->assertSame(3, $this->matching(['period' => '90']));
        $this->assertSame(4, $this->matching(['period' => 'all']));
        $this->assertSame(2, $this->matching([
            'period'    => 'custom',
            'date_from' => now()->subDays(65)->toDateString(),
            'date_to'   => now()->subDays(15)->toDateString(),
        ]));
        // Boundary days are inclusive.
        $this->assertSame(1, $this->matching([
            'period'    => 'custom',
            'date_from' => now()->subDays(3)->toDateString(),
            'date_to'   => now()->subDays(3)->toDateString(),
        ]));
    }

    public function test_period_can_be_applied_to_course_date(): void
    {
        $old = ReviewCampaign::factory()->create(['course_date' => '2025-01-15']);
        $new = ReviewCampaign::factory()->create(['course_date' => '2026-03-10']);
        $this->review($old);
        $this->review($new);
        $this->review($new);

        $range = ['period' => 'custom', 'date_from' => '2026-01-01', 'date_to' => '2026-12-31'];
        $this->assertSame(2, $this->matching($range + ['date_basis' => 'course']));
        // Same range on submission date matches nothing (reviews were created "now").
        $this->assertSame(0, $this->matching(['period' => 'custom', 'date_from' => '2020-01-01', 'date_to' => '2020-12-31']));
    }

    public function test_campaign_selection(): void
    {
        $a = ReviewCampaign::factory()->create();
        $b = ReviewCampaign::factory()->create();
        $c = ReviewCampaign::factory()->create();
        $this->review($a);
        $this->review($b);
        $this->review($b);
        $this->review($c);

        $this->assertSame(4, $this->matching([]));
        $this->assertSame(2, $this->matching(['campaign_scope' => 'selected', 'campaign_ids' => [$b->id]]));
        $this->assertSame(3, $this->matching(['campaign_scope' => 'selected', 'campaign_ids' => [$a->id, $b->id]]));
        // Scope "all" ignores stray ids.
        $this->assertSame(4, $this->matching(['campaign_scope' => 'all', 'campaign_ids' => [$a->id]]));
    }

    public function test_review_and_campaign_filters(): void
    {
        $course   = ReviewCampaign::factory()->create(['activity_type' => 'course', 'instructor_name' => 'أحمد']);
        $workshop = ReviewCampaign::factory()->create(['activity_type' => 'workshop', 'instructor_name' => 'سارة']);
        $custom   = ReviewCampaign::factory()->inactive()->create(['activity_type' => 'ندوة', 'instructor_name' => 'أحمد']);
        $expired  = ReviewCampaign::factory()->expired()->create(['activity_type' => 'program']);

        $this->review($course, ['overall_rating' => 5, 'would_recommend' => true, 'difficulty_level' => 'سهل']);
        $this->review($course, ['overall_rating' => 2, 'would_recommend' => false, 'difficulty_level' => 'صعب']);
        $this->review($workshop, ['overall_rating' => 4, 'would_recommend' => true, 'difficulty_level' => 'متوسط']);
        $this->review($custom, ['overall_rating' => 5, 'would_recommend' => true, 'difficulty_level' => 'متوسط']);
        $this->review($expired, ['overall_rating' => 1, 'would_recommend' => false, 'difficulty_level' => 'صعب']);

        $this->assertSame(2, $this->matching(['ratings' => [5]]));
        $this->assertSame(3, $this->matching(['ratings' => [4, 5]]));
        $this->assertSame(3, $this->matching(['recommend' => 'yes']));
        $this->assertSame(2, $this->matching(['recommend' => 'no']));
        $this->assertSame(2, $this->matching(['difficulty' => ['صعب']]));
        $this->assertSame(1, $this->matching(['activity_types' => ['workshop']]));
        $this->assertSame(1, $this->matching(['activity_types' => ['ندوة']]));
        $this->assertSame(3, $this->matching(['instructors' => ['أحمد']]));
        $this->assertSame(3, $this->matching(['campaign_status' => 'active']));
        $this->assertSame(1, $this->matching(['campaign_status' => 'inactive']));
        $this->assertSame(1, $this->matching(['campaign_status' => 'expired']));
        // Combined filters.
        $this->assertSame(1, $this->matching(['instructors' => ['أحمد'], 'campaign_status' => 'active', 'recommend' => 'no']));
    }

    // ───────────────────────── Columns ─────────────────────────

    public function test_selected_columns_control_csv_output(): void
    {
        $campaign = ReviewCampaign::factory()->create(['course_name' => 'دورة Laravel', 'activity_type' => 'workshop']);
        $review   = $this->review($campaign, ['student_name' => 'محمد علي', 'overall_rating' => 5]);

        [, $csv] = $this->export(['columns' => ['overall_rating', 'course_name', 'activity_type', 'student_name']]);
        $rows = $this->csvRows($csv);

        // Registry order is kept regardless of the order sent.
        $this->assertSame(['الاسم الكامل', 'اسم الدورة / النشاط', 'نوع النشاط', 'التقييم العام'], $rows[0]);
        $this->assertSame(['محمد علي', 'دورة Laravel', 'ورشة عمل', '5'], $rows[1]);
        $this->assertCount(2, $rows);
        $this->assertStringNotContainsString($review->email, $csv);
    }

    // ───────────────────────── Statistics ─────────────────────────

    public function test_report_statistics_are_correct(): void
    {
        $a = ReviewCampaign::factory()->create(['course_name' => 'A']);
        $b = ReviewCampaign::factory()->create(['course_name' => 'B']);
        $this->review($a, ['overall_rating' => 5, 'course_content_rating' => 5, 'instructor_rating' => 4, 'organization_rating' => 3, 'would_recommend' => true, 'difficulty_level' => 'سهل']);
        $this->review($a, ['overall_rating' => 4, 'course_content_rating' => 3, 'instructor_rating' => 4, 'organization_rating' => 5, 'would_recommend' => true, 'difficulty_level' => 'متوسط']);
        $this->review($b, ['overall_rating' => 2, 'course_content_rating' => 1, 'instructor_rating' => 2, 'organization_rating' => 2, 'would_recommend' => false, 'difficulty_level' => 'متوسط']);

        $c = ExportCriteria::fromArray($this->base(['report_type' => 'summary']));
        $s = ReportStatistics::compute($c);

        $this->assertSame(3, $s['total']);
        $this->assertSame(2, $s['campaigns']);
        $this->assertEqualsWithDelta(3.67, $s['overall_avg'], 0.001);
        $this->assertEqualsWithDelta(3.0, $s['content_avg'], 0.001);
        $this->assertEqualsWithDelta(3.33, $s['instructor_avg'], 0.001);
        $this->assertEqualsWithDelta(3.33, $s['organization_avg'], 0.001);
        $this->assertSame(2, $s['recommend_yes']);
        $this->assertSame(67, $s['recommend_pct']);
        $this->assertSame([5 => 1, 4 => 1, 3 => 0, 2 => 1, 1 => 0], $s['stars']);
        $this->assertSame(['متوسط' => 2, 'سهل' => 1], $s['difficulty']);

        $campaigns = ReportStatistics::campaigns($c);
        $this->assertSame('A', $campaigns[0]['course_name']);
        $this->assertSame(2, $campaigns[0]['total']);
        $this->assertEqualsWithDelta(4.5, $campaigns[0]['overall_avg'], 0.001);
        $this->assertSame(100, $campaigns[0]['recommend_pct']);
        $this->assertSame(0, $campaigns[1]['recommend_pct']);
    }

    public function test_preview_returns_count_stats_and_sample(): void
    {
        $c = ReviewCampaign::factory()->create();
        $this->review($c, ['overall_rating' => 5]);
        $this->review($c, ['overall_rating' => 3]);

        $this->actingAs($this->admin)->postJson(route('admin.exports.preview'), $this->base())
            ->assertOk()
            ->assertJsonPath('count', 2)
            ->assertJsonPath('stats.overall_avg', 4)
            ->assertJsonPath('columns', ['الاسم الكامل', 'رقم التقييم', 'التقييم العام'])
            ->assertJsonCount(2, 'sample')
            ->assertJsonPath('will_queue', false);
    }

    // ───────────────────────── Formats ─────────────────────────

    public function test_xlsx_export_is_valid_rtl_and_formula_safe(): void
    {
        $c = ReviewCampaign::factory()->create(['course_name' => 'تحليل البيانات']);
        $this->review($c, ['student_name' => '=HYPERLINK("http://evil","x")', 'what_did_you_like' => '+cmd|calc']);

        [$log, $content] = $this->export([
            'format'  => 'xlsx',
            'columns' => ['student_name', 'course_name', 'overall_rating', 'what_did_you_like'],
        ]);

        $this->assertSame(1, $log->record_count);
        $this->assertStringStartsWith('PK', $content);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open(Storage::disk('local')->path($log->file_path)));
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('rightToLeft="true"', $sheet);
        $this->assertStringContainsString('تحليل البيانات', $sheet);
        $this->assertStringContainsString('=HYPERLINK', $sheet);
        $this->assertStringNotContainsString('<f>', $sheet);            // never a formula cell
        $this->assertNotFalse($zip->getFromName('xl/worksheets/sheet2.xml')); // info sheet
        $zip->close();
    }

    public function test_csv_export_neutralises_formula_injection(): void
    {
        $c = ReviewCampaign::factory()->create();
        $this->review($c, [
            'student_name'         => '=1+1',
            'what_did_you_like'    => '@SUM(A1)',
            'what_can_be_improved' => '-2+3',
            'additional_notes'     => 'عادي',
        ]);

        [, $csv] = $this->export(['columns' => ['student_name', 'what_did_you_like', 'what_can_be_improved', 'additional_notes', 'overall_rating']]);
        $row = $this->csvRows($csv)[1];

        $this->assertSame(["'=1+1", '4', "'@SUM(A1)", "'-2+3", 'عادي'], $row);
        $this->assertSame("'+x", CsvWriter::safe('+x'));
        $this->assertSame('-5', CsvWriter::safe(-5)); // real numbers untouched
    }

    public function test_pdf_reports_are_real_pdfs_with_embedded_arabic_font(): void
    {
        $c = ReviewCampaign::factory()->create(['course_name' => 'دورة إدارة المشاريع PMP']);
        $this->review($c, ['what_did_you_like' => 'شرح رائع للمفاهيم مع أمثلة Excel', 'additional_notes' => 'شكراً']);
        $this->review($c, ['overall_rating' => 2, 'would_recommend' => false]);

        foreach ([
            ['report_type' => 'detailed', 'columns' => ['student_name', 'email', 'course_name', 'overall_rating', 'what_did_you_like', 'submitted_at', 'course_date']],
            ['report_type' => 'summary', 'columns' => []],
            ['report_type' => 'professional', 'columns' => ['student_name', 'what_did_you_like', 'what_can_be_improved', 'additional_notes']],
        ] as $variant) {
            [$log, $pdf] = $this->export(array_merge(['format' => 'pdf'], $variant));

            $this->assertStringStartsWith('%PDF-', $pdf, $variant['report_type']);
            $this->assertStringContainsString('Cairo', $pdf, 'Arabic font must be embedded');
            $this->assertStringContainsString('DejaVuSans', $pdf, 'star glyph subset (EmcStars) must be embedded');
            $this->assertSame(2, $log->record_count);
            $this->assertGreaterThan(5000, $log->file_size);
        }
    }

    public function test_summary_exports_in_csv_and_xlsx(): void
    {
        $c = ReviewCampaign::factory()->create(['course_name' => 'دورة التسويق']);
        $this->review($c, ['overall_rating' => 5]);
        $this->review($c, ['overall_rating' => 3, 'would_recommend' => false]);

        [, $csv] = $this->export(['report_type' => 'summary', 'columns' => []]);
        $this->assertStringContainsString('"إجمالي التقييمات",2', $csv);
        $this->assertStringContainsString('"نسبة التوصية %",50', $csv);
        $this->assertStringContainsString('دورة التسويق', $csv);

        [$log] = $this->export(['report_type' => 'summary', 'format' => 'xlsx', 'columns' => []]);
        $zip = new ZipArchive();
        $zip->open(Storage::disk('local')->path($log->file_path));
        $workbook = $zip->getFromName('xl/workbook.xml');
        $this->assertStringContainsString('الملخص', $workbook);
        $this->assertStringContainsString('أداء الحملات', $workbook);
        $zip->close();
    }

    // ───────────────────────── Integrity & performance ─────────────────────────

    public function test_chunked_export_has_no_duplicates_or_gaps(): void
    {
        config(['exports.chunk_size' => 3]);
        $a = ReviewCampaign::factory()->create();
        $b = ReviewCampaign::factory()->create();
        $ids = [];
        foreach (range(1, 10) as $i) {
            $ids[] = $this->review($i % 2 ? $a : $b)->id;
        }

        [$log, $csv] = $this->export(['columns' => ['review_id']]);
        $exported = array_map(fn ($r) => (int) $r[0], array_slice($this->csvRows($csv), 1));

        $this->assertSame(10, $log->record_count);
        $this->assertSame($ids, $exported);
    }

    public function test_export_never_modifies_reviews(): void
    {
        $c = ReviewCampaign::factory()->create();
        $this->review($c, [], '2026-01-01 10:00:00');
        $before = DB::table('course_reviews')->get()->toArray();

        $this->export(['format' => 'xlsx']);
        $this->export(['report_type' => 'summary', 'columns' => []]);

        $this->assertEquals($before, DB::table('course_reviews')->get()->toArray());
    }

    public function test_large_exports_stream_in_chunks(): void
    {
        $campaign = ReviewCampaign::factory()->create();
        $now      = now()->toDateTimeString();
        $rows     = [];
        foreach (range(1, 6000) as $i) {
            $rows[] = [
                'review_campaign_id' => $campaign->id, 'student_name' => "طالب $i", 'email' => "s$i@x.test",
                'overall_rating' => $i % 5 + 1, 'course_content_rating' => 4, 'instructor_rating' => 4, 'organization_rating' => 4,
                'difficulty_level' => 'متوسط', 'what_did_you_like' => 'ممتاز', 'what_can_be_improved' => '—',
                'would_recommend' => $i % 3 ? 1 : 0, 'created_at' => $now, 'updated_at' => $now,
            ];
            if (count($rows) === 500) {
                DB::table('course_reviews')->insert($rows);
                $rows = [];
            }
        }

        $service  = app(ExportService::class);
        $criteria = ExportCriteria::fromArray($this->base(['format' => 'xlsx', 'columns' => array_keys(\App\Exports\ReviewExportColumns::COLUMNS)]));
        $path     = Storage::disk('local')->path('perf.xlsx');
        Storage::disk('local')->makeDirectory('');

        $memBefore = memory_get_usage(true);
        $start     = microtime(true);
        $count     = $service->generate($criteria, $path);
        $seconds   = microtime(true) - $start;

        $this->assertSame(6000, $count);
        $this->assertLessThan(60, $seconds);
        $this->assertLessThan(64 * 1024 * 1024, memory_get_usage(true) - $memBefore, 'memory should stay flat while streaming');
        fwrite(STDERR, sprintf("\n[perf] 6000 rows XLSX: %.2fs, file %d KB\n", $seconds, filesize($path) / 1024));
    }

    public function test_big_exports_are_queued_and_processed_by_the_job(): void
    {
        Queue::fake();
        config(['exports.sync_limit' => 2, 'exports.use_queue' => true]);
        $c = ReviewCampaign::factory()->create();
        foreach (range(1, 3) as $_) {
            $this->review($c);
        }

        $res = $this->actingAs($this->admin)->postJson(route('admin.exports.store'), $this->base())
            ->assertOk()->assertJsonPath('status', 'pending')->assertJsonPath('download_url', null);
        Queue::assertPushed(GenerateExportJob::class);

        $log = ExportLog::find($res->json('id'));
        (new GenerateExportJob($log->id))->handle(app(ExportService::class));

        $this->actingAs($this->admin)->getJson(route('admin.exports.status', $log))
            ->assertJsonPath('status', 'completed')->assertJsonPath('record_count', 3);
    }

    // ───────────────────────── History, regeneration, retention ─────────────────────────

    public function test_history_regeneration_and_retention(): void
    {
        $c = ReviewCampaign::factory()->create();
        $this->review($c);
        [$log] = $this->export(['period' => '30']);

        // Relative period is frozen to concrete dates in history.
        $this->assertSame('custom', $log->options['period']);
        $this->assertNotNull($log->date_from);
        $this->actingAs($this->admin)->get(route('admin.exports.index', ['tab' => 'history']))
            ->assertOk()->assertSee($log->name)->assertSee('مكتمل');

        // Retention: prune removes the file and blocks downloads.
        $log->update(['expires_at' => now()->subMinute()]);
        $this->artisan('exports:prune')->assertSuccessful();
        $log->refresh();
        $this->assertSame(ExportLog::STATUS_EXPIRED, $log->status);
        $this->assertNull($log->file_path);
        $this->actingAs($this->admin)->get(route('admin.exports.download', $log))->assertRedirect();

        // Regenerate builds a new file with the same options.
        $this->actingAs($this->admin)->post(route('admin.exports.regenerate', $log))->assertRedirect();
        $new = ExportLog::latest('id')->first();
        $this->assertNotSame($log->id, $new->id);
        $this->assertSame(ExportLog::STATUS_COMPLETED, $new->status);
        $this->assertEquals($log->options, $new->options);
        Storage::disk('local')->assertExists($new->file_path);
    }

    public function test_templates_can_be_saved_listed_and_deleted(): void
    {
        $this->actingAs($this->admin)->postJson(route('admin.exports.templates.store'), [
            'name'    => 'قالب شهري',
            'options' => $this->base(['columns' => ['overall_rating', 'what_did_you_like'], 'format' => 'xlsx']),
        ])->assertOk()->assertJsonPath('options.columns', ['overall_rating', 'what_did_you_like']);

        $template = ExportTemplate::firstOrFail();
        $this->assertSame($this->admin->id, $template->user_id);
        $this->actingAs($this->admin)->get(route('admin.exports.index'))->assertSee('قالب شهري');

        $other = User::factory()->create();
        $this->actingAs($other)->deleteJson(route('admin.exports.templates.destroy', $template))->assertForbidden();
        $this->actingAs($this->admin)->deleteJson(route('admin.exports.templates.destroy', $template))->assertOk();
        $this->assertDatabaseCount('export_templates', 0);
    }

    // ───────────────────────── Existing per-campaign exports ─────────────────────────

    public function test_campaign_quick_exports_still_work(): void
    {
        $c = ReviewCampaign::factory()->create(['course_name' => 'دورة قديمة']);
        $this->review($c, ['student_name' => '=evil()']);
        $this->review(ReviewCampaign::factory()->create()); // other campaign, must not leak

        $csv = $this->actingAs($this->admin)->get(route('admin.campaigns.export-csv', $c))->assertOk()->streamedContent();
        $rows = $this->csvRows($csv);
        $this->assertCount(2, $rows);
        $this->assertSame("'=evil()", $rows[1][0]);
        $this->assertSame('الاسم الكامل', $rows[0][0]);

        $pdf = $this->actingAs($this->admin)->get(route('admin.campaigns.exportPdf', $c))->assertOk()->streamedContent();
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString('Cairo', $pdf);

        $this->assertSame(2, ExportLog::where('user_id', $this->admin->id)->count());
    }

    public function test_campaign_activity_type_can_be_custom(): void
    {
        $this->actingAs($this->admin)->post(route('admin.campaigns.store'), [
            'course_name'          => 'ندوة الذكاء الاصطناعي',
            'activity_type'        => 'other',
            'activity_type_custom' => 'ندوة',
        ])->assertRedirect();
        $this->assertSame('ندوة', ReviewCampaign::firstOrFail()->activity_type);

        $this->actingAs($this->admin)->post(route('admin.campaigns.store'), [
            'course_name'   => 'ورشة',
            'activity_type' => 'workshop',
        ]);
        $this->assertSame('ورشة عمل', ReviewCampaign::where('course_name', 'ورشة')->first()->activityTypeLabel());
    }
}
