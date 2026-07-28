<?php

namespace App\Support;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Accomplishment report for one project engineer: the projects they registered
 * and the weekly progress reports filed against each.
 *
 * Three sheets — a project summary, every weekly report, and the checklist
 * answers behind those reports.
 */
class AccomplishmentReportWriter
{
    private const HEADER_FILL = '1E293B';
    private const ACCENT_FILL = 'EFF6FF';

    /**
     * @param  Collection<int, Project>  $projects  Eager-loaded with weeklyReports.ntp, parent, manager.
     */
    public function __construct(
        private User $engineer,
        private Collection $projects,
        private ?string $from = null,
        private ?string $to = null,
    ) {
    }

    /** @return string Raw .xlsx bytes. */
    public function build(): string
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator($this->engineer->name)
            ->setTitle('Accomplishment Report')
            ->setSubject("Projects and weekly progress reports for {$this->engineer->name}");

        $this->buildSummarySheet($spreadsheet->getActiveSheet());
        $this->buildReportsSheet($spreadsheet->createSheet());
        $this->buildChecklistSheet($spreadsheet->createSheet());

        $spreadsheet->setActiveSheetIndex(0);

        $temp = tempnam(sys_get_temp_dir(), 'acr');
        (new Xlsx($spreadsheet))->save($temp);
        $bytes = (string) file_get_contents($temp);
        @unlink($temp);
        $spreadsheet->disconnectWorksheets();

        return $bytes;
    }

    public function filename(): string
    {
        $slug   = str($this->engineer->name)->slug()->value() ?: 'engineer';
        $period = $this->from || $this->to
            ? '-' . ($this->from ?: 'start') . '-to-' . ($this->to ?: 'today')
            : '';

        return "accomplishment-report-{$slug}{$period}.xlsx";
    }

    // ── Sheet 1: project summary ──────────────────────────────────────────────

    private function buildSummarySheet(Worksheet $sheet): void
    {
        $sheet->setTitle('Summary');

        $reports = $this->projects->flatMap->weeklyReports;

        $headRow = $this->titleBlock($sheet, 'ACCOMPLISHMENT REPORT', 'K', [
            ['Project Engineer', $this->engineer->name],
            ['Email', (string) $this->engineer->email],
            ['Report Period', $this->periodLabel()],
            ['Generated', now()->format('M d, Y h:i A')],
            ['Projects Registered', $this->projects->count()],
            ['Weekly Reports Filed', $reports->count()],
        ]) + 1;

        $head = [
            'Project No', 'Title', 'Parent Project', 'Type', 'Status', 'Progress %',
            'Dept Owner', 'Project Manager', 'Deadline', 'Weekly Reports', 'Latest Week',
        ];
        $this->writeRow($sheet, $head, "A{$headRow}");

        $row = $headRow + 1;
        foreach ($this->projects as $project) {
            $latest = $project->weeklyReports->sortByDesc('submitted_date')->first();

            $this->writeRow($sheet, [
                $project->project_no,
                $project->title,
                $project->parent?->project_no ?? '—',
                $this->projectType((string) $project->class_name),
                Project::STATUS_LABELS[$project->status_key] ?? $project->status_key,
                $project->effectiveCompletionPercent() / 100,
                $project->dept_owner ?: '—',
                $project->project_manager_name ?: ($project->manager?->name ?? 'Unassigned'),
                optional($project->deadline)->format('Y-m-d') ?? '—',
                $project->weeklyReports->count(),
                $latest?->week_code ?? '—',
            ], "A{$row}");
            $row++;
        }

        if ($this->projects->isEmpty()) {
            $sheet->setCellValue("A{$row}", 'No projects registered by this engineer.');
            $sheet->mergeCells("A{$row}:K{$row}");
            $sheet->getStyle("A{$row}")->getFont()->setItalic(true);
            $row++;
        }

        $this->styleTable($sheet, $headRow, $row - 1, 'K');
        $sheet->getStyle("F" . ($headRow + 1) . ":F" . ($row - 1))->getNumberFormat()->setFormatCode('0%');
        $sheet->getStyle("J" . ($headRow + 1) . ":J" . ($row - 1))
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $this->setWidths($sheet, ['A' => 18, 'B' => 40, 'C' => 16, 'D' => 10, 'E' => 26, 'F' => 12, 'G' => 18, 'H' => 22, 'I' => 13, 'J' => 15, 'K' => 14]);
        $sheet->freezePane('A' . ($headRow + 1));
    }

    // ── Sheet 2: weekly progress reports ──────────────────────────────────────

    private function buildReportsSheet(Worksheet $sheet): void
    {
        $sheet->setTitle('Progress Reports');

        $headRow = $this->titleBlock($sheet, 'WEEKLY PROGRESS REPORTS', 'I', [
            ['Project Engineer', $this->engineer->name],
            ['Report Period', $this->periodLabel()],
        ]) + 1;

        $head = [
            'Project No', 'Project Title', 'Week', 'NTP / Contractor', 'Progress %',
            'Identified Issues', 'Corrective Actions', 'Progress Updates', 'Submitted',
        ];
        $this->writeRow($sheet, $head, "A{$headRow}");

        $row = $headRow + 1;
        foreach ($this->projects as $project) {
            foreach ($project->weeklyReports->sortBy('submitted_date') as $report) {
                $issues  = collect($report->issues ?? []);

                $this->writeRow($sheet, [
                    $project->project_no,
                    $project->title,
                    $report->week_code,
                    $report->ntp
                        ? trim($report->ntp->ntp_no . ' — ' . $report->ntp->contractor_name, ' —')
                        : 'Whole project',
                    (int) $report->completion_pct / 100,
                    $issues->isNotEmpty()
                        ? $issues->pluck('issue')->filter()->implode("\n")
                        : (string) $report->identified_issues,
                    $issues->pluck('action')->filter()->implode("\n"),
                    (string) $report->progress_updates,
                    optional($report->submitted_date)->format('Y-m-d'),
                ], "A{$row}");
                $row++;
            }
        }

        if ($row === $headRow + 1) {
            $sheet->setCellValue("A{$row}", 'No weekly reports filed in this period.');
            $sheet->mergeCells("A{$row}:I{$row}");
            $sheet->getStyle("A{$row}")->getFont()->setItalic(true);
            $row++;
        }

        $this->styleTable($sheet, $headRow, $row - 1, 'I');
        $sheet->getStyle("E" . ($headRow + 1) . ":E" . ($row - 1))->getNumberFormat()->setFormatCode('0%');
        $sheet->getStyle("F" . ($headRow + 1) . ":H" . ($row - 1))
            ->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);

        $this->setWidths($sheet, ['A' => 18, 'B' => 34, 'C' => 12, 'D' => 26, 'E' => 12, 'F' => 38, 'G' => 38, 'H' => 44, 'I' => 13]);
        $sheet->freezePane('A' . ($headRow + 1));
    }

    // ── Sheet 3: checklist answers ────────────────────────────────────────────

    private function buildChecklistSheet(Worksheet $sheet): void
    {
        $sheet->setTitle('Checklist Details');

        $labels = collect(config('psr.checklist'))->pluck('label', 'seq');

        $headRow = $this->titleBlock($sheet, 'WEEKLY CHECKLIST ANSWERS', 'G', [
            ['Project Engineer', $this->engineer->name],
            ['Report Period', $this->periodLabel()],
        ]) + 1;

        $head = ['Project No', 'Week', 'Submitted', 'Seq', 'Item / Requirement', 'Status', 'Remarks'];
        $this->writeRow($sheet, $head, "A{$headRow}");

        $row = $headRow + 1;
        foreach ($this->projects as $project) {
            foreach ($project->weeklyReports->sortBy('submitted_date') as $report) {
                foreach (($report->checklist ?? []) as $item) {
                    $this->writeRow($sheet, [
                        $project->project_no,
                        $report->week_code,
                        optional($report->submitted_date)->format('Y-m-d'),
                        $item['seq'] ?? '',
                        $labels[$item['seq'] ?? ''] ?? '—',
                        $item['status'] ?? '—',
                        $item['remarks'] ?? '',
                    ], "A{$row}");
                    $row++;
                }
            }
        }

        if ($row === $headRow + 1) {
            $sheet->setCellValue("A{$row}", 'No checklist answers recorded for these reports.');
            $sheet->mergeCells("A{$row}:G{$row}");
            $sheet->getStyle("A{$row}")->getFont()->setItalic(true);
            $row++;
        }

        $this->styleTable($sheet, $headRow, $row - 1, 'G');
        $sheet->getStyle("F" . ($headRow + 1) . ":F" . ($row - 1))
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("E" . ($headRow + 1) . ":E" . ($row - 1))->getAlignment()->setWrapText(true);
        $sheet->getStyle("G" . ($headRow + 1) . ":G" . ($row - 1))->getAlignment()->setWrapText(true);

        $this->setWidths($sheet, ['A' => 18, 'B' => 12, 'C' => 13, 'D' => 8, 'E' => 50, 'F' => 10, 'G' => 38]);
        $sheet->freezePane('A' . ($headRow + 1));
    }

    // ── Shared formatting ─────────────────────────────────────────────────────

    /**
     * @param  array<int, array{0: string, 1: string|int}>  $meta
     * @return int The first free row below the block.
     */
    private function titleBlock(Worksheet $sheet, string $title, string $lastCol, array $meta): int
    {
        $sheet->setCellValue('A1', $title);
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::HEADER_FILL]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(26);

        // Two label/value pairs per row, starting at row 2.
        $row = 2;
        foreach (array_chunk($meta, 2) as $pair) {
            $sheet->setCellValue("A{$row}", $pair[0][0] . ':');
            $sheet->setCellValue("B{$row}", $pair[0][1]);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);

            if (isset($pair[1])) {
                $sheet->setCellValue("E{$row}", $pair[1][0] . ':');
                $sheet->setCellValue("F{$row}", $pair[1][1]);
                $sheet->getStyle("E{$row}")->getFont()->setBold(true);
            }
            $row++;
        }

        return $row;
    }

    /**
     * fromArray() skips cells loosely equal to its null value, which silently
     * drops legitimate zeros (0% progress, 0 reports) — so always compare
     * strictly.
     *
     * @param  array<int, mixed>  $values
     */
    private function writeRow(Worksheet $sheet, array $values, string $startCell): void
    {
        $sheet->fromArray($values, null, $startCell, true);
    }

    private function styleTable(Worksheet $sheet, int $headRow, int $lastRow, string $lastCol): void
    {
        $sheet->getStyle("A{$headRow}:{$lastCol}{$headRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::HEADER_FILL]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension($headRow)->setRowHeight(28);

        if ($lastRow <= $headRow) {
            return;
        }

        $sheet->getStyle("A{$headRow}:{$lastCol}{$lastRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
        ]);

        // Banded rows for readability.
        for ($row = $headRow + 1; $row <= $lastRow; $row++) {
            if (($row - $headRow) % 2 === 0) {
                $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::ACCENT_FILL);
            }
        }

        $sheet->setAutoFilter("A{$headRow}:{$lastCol}{$lastRow}");
    }

    /** @param array<string, int> $widths */
    private function setWidths(Worksheet $sheet, array $widths): void
    {
        foreach ($widths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }
    }

    private function periodLabel(): string
    {
        if (! $this->from && ! $this->to) {
            return 'All time';
        }

        $fmt = fn (?string $d) => $d ? Carbon::parse($d)->format('M d, Y') : null;

        return match (true) {
            $this->from && $this->to => $fmt($this->from) . ' – ' . $fmt($this->to),
            (bool) $this->from       => 'From ' . $fmt($this->from),
            default                  => 'Up to ' . $fmt($this->to),
        };
    }

    private function projectType(string $className): string
    {
        $name = strtolower($className);

        return str_contains($name, 'major') || str_contains($name, 'tier 1') ? 'Major' : 'Minor';
    }
}
