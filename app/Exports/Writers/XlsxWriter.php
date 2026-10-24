<?php

namespace App\Exports\Writers;

use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\EmptyCell;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\CellAlignment;
use OpenSpout\Common\Entity\Style\CellVerticalAlignment;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Streaming XLSX writer (OpenSpout) with RTL sheets and EMC styling.
 * Strings are always written as text cells, so a value such as "=cmd()"
 * can never become a formula (formula-injection safe).
 */
class XlsxWriter
{
    private Writer $writer;
    private bool $firstSheet = true;
    private Style $headerStyle;
    private Style $titleStyle;
    private Style $bodyStyle;

    public function __construct(string $path)
    {
        $tmp = config('exports.temp_path');
        if (!is_dir($tmp)) {
            mkdir($tmp, 0775, true);
        }

        $this->writer = new Writer(new Options(
            FALLBACK_STYLE: new Style(fontSize: 11, fontName: 'Arial'),
            tempFolder: $tmp,
        ));
        $this->writer->openToFile($path);

        $this->headerStyle = new Style(
            fontBold: true,
            fontColor: 'FFFFFF',
            fontName: 'Arial',
            cellAlignment: CellAlignment::CENTER,
            cellVerticalAlignment: CellVerticalAlignment::CENTER,
            shouldWrapText: true,
            backgroundColor: '22334A',
        );
        $this->titleStyle = new Style(fontBold: true, fontSize: 14, fontColor: '22334A', fontName: 'Arial');
        $this->bodyStyle  = new Style(fontName: 'Arial', cellVerticalAlignment: CellVerticalAlignment::TOP);
    }

    /** Start a new RTL worksheet. $widths are column widths (characters). */
    public function sheet(string $name, array $widths = [], int $freezeRow = 0): void
    {
        $sheet = $this->firstSheet
            ? $this->writer->getCurrentSheet()
            : $this->writer->addNewSheetAndMakeItCurrent();
        $this->firstSheet = false;

        $sheet->setName(mb_substr(preg_replace('/[\\\\\/?*\[\]:]/u', ' ', $name), 0, 31));
        $sheet->setSheetView(new SheetView(rightToLeft: true, freezeRow: $freezeRow));

        foreach (array_values($widths) as $i => $width) {
            $sheet->setColumnWidth((float) $width, $i + 1);
        }
    }

    public function title(string $text): void
    {
        $this->writer->addRow(new Row([new StringCell($text, $this->titleStyle)]));
    }

    public function header(array $labels): void
    {
        $this->writer->addRow(new Row(array_map(fn ($l) => new StringCell((string) $l, $this->headerStyle), $labels), 24));
    }

    public function row(array $values): void
    {
        $this->writer->addRow(new Row(array_map(fn ($v) => $this->cell($v), $values)));
    }

    public function blank(): void
    {
        $this->writer->addRow(new Row([]));
    }

    public function close(): void
    {
        $this->writer->close();
    }

    private function cell(mixed $value): Cell
    {
        if ($value === null || $value === '') {
            return new EmptyCell(null, $this->bodyStyle);
        }
        if (is_int($value) || is_float($value)) {
            return new NumericCell($value, $this->bodyStyle);
        }

        return new StringCell((string) $value, $this->bodyStyle);
    }
}
