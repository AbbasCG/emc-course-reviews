<?php

namespace App\Exports\Writers;

/**
 * UTF-8 (BOM) CSV writer that neutralises spreadsheet formula injection.
 */
class CsvWriter
{
    /** @var resource */
    private $handle;

    public function __construct(string $path)
    {
        $this->handle = fopen($path, 'wb');
        if ($this->handle === false) {
            throw new \RuntimeException('Cannot open export file for writing.');
        }
        fwrite($this->handle, "\xEF\xBB\xBF");
    }

    public function header(array $labels): void
    {
        $this->row($labels);
    }

    public function row(array $values): void
    {
        fputcsv($this->handle, array_map([self::class, 'safe'], $values), ',', '"', '');
    }

    public function blank(): void
    {
        fwrite($this->handle, "\n");
    }

    public function close(): void
    {
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }
    }

    /**
     * Prefix values that a spreadsheet would interpret as a formula
     * (=, +, -, @, tab, CR) with a single quote (OWASP recommendation).
     */
    public static function safe(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        $value = (string) $value;
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $value;
        }

        return $value;
    }
}
