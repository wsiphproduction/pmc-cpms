<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Builds the detailed weekly-report import workbook: one row per report, with
 * every field the submission form captures. Checklist status cells get a real
 * dropdown so the symbols can't be mistyped.
 *
 * Column layout is derived from config('psr') and mirrored by the importer,
 * which discovers the repeating groups from the header row.
 */
class PsrTemplateWriter
{
    /** Columns that always lead the sheet. */
    private const BASE = ['week_code', 'ntp_no', 'completion_pct', 'submitted_date', 'progress_updates'];

    /** @return string Raw .xlsx bytes. */
    public function build(): string
    {
        $items    = collect(config('psr.checklist'))->reject(fn ($c) => $c['section'] ?? false)->values();
        $rows     = (int) config('psr.issue_rows');
        $statuses = (array) config('psr.statuses');

        $header = self::BASE;
        foreach ($items as $item) {
            $key = str_replace('.', '_', $item['seq']);
            $header[] = "chk_{$key}_status";
            $header[] = "chk_{$key}_remarks";
        }
        for ($i = 1; $i <= $rows; $i++) {
            $header[] = "issue_{$i}";
            $header[] = "action_{$i}";
            $header[] = "commitment_date_{$i}";
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Weekly Reports');

        $sheet->fromArray($header, null, 'A1');
        $sheet->fromArray($this->sampleRow($items, $rows, $statuses), null, 'A2');

        $lastCol = $sheet->getHighestColumn();

        // Header styling; freeze it so the columns stay visible while filling rows.
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF00']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->freezePane('A2');
        $sheet->getRowDimension(1)->setRowHeight(30);

        // Dropdowns and formats applied down a generous range so pasted rows keep them.
        $lastRow = 500;
        foreach ($header as $i => $name) {
            $col = $sheet->getCell([$i + 1, 1])->getColumn();
            $sheet->getColumnDimension($col)->setWidth(match (true) {
                str_ends_with($name, '_remarks'), str_starts_with($name, 'issue_'),
                str_starts_with($name, 'action_'), $name === 'progress_updates' => 34,
                str_ends_with($name, '_status')                                  => 13,
                default                                                          => 17,
            });

            if (str_ends_with($name, '_status')) {
                $this->addStatusDropdown($sheet, $col, $lastRow, $statuses);
                $sheet->getStyle("{$col}2:{$col}{$lastRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }

            if ($name === 'submitted_date' || str_starts_with($name, 'commitment_date_')) {
                // Pin the display format so exported dates come back unambiguous.
                $sheet->getStyle("{$col}2:{$col}{$lastRow}")
                    ->getNumberFormat()->setFormatCode('yyyy-mm-dd');
            }
        }

        // Reference sheet: which checklist item each chk_* column belongs to.
        $guide = $spreadsheet->createSheet();
        $guide->setTitle('Checklist Guide');
        $guide->fromArray(['Seq', 'Column prefix', 'Item / Requirement Description'], null, 'A1');
        $guide->getStyle('A1:C1')->getFont()->setBold(true);
        foreach ($items as $n => $item) {
            $guide->fromArray([
                $item['seq'],
                'chk_' . str_replace('.', '_', $item['seq']),
                $item['label'],
            ], null, 'A' . ($n + 2));
        }
        $guide->fromArray([
            [],
            ['Status values', implode('  ', $statuses), 'Pick from the dropdown; leave blank if not assessed.'],
        ], null, 'A' . ($items->count() + 2));
        $guide->getColumnDimension('A')->setWidth(10);
        $guide->getColumnDimension('B')->setWidth(18);
        $guide->getColumnDimension('C')->setWidth(70);

        $spreadsheet->setActiveSheetIndex(0);

        // PhpSpreadsheet writes to a stream/path only, so buffer the output.
        $temp = tempnam(sys_get_temp_dir(), 'psr');
        (new Xlsx($spreadsheet))->save($temp);
        $bytes = (string) file_get_contents($temp);
        @unlink($temp);
        $spreadsheet->disconnectWorksheets();

        return $bytes;
    }

    private function addStatusDropdown($sheet, string $col, int $lastRow, array $statuses): void
    {
        for ($row = 2; $row <= $lastRow; $row++) {
            $validation = $sheet->getCell("{$col}{$row}")->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_STOP);
            $validation->setAllowBlank(true);
            $validation->setShowDropDown(true);
            $validation->setShowErrorMessage(true);
            $validation->setErrorTitle('Invalid status');
            $validation->setError('Pick a value from the list, or leave the cell blank.');
            $validation->setFormula1('"' . implode(',', $statuses) . '"');
        }
    }

    /** A worked example row so the expected shape is obvious. */
    private function sampleRow($items, int $issueRows, array $statuses): array
    {
        [$done, $notDone] = [$statuses[0] ?? '√', $statuses[1] ?? '✕'];

        $row = ['W1-OCT', '', 25, '2026-10-07', "Foundation works started; formworks scheduled next week."];
        foreach ($items as $n => $item) {
            $row[] = $n === 0 ? $notDone : $done;
            $row[] = $n === 0 ? 'Perimeter fence pending' : '';
        }

        $examples = [
            ['Delayed delivery of materials', 'Supplier follow-up and alternate source', '2026-10-14'],
            ['Night works permit not yet released', 'Coordinate with LGU', '2026-10-12'],
        ];
        for ($i = 0; $i < $issueRows; $i++) {
            array_push($row, ...($examples[$i] ?? ['', '', '']));
        }

        return $row;
    }
}
