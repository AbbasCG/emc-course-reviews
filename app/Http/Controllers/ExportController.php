<?php

namespace App\Http\Controllers;

use App\Exports\ExportCriteria;
use App\Exports\ExportService;
use App\Exports\ReviewExportColumns;
use App\Http\Requests\ExportRequest;
use App\Models\CourseReview;
use App\Models\ExportLog;
use App\Models\ExportTemplate;
use App\Models\ReviewCampaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ExportController extends Controller
{
    public function __construct(private ExportService $service) {}

    public function index(Request $request)
    {
        $campaigns = ReviewCampaign::withCount('reviews')->latest()->get()->map(fn ($c) => [
            'id'         => $c->id,
            'name'       => $c->course_name,
            'instructor' => $c->instructor_name,
            'type'       => $c->activityTypeLabel(),
            'status'     => $c->statusLabel(),
            'reviews'    => $c->reviews_count,
        ]);

        $activityTypes = collect(ReviewCampaign::ACTIVITY_TYPES)
            ->map(fn ($label, $key) => ['value' => $key, 'label' => $label])
            ->values()
            ->merge(
                ReviewCampaign::query()->whereNotIn('activity_type', array_keys(ReviewCampaign::ACTIVITY_TYPES))
                    ->distinct()->orderBy('activity_type')->pluck('activity_type')
                    ->map(fn ($t) => ['value' => $t, 'label' => $t])
            )->values();

        $instructors = ReviewCampaign::query()->whereNotNull('instructor_name')->where('instructor_name', '!=', '')
            ->distinct()->orderBy('instructor_name')->pluck('instructor_name');

        $difficulties = collect(['سهل', 'متوسط', 'صعب'])
            ->merge(CourseReview::query()->distinct()->pluck('difficulty_level'))
            ->filter()->unique()->values();

        $templates = ExportTemplate::where('user_id', $request->user()->id)->latest()->get(['id', 'name', 'options']);

        $history = ExportLog::where('user_id', $request->user()->id)
            ->latest()->paginate(10, ['*'], 'history_page')->withQueryString();

        $preselect = $request->integer('campaign') ?: null;

        return view('admin.exports.index', [
            'campaigns'     => $campaigns,
            'activityTypes' => $activityTypes,
            'instructors'   => $instructors,
            'difficulties'  => $difficulties,
            'columns'       => ReviewExportColumns::forUi(),
            'columnGroups'  => ReviewExportColumns::GROUPS,
            'templates'     => $templates,
            'history'       => $history,
            'canExportPii'  => Gate::allows('export-personal-data'),
            'preselect'     => $preselect,
            'retentionDays' => config('exports.retention_days'),
        ]);
    }

    public function preview(ExportRequest $request): JsonResponse
    {
        return response()->json($this->service->preview($request->criteria()));
    }

    public function store(ExportRequest $request): JsonResponse
    {
        $log = $this->service->start($request->user(), $request->criteria());

        return response()->json($this->payload($log), $log->status === ExportLog::STATUS_FAILED ? 500 : 200);
    }

    public function status(Request $request, ExportLog $export): JsonResponse
    {
        $this->authorizeOwner($request, $export);

        return response()->json($this->payload($export));
    }

    public function download(Request $request, ExportLog $export)
    {
        $this->authorizeOwner($request, $export);

        if (!$export->isDownloadable()) {
            return redirect()->route('admin.exports.index', ['tab' => 'history'])
                ->with('error', 'الملف غير متاح للتنزيل (منتهي الصلاحية أو غير مكتمل). يمكنك إعادة إنشائه من السجل.');
        }

        return Storage::disk(config('exports.disk'))->download($export->file_path, $export->downloadName(), [
            'Cache-Control' => 'no-store, private',
        ]);
    }

    public function regenerate(Request $request, ExportLog $export)
    {
        $this->authorizeOwner($request, $export);

        $criteria = ExportCriteria::fromArray($export->options);
        if ($criteria->includesPii() && Gate::denies('export-personal-data')) {
            abort(403, 'ليست لديك صلاحية تصدير البيانات الشخصية.');
        }

        $log = $this->service->start($request->user(), $criteria);

        if ($request->expectsJson()) {
            return response()->json($this->payload($log), $log->status === ExportLog::STATUS_FAILED ? 500 : 200);
        }

        $message = match ($log->status) {
            ExportLog::STATUS_COMPLETED => 'تمت إعادة إنشاء التقرير بنجاح ويمكنك تنزيله الآن.',
            ExportLog::STATUS_FAILED    => 'فشلت إعادة إنشاء التقرير: ' . $log->error,
            default                     => 'تمت جدولة إعادة إنشاء التقرير، وسيظهر في السجل عند اكتماله.',
        };

        return redirect()->route('admin.exports.index', ['tab' => 'history'])
            ->with($log->status === ExportLog::STATUS_FAILED ? 'error' : 'success', $message);
    }

    public function storeTemplate(Request $request): JsonResponse
    {
        Gate::authorize('export-reviews');

        $data = $request->validate([
            'name'      => ['required', 'string', 'max:120'],
            'options'   => ['required', 'array'],
            'options.columns'   => ['nullable', 'array'],
            'options.columns.*' => ['string', Rule::in(ReviewExportColumns::keys())],
        ], [
            'name.required' => 'اسم القالب مطلوب.',
            'name.max'      => 'اسم القالب يجب ألا يتجاوز 120 حرفاً.',
        ]);

        // Persist only known option keys.
        $options = ExportCriteria::fromArray($data['options'])->toArray();
        unset($options['name']);

        $template = ExportTemplate::updateOrCreate(
            ['user_id' => $request->user()->id, 'name' => $data['name']],
            ['options' => $options]
        );

        return response()->json($template->only(['id', 'name', 'options']));
    }

    public function destroyTemplate(Request $request, ExportTemplate $template): JsonResponse
    {
        abort_unless($template->user_id === $request->user()->id, 403);
        $template->delete();

        return response()->json(['deleted' => true]);
    }

    private function authorizeOwner(Request $request, ExportLog $export): void
    {
        abort_unless($export->user_id === $request->user()->id, 403);
    }

    private function payload(ExportLog $log): array
    {
        return [
            'id'           => $log->id,
            'name'         => $log->name,
            'status'       => $log->status,
            'status_label' => $log->statusBadge()['label'],
            'record_count' => $log->record_count,
            'file_size'    => $log->file_size,
            'error'        => $log->status === ExportLog::STATUS_FAILED ? $log->error : null,
            'download_url' => $log->status === ExportLog::STATUS_COMPLETED ? route('admin.exports.download', $log) : null,
            'status_url'   => route('admin.exports.status', $log),
        ];
    }
}
