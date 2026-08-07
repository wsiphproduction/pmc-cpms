<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

/**
 * Reads an uploaded weekly-report spreadsheet (.csv or .xlsx) and turns it into
 * normalised report payloads.
 *
 * Recognised columns (header row, case-insensitive): week_code, completion_pct,
 * identified_issues, progress_updates, submitted_date, ntp_no and — for the
 * cross-project Weekly Status template — project_no. Only week_code is required
 * per row.
 *
 * The detailed template additionally carries the checklist and the weekly
 * issues / action plan from the submission form, as repeating column groups:
 *   chk_<seq>_status / chk_<seq>_remarks  (seq underscored, e.g. chk_1_1_status)
 *   issue_<n> / action_<n> / commitment_date_<n>
 * Both groups are discovered from the header, so adding checklist items or issue
 * rows to the form needs no change here.
 *
 * Shared by the per-project PSR import and the cross-project Weekly Status
 * import so the two can't drift apart.
 */
class WeeklyReportSheet
{
    /** @var array<int, string> Lower-cased header cells. */
    private array $columns;

    /** @var array<int, array<int, string|null>> Data rows (header already removed). */
    private array $rows;

    /** @var array<string, int|null> Index of each single-value column. */
    private array $index;

    /** @var array<string, array<string, int>> chk_<seq> groups keyed by seq. */
    private array $checklistIndex = [];

    /** @var array<int, array<string, int>> issue groups keyed by row number. */
    private array $issueIndex = [];

    /**
     * @throws \RuntimeException when the file can't be read or is empty.
     */
    public function __construct(UploadedFile $file)
    {
        $rows = $this->readRows($file);

        $header = array_shift($rows);
        if ($header === null) {
            throw new \RuntimeException('The uploaded file is empty.');
        }

        $header[0]    = preg_replace('/^\xEF\xBB\xBF/', '', (string) ($header[0] ?? ''));
        $this->columns = array_map(fn ($h) => strtolower(trim((string) $h)), $header);
        $this->rows    = $rows;

        $this->index = [
            'project_no'        => $this->findColumn(['project_no', 'project', 'project no', 'project number', 'project #']),
            'week_code'         => $this->findColumn(['week_code', 'week', 'week code']),
            'completion_pct'    => $this->findColumn(['completion_pct', 'completion', 'percent', '% completion', 'progress']),
            'identified_issues' => $this->findColumn(['identified_issues', 'issues', 'identified issues']),
            'progress_updates'  => $this->findColumn(['progress_updates', 'updates', 'progress updates']),
            'submitted_date'    => $this->findColumn(['submitted_date', 'date', 'submitted date']),
            'ntp_no'            => $this->findColumn(['ntp_no', 'ntp', 'ntp no', 'ntp number']),
        ];

        foreach ($this->columns as $i => $name) {
            if (preg_match('/^chk_(.+)_(status|remarks)$/', $name, $m)) {
                $this->checklistIndex[str_replace('_', '.', $m[1])][$m[2]] = $i;
            } elseif (preg_match('/^(issue|action|corrective_action|commitment_date|commit_date)_(\d+)$/', $name, $m)) {
                $key = match ($m[1]) {
                    'issue'                       => 'issue',
                    'action', 'corrective_action' => 'action',
                    default                       => 'commitment_date',
                };
                $this->issueIndex[(int) $m[2]][$key] = $i;
            }
        }

        ksort($this->issueIndex);
        uksort($this->checklistIndex, fn ($a, $b) => strnatcmp($a, $b));
    }

    /** week_code is the one column a file can't do without. */
    public function hasWeekCode(): bool
    {
        return $this->index['week_code'] !== null;
    }

    public function hasProjectNo(): bool
    {
        return $this->index['project_no'] !== null;
    }

    /**
     * Normalised reports, one per non-blank row. Rows without a week code are
     * skipped. `project_no` and `ntp_no` are returned as raw strings for the
     * caller to resolve, since only it knows which projects are in scope.
     *
     * @return \Generator<int, array{
     *     project_no: ?string, ntp_no: ?string, week_code: string, completion_pct: int,
     *     identified_issues: ?string, progress_updates: ?string, submitted_date: string,
     *     checklist: array<int, array{seq: string, status: ?string, remarks: ?string}>,
     *     issues: array<int, array{issue: ?string, action: ?string, commitment_date: ?string}>
     * }>
     */
    public function reports(): \Generator
    {
        foreach ($this->rows as $row) {
            $week = $this->cell($row, $this->index['week_code']);
            if ($week === null) {
                continue;
            }

            // Checklist: keep only items the row actually answered.
            $checklist = [];
            foreach ($this->checklistIndex as $seq => $idx) {
                $status  = self::normalizeChecklistStatus($this->cell($row, $idx['status'] ?? null));
                $remarks = $this->cell($row, $idx['remarks'] ?? null);
                if ($status === null && $remarks === null) {
                    continue;
                }
                $checklist[] = ['seq' => (string) $seq, 'status' => $status, 'remarks' => $remarks];
            }

            // Issues / action plan: skip rows left entirely blank.
            $issues = [];
            foreach ($this->issueIndex as $idx) {
                $issue  = $this->cell($row, $idx['issue'] ?? null);
                $action = $this->cell($row, $idx['action'] ?? null);
                $due    = self::parseDate($this->cell($row, $idx['commitment_date'] ?? null));
                if ($issue === null && $action === null && $due === null) {
                    continue;
                }
                $issues[] = ['issue' => $issue, 'action' => $action, 'commitment_date' => $due];
            }

            $pct = (int) round((float) ($this->cell($row, $this->index['completion_pct']) ?? 0));

            yield [
                'project_no'        => $this->cell($row, $this->index['project_no']),
                'ntp_no'            => $this->cell($row, $this->index['ntp_no']),
                'week_code'         => mb_substr($week, 0, 20),
                'completion_pct'    => max(0, min(100, $pct)),
                // The form treats the first issue row as the report's headline
                // issue, so fall back to it when the column is absent.
                'identified_issues' => $this->cell($row, $this->index['identified_issues']) ?? ($issues[0]['issue'] ?? null),
                'progress_updates'  => $this->cell($row, $this->index['progress_updates']),
                // Keep the default of today when the date is missing or unparseable.
                'submitted_date'    => self::parseDate($this->cell($row, $this->index['submitted_date'])) ?? now()->toDateString(),
                'checklist'         => $checklist,
                'issues'            => $issues,
            ];
        }
    }

    /**
     * Map a checklist cell onto the symbols the form uses. Spreadsheets mangle
     * √/✕/Ø often enough that plain-word aliases are accepted too.
     */
    public static function normalizeChecklistStatus(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $value = trim($raw);

        // Match the symbols before any case folding: lowercasing has to be
        // multibyte-aware to map Ø onto ø, and the symbols need no folding at all.
        if (in_array($value, (array) config('psr.statuses'), true)) {
            return $value;
        }

        return match (mb_strtolower($value)) {
            'v', 'y', 'yes', 'ok', 'done', 'complete', 'completed', 'pass'    => '√',
            'x', 'n', 'no', 'not done', 'incomplete', 'not completed', 'fail' => '✕',
            'ø', 'o', 'n/a', 'na', 'not applicable'                           => 'Ø',
            default                                                           => null,
        };
    }

    private static function parseDate(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($raw)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function cell(array $row, ?int $idx): ?string
    {
        if ($idx === null) {
            return null;
        }

        $value = trim((string) ($row[$idx] ?? ''));

        return $value === '' ? null : $value;
    }

    private function findColumn(array $candidates): ?int
    {
        foreach ($candidates as $candidate) {
            $index = array_search($candidate, $this->columns, true);
            if ($index !== false) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Read an uploaded .csv or .xlsx into plain rows (first row is the header).
     * Dates in a workbook come back as formatted text so the same parsing applies
     * to both formats.
     *
     * @return array<int, array<int, string|null>>
     */
    private function readRows(UploadedFile $file): array
    {
        $path = $file->getRealPath();

        $isXlsx = strtolower((string) $file->getClientOriginalExtension()) === 'xlsx'
            || $file->getMimeType() === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        if ($isXlsx) {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
            $reader->setReadDataOnly(false);   // number formats are needed to render dates
            $spreadsheet = $reader->load($path);
            $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
            $spreadsheet->disconnectWorksheets();

            // Drop trailing rows the template pre-formatted but nobody filled in.
            return array_values(array_filter(
                $rows,
                fn ($row) => collect($row)->contains(fn ($v) => trim((string) $v) !== '')
            ));
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Could not open {$path}.");
        }

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }
}
