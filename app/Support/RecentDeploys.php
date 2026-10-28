<?php

namespace App\Support;

/**
 * The latest updates to reach the live site, for the "What's new" card on the
 * login page. Read from the log scripts/deploy.sh appends to, which closes each
 * successful deploy with `[Y-m-d H:i:s] Deployed <sha>: <commit subject>`.
 *
 * The login page is public, so only user-facing commits are shown (`feat:` and
 * `fix:`; chores, CI, tests and merges are left out) and any email address in
 * a subject is dropped.
 */
class RecentDeploys
{
    /** Enough of the log's tail to hold far more deploys than are ever shown. */
    private const TAIL_BYTES = 65536;

    private const LABELS = [
        'feat' => 'New',
        'fix'  => 'Fix',
    ];

    /**
     * @return array<int, array{sha: string, type: string, text: string, date: string}>
     */
    public static function latest(int $limit = 5, ?string $path = null): array
    {
        $path ??= storage_path('logs/deploy.log');

        $log = self::tail($path);

        if ($log === '') {
            return [];
        }

        preg_match_all(
            '/^\[(\d{4}-\d{2}-\d{2}) \d{2}:\d{2}:\d{2}\] Deployed ([0-9a-f]{7,40}): (.+?)\s*$/m',
            $log,
            $matches,
            PREG_SET_ORDER,
        );

        $updates = [];

        // Newest first; a commit deployed twice is listed once.
        foreach (array_reverse($matches) as [, $date, $sha, $subject]) {
            if (isset($updates[$sha]) || ($update = self::present($subject)) === null) {
                continue;
            }

            $updates[$sha] = [
                'sha'  => $sha,
                'type' => $update['type'],
                'text' => $update['text'],
                'date' => date('M j, Y', strtotime($date)),
            ];

            if (count($updates) === $limit) {
                break;
            }
        }

        return array_values($updates);
    }

    /**
     * A commit subject as a reader would want it, or null when it is not one
     * for users: "feat: add X" becomes ['type' => 'New', 'text' => 'Add X'].
     *
     * @return array{type: string, text: string}|null
     */
    private static function present(string $subject): ?array
    {
        if (! preg_match('/^(\w+)(?:\([^)]*\))?!?:\s*(.+)$/', $subject, $parts)
            || ! isset(self::LABELS[strtolower($parts[1])])) {
            return null;
        }

        $text = preg_replace('/\s*\S+@\S+\.\S+/', '', $parts[2]);
        $text = trim(preg_replace('/\s{2,}/', ' ', $text));

        if ($text === '') {
            return null;
        }

        return [
            'type' => self::LABELS[strtolower($parts[1])],
            'text' => mb_strtoupper(mb_substr($text, 0, 1)) . mb_substr($text, 1),
        ];
    }

    private static function tail(string $path): string
    {
        if (! is_file($path) || ! is_readable($path)) {
            return '';
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return '';
        }

        $size = filesize($path) ?: 0;
        fseek($handle, max(0, $size - self::TAIL_BYTES));
        $tail = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $tail;
    }
}
