<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

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
        $items = $recipients instanceof Collection || is_array($recipients) ? $recipients : [$recipients];

        foreach ($items as $recipient) {
            static::create([
                'recipient' => $recipient instanceof User ? $recipient->id : $recipient,
                'message' => $message,
                'link' => $link,
            ]);
        }
    }
}
