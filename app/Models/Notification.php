<?php

namespace App\Models;

use App\Mail\ProjectRequestActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class Notification extends Model
{
    protected $fillable = ['recipient', 'message', 'link', 'is_read'];

    protected $casts = [
        'recipient' => 'integer',
        'is_read' => 'boolean',
    ];

    public function recipientUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient');
    }

    /**
     * @param int|array<int|User>|Collection<int, User|int> $recipients
     */
    public static function notify(int|array|Collection $recipients, string $message, ?string $link = null): void
    {
        foreach (self::recipientIds($recipients) as $recipient) {
            static::create([
                'recipient' => $recipient,
                'message' => $message,
                'link' => $link,
            ]);
        }
    }

    /**
     * The bell notification for a project request activity, mailed as well.
     * A queue or mail outage must not undo what the activity already did, so
     * a mail that cannot be queued is logged and the rest go on.
     *
     * @param int|array<int|User>|Collection<int, User|int> $recipients
     */
    public static function notifyAboutRequest(ProjectRequest $projectRequest, int|array|Collection $recipients, string $message, ?string $link = null): void
    {
        $link ??= route('requests.show', $projectRequest->id, absolute: false);

        static::notify($recipients, $message, $link);

        $url = url($link);

        User::whereIn('id', self::recipientIds($recipients)->unique())
            ->whereNotNull('email')
            ->get()
            ->each(function (User $user) use ($projectRequest, $message, $url) {
                try {
                    Mail::to($user->email)->send(new ProjectRequestActivity($projectRequest, $user, $message, $url));
                } catch (\Throwable $e) {
                    Log::error("Project request email to {$user->email} could not be queued for request #{$projectRequest->id}: " . $e->getMessage());
                }
            });
    }

    /** @param int|array<int|User>|Collection<int, User|int> $recipients */
    private static function recipientIds(int|array|Collection $recipients): Collection
    {
        return collect($recipients instanceof Collection || is_array($recipients) ? $recipients : [$recipients])
            ->map(fn ($recipient) => $recipient instanceof User ? $recipient->id : $recipient)
            ->values();
    }
}
