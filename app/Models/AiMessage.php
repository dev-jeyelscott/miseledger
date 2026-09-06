<?php

namespace App\Models;

use App\Enums\AiMessageRole;
use Database\Factories\AiMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $ai_conversation_id
 * @property AiMessageRole $role
 * @property int $sequence
 * @property string $content
 * @property Carbon|null $created_at
 */
#[Fillable(['ai_conversation_id', 'role', 'sequence', 'content'])]
class AiMessage extends Model
{
    /** @use HasFactory<AiMessageFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /** @return BelongsTo<AiConversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }

    /**
     * Limit a query to one private conversation.
     *
     * @param  Builder<AiMessage>  $query
     * @return Builder<AiMessage>
     */
    public function scopeForConversation(
        Builder $query,
        AiConversation $conversation,
    ): Builder {
        return $query->whereBelongsTo($conversation, 'conversation');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'role' => AiMessageRole::class,
            'created_at' => 'datetime',
        ];
    }
}
