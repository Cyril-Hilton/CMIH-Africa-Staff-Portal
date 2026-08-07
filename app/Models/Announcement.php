<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'body',
        'pinned',
        'audience_type',
        'recipient_ids',
        'department_keys',
    ];

    protected function casts(): array
    {
        return [
            'pinned' => 'boolean',
            'recipient_ids' => 'array',
            'department_keys' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plainBody(?int $limit = null): string
    {
        $text = self::cleanBodyText($this->body ?? '');

        return $limit ? Str::limit($text, $limit) : $text;
    }

    public static function cleanBodyText(string $body): string
    {
        $body = self::decodeBodyEntities($body);
        $body = preg_replace('/<(br|\/p|\/div|\/li|\/h[1-6])\b[^>]*>/i', "\n", $body) ?? $body;
        $body = preg_replace('/<li\b[^>]*>/i', '- ', $body) ?? $body;

        $text = strip_tags($body);
        $text = self::decodeBodyEntities($text);
        $text = str_replace("\xc2\xa0", ' ', $text);
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/[ \t]*\r?\n[ \t]*/', "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", trim($text)) ?? trim($text);

        return $text;
    }

    private static function decodeBodyEntities(string $text): string
    {
        for ($i = 0; $i < 3; $i++) {
            $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if ($decoded === $text) {
                break;
            }

            $text = $decoded;
        }

        return $text;
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $department = User::normalizeDepartmentKey($user->department);

        return $query->where(function (Builder $visibilityQuery) use ($user, $department) {
            $visibilityQuery
                ->where('audience_type', 'all')
                ->orWhereNull('audience_type')
                ->orWhere(function (Builder $recipientQuery) use ($user) {
                    $recipientQuery
                        ->where('audience_type', 'selected')
                        ->whereJsonContains('recipient_ids', (int) $user->id);
                })
                ->orWhere(function (Builder $departmentQuery) use ($department) {
                    $departmentQuery
                        ->where('audience_type', 'departments')
                        ->whereJsonContains('department_keys', $department);
                });
        });
    }
}
