<?php

namespace App\Exports;

use Illuminate\Support\Carbon;

/**
 * Immutable description of what to export (scope, filters, columns, format).
 * Built from validated request data and stored on ExportLog::options so a
 * report can be regenerated exactly.
 */
final class ExportCriteria
{
    public function __construct(
        public readonly string $reportType = 'detailed',
        public readonly string $format = 'xlsx',
        public readonly array $campaignIds = [],
        public readonly string $period = 'all',
        public readonly ?string $dateFrom = null,
        public readonly ?string $dateTo = null,
        public readonly string $dateBasis = 'submitted',
        public readonly array $activityTypes = [],
        public readonly array $instructors = [],
        public readonly array $difficulties = [],
        public readonly array $ratings = [],
        public readonly string $recommend = 'any',
        public readonly string $campaignStatus = 'any',
        public readonly array $columns = [],
        public readonly ?string $name = null,
    ) {}

    public static function fromArray(array $data): self
    {
        $ids = ($data['campaign_scope'] ?? 'all') === 'selected'
            ? array_values(array_unique(array_map('intval', $data['campaign_ids'] ?? [])))
            : [];

        return new self(
            reportType:     $data['report_type'] ?? 'detailed',
            format:         $data['format'] ?? 'xlsx',
            campaignIds:    $ids,
            period:         (string) ($data['period'] ?? 'all'),
            dateFrom:       $data['date_from'] ?? null,
            dateTo:         $data['date_to'] ?? null,
            dateBasis:      $data['date_basis'] ?? 'submitted',
            activityTypes:  array_values(array_filter($data['activity_types'] ?? [], 'strlen')),
            instructors:    array_values(array_filter($data['instructors'] ?? [], 'strlen')),
            difficulties:   array_values(array_filter($data['difficulty'] ?? [], 'strlen')),
            ratings:        array_values(array_map('intval', $data['ratings'] ?? [])),
            recommend:      $data['recommend'] ?? 'any',
            campaignStatus: $data['campaign_status'] ?? 'any',
            columns:        ReviewExportColumns::normalize($data['columns'] ?? []),
            name:           isset($data['name']) && trim((string) $data['name']) !== '' ? trim($data['name']) : null,
        );
    }

    /** Serializable form (same keys as the request payload). */
    public function toArray(): array
    {
        return [
            'report_type'     => $this->reportType,
            'format'          => $this->format,
            'campaign_scope'  => $this->campaignIds ? 'selected' : 'all',
            'campaign_ids'    => $this->campaignIds,
            'period'          => $this->period,
            'date_from'       => $this->dateFrom,
            'date_to'         => $this->dateTo,
            'date_basis'      => $this->dateBasis,
            'activity_types'  => $this->activityTypes,
            'instructors'     => $this->instructors,
            'difficulty'      => $this->difficulties,
            'ratings'         => $this->ratings,
            'recommend'       => $this->recommend,
            'campaign_status' => $this->campaignStatus,
            'columns'         => $this->columns,
            'name'            => $this->name,
        ];
    }

    /**
     * Resolved [from, to] day range, or [null, null] for "all time".
     * Relative periods are resolved against $now.
     *
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    public function range(?Carbon $now = null): array
    {
        $now ??= now();

        return match ($this->period) {
            '7', '30', '90' => [$now->copy()->subDays((int) $this->period - 1)->startOfDay(), $now->copy()->endOfDay()],
            'custom'        => [
                $this->dateFrom ? Carbon::parse($this->dateFrom)->startOfDay() : null,
                $this->dateTo ? Carbon::parse($this->dateTo)->endOfDay() : null,
            ],
            default         => [null, null],
        };
    }

    /** Copy with the relative period frozen to concrete dates (for history/regeneration). */
    public function withResolvedRange(?Carbon $now = null): self
    {
        [$from, $to] = $this->range($now);
        if (!$from && !$to) {
            return $this;
        }

        $data = $this->toArray();
        $data['period']    = 'custom';
        $data['date_from'] = $from?->toDateString();
        $data['date_to']   = $to?->toDateString();

        return self::fromArray($data);
    }

    public function with(array $overrides): self
    {
        return self::fromArray(array_merge($this->toArray(), $overrides));
    }

    public function includesPii(): bool
    {
        return (bool) array_intersect($this->columns, ReviewExportColumns::piiKeys());
    }

    public function hasColumn(string $key): bool
    {
        return in_array($key, $this->columns, true);
    }

    public function defaultName(): string
    {
        $type = \App\Models\ExportLog::REPORT_TYPES[$this->reportType] ?? 'تقرير';

        // Slashes keep the date in LTR order after Arabic text (hyphens get reversed by bidi).
        return $type . ' — ' . now()->format('Y/m/d');
    }
}
