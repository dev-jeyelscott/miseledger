<?php

namespace App\Models;

use App\Enums\ContentKind;
use Carbon\CarbonInterface;
use Database\Factories\ContentPageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A versioned marketing/legal content slot. `published_revision_id` is the
 * sole pointer public routes may render; historical revisions are never
 * overwritten and rollback always creates a new draft revision (POC-V6.1).
 *
 * @property int $id
 * @property ContentKind $kind
 * @property string $key
 * @property string $slug
 * @property string $title
 * @property int|null $published_revision_id
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable([
    'kind',
    'key',
    'slug',
    'title',
    'published_revision_id',
])]
class ContentPage extends Model
{
    /** @use HasFactory<ContentPageFactory> */
    use HasFactory;

    /** @return HasMany<ContentRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(ContentRevision::class)
            ->orderByDesc('revision');
    }

    /** @return BelongsTo<ContentRevision, $this> */
    public function publishedRevision(): BelongsTo
    {
        return $this->belongsTo(ContentRevision::class, 'published_revision_id');
    }

    /**
     * The single revision currently open for editing, when one exists. Only
     * one unpublished revision may exist per page at a time.
     */
    public function latestDraft(): ?ContentRevision
    {
        return $this->revisions
            ->firstWhere('published_at', null);
    }

    /**
     * Cast structured attributes.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ContentKind::class,
            'published_revision_id' => 'integer',
        ];
    }
}
