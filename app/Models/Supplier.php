<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Supplier extends Model
{
    use HasFactory;

    /** Added one-by-one in Master Data by PMD. */
    public const SOURCE_PMD = 'pmd';

    /** Brought in wholesale by the CSV importer. */
    public const SOURCE_IMPORT = 'import';

    protected $fillable = [
        'company',
        'source',
        'accredited',
        'email',
        'telephone_no',
        'mobile_no',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'accredited' => 'boolean',
        'is_active'  => 'boolean',
        'created_by' => 'integer',
    ];

    /**
     * Split a stored contact string into individual addresses.
     *
     * A supplier commonly quotes through several mailboxes, so `email` holds a
     * comma- (or semicolon-) separated list. Everything that sends to a supplier
     * goes through here rather than parsing the column itself.
     *
     * @return array<int, string>
     */
    public static function parseEmails(?string $value): array
    {
        return collect(preg_split('/[,;]+/', (string) $value) ?: [])
            ->map(fn ($email) => trim($email))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** The stored list, normalized back to one canonical string. */
    public static function normalizeEmails(?string $value): ?string
    {
        return implode(', ', self::parseEmails($value)) ?: null;
    }

    /** @return array<int, string> */
    public function getEmailsAttribute(): array
    {
        return self::parseEmails($this->email);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Only the suppliers PMD maintains itself — what Master Data lists. */
    public function scopePmd(Builder $query): Builder
    {
        return $query->where('source', self::SOURCE_PMD);
    }
}
