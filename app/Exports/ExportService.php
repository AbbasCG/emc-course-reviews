<?php

namespace App\Exports;

use App\Exports\Writers\CsvWriter;
use App\Exports\Writers\PdfFactory;
use App\Exports\Writers\XlsxWriter;
use App\Jobs\GenerateExportJob;
use App\Models\ExportLog;
use App\Models\ReviewCampaign;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mpdf\HTMLParserMode;
use Throwable;

/**
 * Generates review exports (XLSX / CSV / PDF) into private storage and keeps
 * the ExportLog history up to date. Reviews are only ever read.
 */
class ExportService
{
    public function count(ExportCriteria $c): int
    {
        return ReviewExportQuery::build($c)->count();
    }

    public function shouldQueue(ExportCriteria $c, int $count): bool
    {
        if (!config('exports.use_queue')) {
            return false;
        }

        $limit = $c->format === 'pdf' && $c->reportType !== 'summary'
            ? config('exports.pdf_sync_limit')
            : config('exports.sync_limit');

        return $count > $limit;
    }

    /** Create a pending history entry; relative periods are frozen to dates. */
    public function createLog(User $user, ExportCriteria $c): ExportLog
    {
        $c = $c->withResolvedRange();

        return ExportLog::create([
            'user_id'     => $user->id,
            'name'        => $c->name ?? $c->defaultName(),
            'report_type' => $c->reportType,
            'format'      => $c->format,
            'status'      => ExportLog::STATUS_PENDING,
            'options'     => $c->toArray(),
            'date_from'   => $c->dateFrom,
            'date_to'     => $c->dateTo,
        ]);
    }

    /**
     * Create the log and either generate now or queue it.
     */
    public function start(User $user, ExportCriteria $c): ExportLog
    {
        $log = $this->createLog($user, $c);

        if ($this->shouldQueue($c, $this->count($c))) {
            GenerateExportJob::dispatch($log->id);

            return $log;
        }

        return $this->run($log);
    }

    public function run(ExportLog $log): ExportLog
    {
        $log->update([
            'status'     => ExportLog::STATUS_PROCESSING,
            'started_at' => now(),
            'error'      => null,
        ]);

        $disk = Storage::disk(config('exports.disk'));
        $path = config('exports.directory') . '/' . now()->format('Y/m') . '/' . Str::uuid() . '.' . $log->format;
        $disk->makeDirectory(dirname($path));
        $absolute = $disk->path($path);

        try {
            @set_time_limit(0);
            $count = $this->generate(ExportCriteria::fromArray($log->options), $absolute);

            $log->update([
                'status'       => ExportLog::STATUS_COMPLETED,
                'record_count' => $count,
                'file_path'    => $path,
                'file_size'    => filesize($absolute) ?: null,
                'completed_at' => now(),
                'expires_at'   => now()->addDays(config('exports.retention_days')),
            ]);
        } catch (Throwable $e) {
            if (is_file($absolute)) {
                @unlink($absolute);
            }
            report($e);
            $log->update([
                'status'       => ExportLog::STATUS_FAILED,
                'error'        => Str::limit($e->getMessage(), 500),
                'completed_at' => now(),
            ]);
        }

        return $log->refresh();
    }

    /** Write the file for the criteria to $path. Returns the number of reviews included. */
    public function generate(ExportCriteria $c, string $path): int
    {
        return match ($c->reportType) {
            'summary'      => $this->summary($c, $path),
            'professional' => $this->professionalPdf($c, $path),
            'campaign'     => $this->campaignPdf($c, $path),
            default        => $this->detailed($c, $path),
        };
    }

    /** Preview payload for the wizard (counts, key stats, first rows). */
    public function preview(ExportCriteria $c): array
    {
        $stats   = ReportStatistics::compute($c);
        $columns = $c->columns ?: ReviewExportColumns::keys();

        $sample = ReviewExportQuery::build($c)->with('campaign')->latest('id')->limit(5)->get()
            ->map(fn ($r) => ReviewExportColumns::row($r, $columns))->all();

        $warnings = [];
        if ($stats['total'] === 0) {
            $warnings[] = 'لا توجد تقييمات مطابقة للفلاتر المحددة.';
        }
        if ($c->format === 'pdf' && $c->reportType === 'detailed' && $stats['total'] > config('exports.pdf_max_rows')) {
            $warnings[] = 'عدد السجلات يتجاوز الحد الأقصى لملف PDF التفصيلي (' . config('exports.pdf_max_rows') . '). استخدم Excel أو CSV.';
        }

        return [
            'count'      => $stats['total'],
            'stats'      => $stats,
            'columns'    => array_map(fn ($k) => ReviewExportColumns::label($k), $columns),
            'sample'     => $sample,
            'will_queue' => $this->shouldQueue($c, $stats['total']),
            'warnings'   => $warnings,
        ];
    }

    // ───────────────────────── Detailed ─────────────────────────

    private function detailed(ExportCriteria $c, string $path): int
    {
        $columns = $c->columns ?: ReviewExportColumns::keys();
        $labels  = array_map(fn ($k) => ReviewExportColumns::label($k), $columns);

        if ($c->format === 'pdf') {
            return $this->detailedPdf($c, $columns, $labels, $path);
        }

        $writer = $c->format === 'csv' ? new CsvWriter($path) : new XlsxWriter($path);
        if ($writer instanceof XlsxWriter) {
            $writer->sheet('التقييمات', array_map(fn ($k) => ReviewExportColumns::width($k), $columns), 1);
        }
        $writer->header($labels);

        $count = 0;
        ReviewExportQuery::build($c)->with('campaign')->chunkById(
            config('exports.chunk_size'),
            function ($reviews) use ($writer, $columns, &$count) {
                foreach ($reviews as $review) {
                    $writer->row(ReviewExportColumns::row($review, $columns));
                    $count++;
                }
            }
        );

        if ($writer instanceof XlsxWriter) {
            $this->xlsxInfoSheet($writer, $c, $count);
        }
        $writer->close();

        return $count;
    }

    private function detailedPdf(ExportCriteria $c, array $columns, array $labels, string $path): int
    {
        $total = $this->count($c);
        if ($total > config('exports.pdf_max_rows')) {
            throw new \RuntimeException('عدد السجلات (' . $total . ') يتجاوز الحد الأقصى لملف PDF التفصيلي. استخدم Excel أو CSV.');
        }

        $mpdf = PdfFactory::make(count($columns) > 6 ? 'L' : 'P');
        $this->pdfDocument($mpdf, $c, 'تصدير تفصيلي للتقييمات');
        $mpdf->WriteHTML(view('exports.pdf.detailed-head', [
            'criteria' => $c,
            'total'    => $total,
            'filters'  => $this->filterSummary($c),
        ])->render(), HTMLParserMode::HTML_BODY);

        // Each chunk becomes its own table (repeated header) to keep memory flat.
        $count = 0;
        ReviewExportQuery::build($c)->with('campaign')->chunkById(
            config('exports.chunk_size'),
            function ($reviews) use ($mpdf, $columns, $labels, &$count) {
                $rows = $reviews->map(fn ($r) => ReviewExportColumns::row($r, $columns))->all();
                $mpdf->WriteHTML(view('exports.pdf.detailed-rows', compact('columns', 'labels', 'rows'))->render(), HTMLParserMode::HTML_BODY);
                $count += count($rows);
            }
        );

        if ($count === 0) {
            $mpdf->WriteHTML('<div class="empty">لا توجد تقييمات مطابقة للفلاتر المحددة.</div>', HTMLParserMode::HTML_BODY);
        }

        $mpdf->Output($path, 'F');

        return $count;
    }

    // ───────────────────────── Summary ─────────────────────────

    private function summary(ExportCriteria $c, string $path): int
    {
        $stats     = ReportStatistics::compute($c);
        $campaigns = ReportStatistics::campaigns($c);
        $filters   = $this->filterSummary($c);

        if ($c->format === 'pdf') {
            $mpdf = PdfFactory::make();
            $this->pdfDocument($mpdf, $c, 'تقرير ملخص التقييمات');
            $mpdf->WriteHTML(view('exports.pdf.summary', compact('c', 'stats', 'campaigns', 'filters'))->render(), HTMLParserMode::HTML_BODY);
            $mpdf->Output($path, 'F');

            return $stats['total'];
        }

        $kpis = [
            ['إجمالي التقييمات', $stats['total']],
            ['عدد الحملات', $stats['campaigns']],
            ['متوسط التقييم العام', $stats['overall_avg']],
            ['متوسط تقييم المحتوى', $stats['content_avg']],
            ['متوسط تقييم المدرب', $stats['instructor_avg']],
            ['متوسط تقييم التنظيم', $stats['organization_avg']],
            ['نسبة التوصية %', $stats['recommend_pct']],
            ['يوصون', $stats['recommend_yes']],
            ['لا يوصون', $stats['recommend_no']],
        ];
        $starRows = [];
        foreach ($stats['stars'] as $star => $n) {
            $starRows[] = [$star . ' نجوم', $n, $stats['total'] ? round($n / $stats['total'] * 100, 1) : 0];
        }
        $diffRows = [];
        foreach ($stats['difficulty'] as $level => $n) {
            $diffRows[] = [(string) $level, $n, $stats['total'] ? round($n / $stats['total'] * 100, 1) : 0];
        }
        $campaignHeader = ['رقم الحملة', 'اسم الدورة / النشاط', 'نوع النشاط', 'المدرب', 'الحالة', 'عدد التقييمات',
                           'التقييم العام', 'المحتوى', 'المدرب', 'التنظيم', 'نسبة التوصية %'];
        $campaignRows = array_map(fn ($r) => [
            $r['id'], $r['course_name'], $r['activity_type'], $r['instructor_name'], $r['status'], $r['total'],
            $r['overall_avg'], $r['content_avg'], $r['instructor_avg'], $r['organization_avg'], $r['recommend_pct'],
        ], $campaigns);

        if ($c->format === 'csv') {
            $w = new CsvWriter($path);
            $w->row(['تقرير ملخص التقييمات — EMC']);
            foreach ($filters as [$label, $value]) {
                $w->row([$label, $value]);
            }
            $w->blank();
            $w->row(['المؤشر', 'القيمة']);
            foreach ($kpis as $k) {
                $w->row($k);
            }
            $w->blank();
            $w->row(['توزيع النجوم', 'العدد', 'النسبة %']);
            foreach ($starRows as $r) {
                $w->row($r);
            }
            $w->blank();
            $w->row(['مستوى الصعوبة', 'العدد', 'النسبة %']);
            foreach ($diffRows as $r) {
                $w->row($r);
            }
            $w->blank();
            $w->row($campaignHeader);
            foreach ($campaignRows as $r) {
                $w->row($r);
            }
            $w->close();

            return $stats['total'];
        }

        $w = new XlsxWriter($path);
        $w->sheet('الملخص', [30, 18]);
        $w->title('تقرير ملخص التقييمات — EMC');
        foreach ($filters as [$label, $value]) {
            $w->row([$label, $value]);
        }
        $w->blank();
        $w->header(['المؤشر', 'القيمة']);
        foreach ($kpis as $k) {
            $w->row($k);
        }

        $w->sheet('توزيع النجوم', [16, 12, 12], 1);
        $w->header(['التقييم', 'العدد', 'النسبة %']);
        foreach ($starRows as $r) {
            $w->row($r);
        }
        $w->blank();
        $w->header(['مستوى الصعوبة', 'العدد', 'النسبة %']);
        foreach ($diffRows as $r) {
            $w->row($r);
        }

        $w->sheet('أداء الحملات', [10, 32, 14, 20, 12, 12, 12, 10, 10, 10, 14], 1);
        $w->header($campaignHeader);
        foreach ($campaignRows as $r) {
            $w->row($r);
        }
        $w->close();

        return $stats['total'];
    }

    // ───────────────────────── Professional PDF ─────────────────────────

    private function professionalPdf(ExportCriteria $c, string $path): int
    {
        $stats     = ReportStatistics::compute($c);
        $campaigns = ReportStatistics::campaigns($c);
        $filters   = $this->filterSummary($c);
        $limit     = config('exports.pdf_comment_limit');

        $textColumns = array_values(array_intersect(
            ['what_did_you_like', 'what_can_be_improved', 'additional_notes'],
            $c->columns
        ));

        $comments = collect();
        if ($textColumns) {
            $comments = ReviewExportQuery::build($c)->with('campaign')->latest('id')->limit($limit)->get();
        }

        $mpdf = PdfFactory::make();
        $this->pdfDocument($mpdf, $c, 'التقرير الرسمي لتقييمات EMC');
        $mpdf->WriteHTML(view('exports.pdf.professional', [
            'c'           => $c,
            'stats'       => $stats,
            'campaigns'   => $campaigns,
            'filters'     => $filters,
            'executive'   => $this->executiveSummary($stats, $campaigns),
            'comments'    => $comments,
            'textColumns' => $textColumns,
            'limit'       => $limit,
        ])->render(), HTMLParserMode::HTML_BODY);
        $mpdf->Output($path, 'F');

        return $stats['total'];
    }

    // ───────────────────────── Single-campaign PDF (legacy button) ─────────────────────────

    private function campaignPdf(ExportCriteria $c, string $path): int
    {
        $campaign = ReviewCampaign::findOrFail($c->campaignIds[0] ?? 0);
        $reviews  = ReviewExportQuery::build($c)->latest()->get();
        $s        = ReportStatistics::compute($c);

        $stats = [
            'total_reviews'        => $s['total'],
            'overall_avg'          => round($s['overall_avg'], 1),
            'content_avg'          => round($s['content_avg'], 1),
            'instructor_avg'       => round($s['instructor_avg'], 1),
            'organization_avg'     => round($s['organization_avg'], 1),
            'recommend_percentage' => $s['recommend_pct'],
            'recommend_yes'        => $s['recommend_yes'],
            'recommend_no'         => $s['recommend_no'],
            'difficulty_data'      => collect($s['difficulty']),
        ];
        $showPii = $c->includesPii();

        $mpdf = PdfFactory::make('P', ['margin_bottom' => 10]);
        $mpdf->SetTitle('تقرير تقييمات — ' . $campaign->course_name);
        $mpdf->WriteHTML(view('admin.campaigns.pdf-report', compact('campaign', 'reviews', 'stats', 'showPii'))->render());
        $mpdf->Output($path, 'F');

        return $s['total'];
    }

    // ───────────────────────── Helpers ─────────────────────────

    private function pdfDocument(\Mpdf\Mpdf $mpdf, ExportCriteria $c, string $title): void
    {
        $mpdf->SetTitle($title);
        $mpdf->WriteHTML(view('exports.pdf.styles')->render(), HTMLParserMode::HEADER_CSS);
        $mpdf->SetHTMLFooter(view('exports.pdf.footer')->render());
    }

    private function xlsxInfoSheet(XlsxWriter $w, ExportCriteria $c, int $count): void
    {
        $w->sheet('معلومات التقرير', [28, 50]);
        $w->title('EMC — ' . ($c->name ?? $c->defaultName()));
        $w->row(['عدد السجلات', $count]);
        foreach ($this->filterSummary($c) as [$label, $value]) {
            $w->row([$label, $value]);
        }
    }

    /** Human readable list of the applied filters: [[label, value], ...]. */
    public function filterSummary(ExportCriteria $c): array
    {
        [$from, $to] = $c->range();

        $campaigns = $c->campaignIds
            ? ReviewCampaign::whereIn('id', $c->campaignIds)->pluck('course_name')->implode('، ')
            : 'جميع الحملات';

        $rows = [
            ['تاريخ التصدير', now()->format('Y-m-d H:i')],
            ['الحملات', Str::limit($campaigns, 300)],
            ['الفترة', $from || $to
                ? 'من ' . ($from?->format('Y-m-d') ?? 'البداية') . ' إلى ' . ($to?->format('Y-m-d') ?? 'اليوم')
                : 'كل الفترات'],
            ['أساس التاريخ', $c->dateBasis === 'course' ? 'تاريخ الدورة' : 'تاريخ إرسال التقييم'],
        ];

        if ($c->activityTypes) {
            $rows[] = ['نوع النشاط', implode('، ', array_map([ReviewCampaign::class, 'activityTypeLabelFor'], $c->activityTypes))];
        }
        if ($c->instructors) {
            $rows[] = ['المدرب', implode('، ', $c->instructors)];
        }
        if ($c->difficulties) {
            $rows[] = ['مستوى الصعوبة', implode('، ', $c->difficulties)];
        }
        if ($c->ratings) {
            $rows[] = ['التقييم العام (نجوم)', implode('، ', $c->ratings)];
        }
        if ($c->recommend !== 'any') {
            $rows[] = ['التوصية', $c->recommend === 'yes' ? 'يوصي' : 'لا يوصي'];
        }
        if ($c->campaignStatus !== 'any') {
            $rows[] = ['حالة الحملة', ReviewCampaign::STATUSES[$c->campaignStatus] ?? $c->campaignStatus];
        }

        return $rows;
    }

    /** Auto-generated executive summary paragraphs (HTML-safe, for the PDF view). */
    private function executiveSummary(array $s, array $campaigns): array
    {
        if ($s['total'] === 0) {
            return ['لا توجد تقييمات مطابقة للفلاتر المحددة في هذا التقرير.'];
        }

        $level = match (true) {
            $s['overall_avg'] >= 4.5 => 'ممتاز',
            $s['overall_avg'] >= 4   => 'جيد جداً',
            $s['overall_avg'] >= 3   => 'جيد',
            default                  => 'يحتاج إلى تحسين',
        };

        $lines = [
            "يغطي هذا التقرير {$s['total']} تقييماً من {$s['campaigns']} حملة. بلغ متوسط التقييم العام {$s['overall_avg']} من 5 بمستوى رضا {$level}، ويوصي " . PdfFactory::ltr($s['recommend_pct'] . '%') . " من المشاركين بالنشاط.",
        ];

        $aspects = [
            'المحتوى' => $s['content_avg'],
            'المدرب'  => $s['instructor_avg'],
            'التنظيم' => $s['organization_avg'],
        ];
        arsort($aspects);
        $best  = array_key_first($aspects);
        $worst = array_key_last($aspects);
        if ($best !== $worst) {
            $lines[] = "أقوى المحاور تقييماً هو {$best} ({$aspects[$best]})، بينما يأتي {$worst} في المرتبة الأخيرة ({$aspects[$worst]}).";
        }

        if (count($campaigns) > 1) {
            $top    = $campaigns[0];
            $bottom = $campaigns[count($campaigns) - 1];
            $lines[] = "الحملة الأعلى تقييماً: «" . e($top['course_name']) . "» بمتوسط {$top['overall_avg']}، والأقل: «" . e($bottom['course_name']) . "» بمتوسط {$bottom['overall_avg']}.";
        }

        return $lines;
    }
}
