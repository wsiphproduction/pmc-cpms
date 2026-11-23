<?php

namespace App\Support;

use App\Support\ContractorEvaluationScoring as Scoring;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * The contractor performance reports of framework §8, as Excel workbooks:
 *
 *  - Consolidated Contractor Performance / Ranking and Comparison
 *  - Individual Contractor Performance (with its performance history)
 *  - Project-Level Performance Evaluation
 *
 * Every figure comes from ContractorPerformance, so a workbook always agrees
 * with the page it was downloaded from.
 */
class ContractorPerformanceReportWriter
{
    private const HEADER_FILL = '1E293B';
    private const TITLE_COLOR = '5B21B6';

    public function __construct(private string $author) {}

    /** Ranking of every contractor in the filtered set, with category averages. */
    public function consolidated(array $ranking, array $filters): string
    {
        $book = $this->book('Consolidated Contractor Performance Report');
        $sheet = $book->getActiveSheet()->setTitle('Ranking');

        $categories = Scoring::categories();
        $headers = ['Rank', 'Contractor', 'On Master Data', 'Projects', 'Evaluated', 'Not Yet Evaluated', 'Contract Value (PhP)', 'Overall Score', 'Rating', 'Recommended Action'];
        foreach ($categories as $c) {
            $headers[] = "{$c['letter']}. {$c['title']} (% of {$c['weight']})";
        }
        $headers[] = 'Flags';

        $row = $this->title($sheet, 'Consolidated Contractor Performance Report', $this->filterLine($filters), count($headers));
        $this->headerRow($sheet, $row, $headers);

        foreach ($ranking as $r) {
            $values = [
                $r['rank'] ?? '—',
                $r['name'],
                $r['supplier'] ? 'Yes' : 'No',
                $r['projects'],
                $r['evaluated'],
                $r['pending'],
                $r['contract_value'],
                $r['score'] ?? 'Not Yet Evaluated',
                $r['classification'] ?? '—',
                $r['action'] ?? '',
            ];
            foreach (array_keys($categories) as $key) {
                $values[] = $r['category_averages'][$key] ?? '—';
            }
            $values[] = implode("\n", array_column($r['flags'], 'message'));
            $sheet->fromArray($values, null, 'A' . ++$row);
        }

        $sheet->getStyle('G:G')->getNumberFormat()->setFormatCode('#,##0.00');
        $this->finish($sheet, count($headers));

        return $this->bytes($book);
    }

    /** One contractor: summary, projects, category averages, history and corrective actions. */
    public function individual(array $profile, array $filters): string
    {
        $book = $this->book("Contractor Performance — {$profile['name']}");
        $categories = Scoring::categories();

        // Summary
        $sheet = $book->getActiveSheet()->setTitle('Summary');
        $row = $this->title($sheet, 'Individual Contractor Performance Evaluation Report', $this->filterLine($filters), 2);
        $lines = [
            ['Contractor', $profile['name']],
            ['On Master Data', $profile['supplier'] ? 'Yes' . ($profile['supplier']['accredited'] ? ' (accredited)' : '') : 'No'],
            ['Overall Score', $profile['score'] ?? 'Not Yet Evaluated'],
            ['Rating', $profile['classification'] ?? '—'],
            ['Recommended Action', $profile['action'] ?? '—'],
            ['Rank', $profile['rank'] ? "{$profile['rank']} of {$profile['ranked_of']}" : '—'],
            ['Projects', $profile['projects']],
            ['Projects Evaluated', $profile['evaluated']],
            ['Not Yet Evaluated', $profile['pending']],
            ['Approved Evaluations', $profile['evaluations']],
            ['Contract Value (PhP)', $profile['contract_value']],
        ];
        foreach ($categories as $key => $c) {
            $lines[] = ["{$c['letter']}. {$c['title']} (% of weight)", $profile['category_averages'][$key] ?? '—'];
        }
        foreach ($profile['flags'] as $flag) {
            $lines[] = ['Flag', $flag['message']];
        }
        foreach ($lines as $line) {
            $sheet->fromArray($line, null, 'A' . ++$row);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        }
        $sheet->getColumnDimension('A')->setWidth(48);
        $sheet->getColumnDimension('B')->setWidth(60);

        // Projects
        $sheet = $book->createSheet()->setTitle('Projects');
        $headers = ['Project No.', 'Title', 'Department', 'Category', 'Status', 'Contract Value (PhP)', 'Latest Evaluation', 'Period', 'Score', 'Rating'];
        $row = $this->title($sheet, "Projects — {$profile['name']}", null, count($headers));
        $this->headerRow($sheet, $row, $headers);
        foreach ($profile['assignments'] as $a) {
            $sheet->fromArray([
                $a['project_no'], $a['title'], $a['department'], $a['category'], $a['status'], $a['contract_value'],
                $a['evaluation']['control_no'] ?? 'Not Yet Evaluated',
                $a['evaluation']['period_label'] ?? '',
                $a['evaluation']['score'] ?? '',
                $a['evaluation']['classification'] ?? '',
            ], null, 'A' . ++$row);
        }
        $sheet->getStyle('F:F')->getNumberFormat()->setFormatCode('#,##0.00');
        $this->finish($sheet, count($headers));

        // History
        $this->historySheet($book->createSheet()->setTitle('Performance History'), $profile['history'], "Performance History — {$profile['name']}");

        // Corrective actions
        $sheet = $book->createSheet()->setTitle('Corrective Actions');
        $headers = ['Evaluation', 'Project No.', 'Period', 'Category', 'Required Corrective Action'];
        $row = $this->title($sheet, "Corrective Actions — {$profile['name']}", null, count($headers));
        $this->headerRow($sheet, $row, $headers);
        foreach ($profile['corrective_actions'] as $c) {
            $sheet->fromArray([$c['control_no'], $c['project_no'], $c['period'], $c['category'], $c['action']], null, 'A' . ++$row);
        }
        $this->finish($sheet, count($headers));

        $book->setActiveSheetIndex(0);

        return $this->bytes($book);
    }

    /** Every approved project-level evaluation in the filtered set, one row each. */
    public function projectLevel(array $history, array $filters): string
    {
        $book = $this->book('Project-Level Performance Evaluation Report');
        $this->historySheet($book->getActiveSheet()->setTitle('Evaluations'), $history, 'Project-Level Performance Evaluation Report', $this->filterLine($filters), true);

        return $this->bytes($book);
    }

    // ── Pieces ────────────────────────────────────────────────────────────────

    private function historySheet(Worksheet $sheet, array $history, string $title, ?string $subtitle = null, bool $withContractor = false): void
    {
        $categories = Scoring::categories();
        $headers = [...($withContractor ? ['Contractor'] : []), 'Evaluation', 'Project No.', 'Project Title', 'Type', 'Period', 'As Of', 'Evaluated By', 'Score', 'Rating'];
        foreach ($categories as $c) {
            $headers[] = "{$c['letter']} (/{$c['weight']})";
        }

        $row = $this->title($sheet, $title, $subtitle, count($headers));
        $this->headerRow($sheet, $row, $headers);
        $types = config('contractor_evaluation.period_types');

        foreach ($history as $h) {
            $values = [
                ...($withContractor ? [$h['contractor'] ?? ''] : []),
                $h['control_no'], $h['project_no'], $h['project_title'],
                $types[$h['period_type']] ?? $h['period_type'], $h['period_label'], $h['as_of'], $h['evaluated_by'],
                $h['score'], $h['classification'],
            ];
            foreach (array_keys($categories) as $key) {
                $values[] = collect($h['categories'])->firstWhere('key', $key)['score'] ?? '';
            }
            $sheet->fromArray($values, null, 'A' . ++$row);
        }

        $this->finish($sheet, count($headers));
    }

    private function book(string $title): Spreadsheet
    {
        $book = new Spreadsheet();
        $book->getProperties()->setCreator($this->author)->setTitle($title);

        return $book;
    }

    /** Title and subtitle rows; returns the row the header goes on. */
    private function title(Worksheet $sheet, string $title, ?string $subtitle, int $columns): int
    {
        $sheet->setCellValue('A1', $title);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB(self::TITLE_COLOR);
        $sheet->setCellValue('A2', trim(($subtitle ? $subtitle . ' · ' : '') . 'Generated ' . now()->format('M d, Y h:i A') . ' by ' . $this->author));
        $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(9)->getColor()->setRGB('64748B');

        return 4;
    }

    private function headerRow(Worksheet $sheet, int $row, array $headers): void
    {
        $sheet->fromArray($headers, null, "A{$row}");
        $last = $this->col(count($headers));
        $sheet->getStyle("A{$row}:{$last}{$row}")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::HEADER_FILL]],
            'alignment' => ['wrapText' => true, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->freezePane('A' . ($row + 1));
    }

    private function finish(Worksheet $sheet, int $columns): void
    {
        $last = $this->col($columns);
        $highest = $sheet->getHighestRow();

        if ($highest >= 4) {
            $sheet->getStyle("A4:{$last}{$highest}")->applyFromArray([
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
                'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
            ]);
        }

        for ($i = 1; $i <= $columns; $i++) {
            $sheet->getColumnDimension($this->col($i))->setWidth($i <= 3 ? 24 : 16);
        }
    }

    private function filterLine(array $filters): ?string
    {
        $parts = [];
        if ($filters['window_from'] || $filters['window_to']) {
            $parts[] = 'Period ' . ($filters['window_from'] ?? '…') . ' to ' . ($filters['window_to'] ?? '…');
        }
        if ($filters['basis'] === 'value') {
            $parts[] = 'Weighted by contract value';
        }
        foreach (['contractor' => 'Contractor', 'project' => 'Project', 'department' => 'Department', 'category' => 'Category', 'status' => 'Status', 'project_type' => 'Type', 'rating' => 'Rating', 'evaluation' => 'Evaluation'] as $key => $label) {
            if (filled($filters[$key] ?? null)) {
                $parts[] = "{$label}: {$filters[$key]}";
            }
        }

        return $parts === [] ? null : implode(' · ', $parts);
    }

    private function col(int $index): string
    {
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index);
    }

    private function bytes(Spreadsheet $book): string
    {
        $temp = tempnam(sys_get_temp_dir(), 'cpe');
        (new Xlsx($book))->save($temp);
        $bytes = (string) file_get_contents($temp);
        @unlink($temp);
        $book->disconnectWorksheets();

        return $bytes;
    }
}
