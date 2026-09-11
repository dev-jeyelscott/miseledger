<?php

namespace App\Models;

use App\Enums\ProblemReportStatus;
use Database\Factories\ProblemReportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $reference
 * @property int $user_id
 * @property int|null $organization_id
 * @property string|null $organization_name_snapshot
 * @property string|null $title
 * @property string $description
 * @property ProblemReportStatus $status
 * @property string|null $notion_id
 * @property string|null $notion_status
 * @property Carbon|null $notion_synced_at
 * @property Carbon|null $notion_last_checked_at
 * @property string|null $notion_check_error
 * @property Carbon|null $email_notified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['reference', 'user_id', 'organization_id', 'organization_name_snapshot', 'title', 'description', 'status'])]
class ProblemReport extends Model
{
    /** @use HasFactory<ProblemReportFactory> */
    use HasFactory;

    protected $hidden = [
        'notion_id',
        'notion_status',
        'notion_synced_at',
        'notion_last_checked_at',
        'notion_check_error',
        'email_notified_at',
        'user_id',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class)->withDefault();
    }

    /** @return HasMany<ProblemReportAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(ProblemReportAttachment::class);
    }

    /**
     * Cast local business and synchronization timestamps to their domain types.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProblemReportStatus::class,
            'notion_synced_at' => 'datetime',
            'notion_last_checked_at' => 'datetime',
            'email_notified_at' => 'datetime',
        ];
    }
}
