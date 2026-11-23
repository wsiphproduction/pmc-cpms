<?php

namespace App\Support;

use App\Models\Project;
use App\Models\ProjectBilling;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Reads a hub document's form against its definition in
 * config/project_assessments.php.
 *
 * A draft may be saved half-filled, so `clean()` only rejects values of the
 * wrong kind (a bad date, an option not on the list); `missing()` lists what
 * a submission still needs. Risk columns are worked out here, never taken
 * from the browser.
 */
class AssessmentForm
{
    public const ANSWERS = ['yes', 'no', 'na'];

    /**
     * @return array<string, mixed> the cleaned form
     *
     * @throws ValidationException on a value of the wrong kind
     */
    public static function clean(array $definition, array $input, Project $project): array
    {
        $data = [];
        $errors = [];

        foreach ($definition['fields'] as $field) {
            $key = $field['key'];
            $value = $input[$key] ?? null;

            try {
                $data[$key] = match ($field['type']) {
                    'table'     => self::table($field, is_array($value) ? $value : []),
                    'checklist' => self::checklist($field, is_array($value) ? $value : []),
                    'billing'   => self::billing($value, $project),
                    default     => self::scalar($field, $value),
                };
            } catch (\InvalidArgumentException $e) {
                $errors["data.{$key}"] = "{$field['label']}: {$e->getMessage()}";
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $data;
    }

    /** What a submission still needs, one line per gap. */
    public static function missing(array $definition, array $data): array
    {
        $missing = [];

        foreach ($definition['fields'] as $field) {
            $value = $data[$field['key']] ?? null;
            $label = $field['label'];

            if ($field['type'] === 'table') {
                if (($field['required'] ?? false) && empty($value)) {
                    $missing[] = "{$label}: add at least one row.";
                }
                foreach ($value ?? [] as $i => $row) {
                    foreach ($field['columns'] as $column) {
                        if (($column['required'] ?? false) && blank($row[$column['key']] ?? null)) {
                            $missing[] = "{$label}, row " . ($i + 1) . ": {$column['label']} is required.";
                        }
                    }
                }
                continue;
            }

            if ($field['type'] === 'checklist') {
                if ($field['required'] ?? false) {
                    foreach ($field['items'] as $itemKey => $itemLabel) {
                        if (blank($value[$itemKey]['answer'] ?? null)) {
                            $missing[] = "{$label}: answer “{$itemLabel}”.";
                        }
                    }
                }
                continue;
            }

            if (($field['required'] ?? false) && blank($value)) {
                $missing[] = "{$label} is required.";
            }
        }

        return $missing;
    }

    /** A short title for lists: the definition's title field, as text. */
    public static function title(array $definition, array $data): ?string
    {
        $key = $definition['title_field'] ?? null;

        if ($key === null) {
            return null;
        }

        if ($key === 'billing' && ! empty($data['billing'])) {
            $billing = ProjectBilling::find($data['billing']);

            return $billing ? 'Billing ' . ($billing->stmt_no ?: '#' . $billing->id) : null;
        }

        $value = $data[$key] ?? null;

        return is_string($value) ? mb_strimwidth($value, 0, 250, '…') : null;
    }

    /** 1–4 Low, 5–9 Medium, 10–16 High, 20–25 Extreme. */
    public static function riskRating(?int $score): ?string
    {
        return match (true) {
            $score === null => null,
            $score >= 20    => 'Extreme',
            $score >= 10    => 'High',
            $score >= 5     => 'Medium',
            default         => 'Low',
        };
    }

    // ── Field kinds ───────────────────────────────────────────────────────────

    private static function scalar(array $field, mixed $value): mixed
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }

        switch ($field['type']) {
            case 'date':
                try {
                    return Carbon::createFromFormat('Y-m-d', (string) $value)->toDateString();
                } catch (\Throwable) {
                    throw new \InvalidArgumentException('enter a valid date.');
                }

            case 'number':
            case 'money':
            case 'percent':
                if (! is_numeric($value)) {
                    throw new \InvalidArgumentException('enter a number.');
                }
                $number = round((float) $value, 2);
                if ($field['type'] !== 'number' && $number < 0) {
                    throw new \InvalidArgumentException('cannot be negative.');
                }
                if ($field['type'] === 'percent' && $number > 100) {
                    throw new \InvalidArgumentException('cannot be over 100%.');
                }

                return $number;

            case 'select':
                if (! array_key_exists((string) $value, $field['options'])) {
                    throw new \InvalidArgumentException('choose one of the listed options.');
                }

                return (string) $value;

            default: // text, textarea
                return mb_substr(trim((string) $value), 0, $field['type'] === 'text' ? 500 : 5000);
        }
    }

    private static function checklist(array $field, array $value): array
    {
        $clean = [];

        foreach ($field['items'] as $itemKey => $label) {
            $answer = $value[$itemKey]['answer'] ?? null;
            if ($answer !== null && $answer !== '' && ! in_array($answer, self::ANSWERS, true)) {
                throw new \InvalidArgumentException("answer “{$label}” Yes, No or N/A.");
            }

            $clean[$itemKey] = [
                'answer' => $answer ?: null,
                'remark' => mb_substr(trim((string) ($value[$itemKey]['remark'] ?? '')), 0, 1000) ?: null,
            ];
        }

        return $clean;
    }

    private static function table(array $field, array $rows): array
    {
        $clean = [];

        foreach (array_values($rows) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $out = [];
            foreach ($field['columns'] as $column) {
                if ($column['type'] === 'risk') {
                    continue;
                }
                $out[$column['key']] = self::scalar($column, $row[$column['key']] ?? null);
            }

            // A row with nothing in it is dropped rather than kept as a blank.
            if (collect($out)->filter(fn ($v) => $v !== null && $v !== '')->isEmpty()) {
                continue;
            }

            foreach ($field['columns'] as $column) {
                if ($column['type'] === 'risk') {
                    [$a, $b] = $column['of'];
                    $score = isset($out[$a], $out[$b]) ? (int) $out[$a] * (int) $out[$b] : null;
                    $out[$column['key']] = $score === null ? null : ['score' => $score, 'rating' => self::riskRating($score)];
                }
            }

            $clean[] = $out;
        }

        if (count($clean) > 100) {
            throw new \InvalidArgumentException('keep it to 100 rows.');
        }

        return $clean;
    }

    private static function billing(mixed $value, Project $project): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $exists = ProjectBilling::where('project_id', $project->id)->whereKey((int) $value)->exists();
        if (! $exists) {
            throw new \InvalidArgumentException('choose one of this project’s billings.');
        }

        return (int) $value;
    }
}
