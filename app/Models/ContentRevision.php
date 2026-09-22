<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\ContentRevisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One immutable historical snapshot of a content page's Markdown. Once
 * published, a revision's authored content is append-only evidence and is
 * never rewritten; rollback creates a new revision instead (POC-V6.1,
 * POC-V6.5, POC-V6.6).
 *
 * @property int $id
 * @property int $content_page_id
 * @property int $revision
 * @property string $title
 * @property string $body_markdown
 * @property array<string, mixed>|null $metadata
 * @property int|null $created_by_user_id
 * @property int|null $published_by_user_id
 * @property CarbonInterface|null $published_at
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable([
    'content_page_id',
    'revision',
    'title',
    'body_markdown',
    'metadata',
    'created_by_user_id',
    'published_by_user_id',
    'published_at',
])]
class ContentRevision extends Model
{
    /** @use HasFactory<ContentRevisionFactory> */
    use HasFactory;

    /** @return BelongsTo<ContentPage, $this> */
    public function contentPage(): BelongsTo
    {
        return $this->belongsTo(ContentPage::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by_user_id');
    }

    public function isDraft(): bool
    {
        return $this->published_at === null;
    }

    /**
     * Cast structured attributes.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'revision' => 'integer',
            'metadata' => 'array',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Once a revision has been published, its authored content becomes
     * immutable historical evidence. The publish transition itself (writing
     * `published_at`/`published_by_user_id` from null) remains allowed;
     * deletion is never allowed.
     */
    protected static function booted(): void
    {
        static::updating(static function (ContentRevision $revision): void {
            if ($revision->getOriginal('published_at') === null) {
                return;
            }

            $immutableFields = ['title', 'body_markdown', 'metadata', 'content_page_id', 'revision'];

            foreach ($immutableFields as $field) {
                if ($revision->isDirty($field)) {
                    throw new LogicException(
                        'Published content revisions are immutable.',
                    );
                }
            }
        });

        static::deleting(static function (ContentRevision $revision): never {
            throw new LogicException(
                'Content revisions cannot be deleted.',
            );
        });
    }
}
