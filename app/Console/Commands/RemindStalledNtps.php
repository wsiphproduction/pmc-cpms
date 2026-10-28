<?php

namespace App\Console\Commands;

use App\Models\ProjectNtp;
use App\Support\ApprovalFlow;
use Illuminate\Console\Command;

/**
 * Reminds the signers of every NTP that has sat at the same approval step for
 * a week or more, and again each week after, until they act on it. Scheduled
 * each weekday morning in routes/console.php.
 *
 *   php artisan ntps:remind-stalled
 *   php artisan ntps:remind-stalled --days=3   # a shorter wait, for testing
 */
class RemindStalledNtps extends Command
{
    protected $signature = 'ntps:remind-stalled
                            {--days=7 : How long an NTP may wait at one step before its signers are reminded}';

    protected $description = 'Remind approvers of NTPs that have waited on them for a week or more';

    public function handle(ApprovalFlow $flow): int
    {
        $days = max(1, (int) $this->option('days'));
        $sent = 0;

        // `whereHas('project')` leaves out NTPs whose project was deleted.
        ProjectNtp::where('status', 'pending_review')
            ->whereHas('project')
            ->with(['project', 'approvals'])
            ->each(function (ProjectNtp $ntp) use ($flow, $days, &$sent) {
                if ($flow->remindStalledNtp($ntp, $days)) {
                    $sent++;
                    $this->line("Reminded the signers of NTP {$ntp->ntp_no}.");
                }
            });

        $this->info("{$sent} reminder(s) sent.");

        return self::SUCCESS;
    }
}
