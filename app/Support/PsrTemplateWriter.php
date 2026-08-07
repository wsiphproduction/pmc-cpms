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
 *
 * Passing a project list switches the workbook into cross-project mode (the
 * Weekly Status module): a leading `project_no` column says which project each
 * row belongs to, so one file can cover a whole week's worth of projects,
 * sub-projects and NTPs.
 */
class PsrTemplateWriter
{
    /** Columns that always lead the sheet. */
    private const BASE = ['week_code', 'ntp_no', 'completion_pct', 'submitted_date', 'progress_updates'];

    private const PROJECTS_SHEET = 'My Projects';

    /**
     * @param  array<int, array{project_no: string, title: string, ntps?: string}>  $projects
     *         When given, a `project_no` column leads the sheet and is backed by
     *         a dropdown of these project numbers.
     * @return string Raw .xlsx bytes.
     */
    public function build(array $projects = []): string
    {
        $items    = collect(config('psr.checklist'))->reject(fn ($c) => $c['section'] ?? false)->values();
        $rows     = (int) config('psr.issue_rows');
        $statuses = (array) config('psr.statuses');

        $header = $projects ? ['project_no', ...self::BASE] : self::BASE;
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
        $sheet->fromArray($this->sampleRow($items, $rows, $statuses, $projects), null, 'A2');

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
                $name === 'project_no'                                           => 20,
                default                                                          => 17,
            });

            if (str_ends_with($name, '_status')) {
                $this->addDropdown($sheet, $col, $lastRow, '"' . implode(',', $statuses) . '"',
                    'Invalid status', 'Pick a value from the list, or leave the cell blank.');
                $sheet->getStyle("{$col}2:{$col}{$lastRow}")
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }

            if ($name === 'project_no' && $projects) {
                // Cross-sheet range, so the list grows with the engineer's projects.
                $range = "'" . self::PROJECTS_SHEET . "'!\$A\$2:\$A\$" . (count($projects) + 1);
                $this->addDropdown($sheet, $col, $lastRow, $range,
                    'Unknown project', 'Pick a project number from the list on the "' . self::PROJECTS_SHEET . '" sheet.');
            }

            if ($name === 'submitted_date' || str_starts_with($name, 'commitment_date_')) {
                // Pin the display format so exported dates come back unambiguous.
                $sheet->getStyle("{$col}2:{$col}{$lastRow}")
                    ->getNumberFormat()->setFormatCode('yyyy-mm-dd');
            }
        }

        if ($projects) {
            $this->addProjectsSheet($spreadsheet, $projects);
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

    /**
     * Reference sheet backing the project_no dropdown: the projects the engineer
     * can report on, with each one's NTP numbers spelled out so the ntp_no column
     * can be filled in without leaving the workbook.
     *
     * @param  array<int, array{project_no: string, title: string, ntps?: string}>  $projects
     */
    private function addProjectsSheet(Spreadsheet $spreadsheet, array $projects): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle(self::PROJECTS_SHEET);
        $sheet->fromArray(['Project #', 'Project Title', 'NTP numbers (for ntp_no)'], null, 'A1');
        $sheet->getStyle('A1:C1')->getFont()->setBold(true);

        foreach (array_values($projects) as $n => $project) {
            $sheet->fromArray([
                $project['project_no'],
                $project['title'],
                $project['ntps'] ?? '',
            ], null, 'A' . ($n + 2));
        }

        $sheet->getColumnDimension('A')->setWidth(22);
        $sheet->getColumnDimension('B')->setWidth(52);
        $sheet->getColumnDimension('C')->setWidth(46);
    }

    private function addDropdown($sheet, string $col, int $lastRow, string $formula, string $errorTitle, string $error): void
    {
        for ($row = 2; $row <= $lastRow; $row++) {
            $validation = $sheet->getCell("{$col}{$row}")->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_STOP);
            $validation->setAllowBlank(true);
            $validation->setShowDropDown(true);
            $validation->setShowErrorMessage(true);
            $validation->setErrorTitle($errorTitle);
            $validation->setError($error);
            $validation->setFormula1($formula);
        }
    }

    /** A worked example row so the expected shape is obvious. */
    private function sampleRow($items, int $issueRows, array $statuses, array $projects = []): array
    {
        [$done, $notDone] = [$statuses[0] ?? '√', $statuses[1] ?? '✕'];

        $row = ['W1-OCT', '', 25, '2026-10-07', "Foundation works started; formworks scheduled next week."];
        if ($projects) {
            array_unshift($row, $projects[0]['project_no'] ?? '');
        }
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
