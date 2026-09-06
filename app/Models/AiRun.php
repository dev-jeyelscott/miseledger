<?php

namespace App\Models;

use App\Enums\AiProvider;
use App\Enums\AiRunStatus;
use Database\Factories\AiRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $ai_conversation_id
 * @property int $user_id
 * @property int|null $ai_provider_connection_id
 * @property int|null $ai_provider_connection_user_id
 * @property AiProvider $provider
 * @property string|null $provider_run_id
 * @property string|null $model
 * @property AiRunStatus $status
 * @property string|null $error_code
 * @property int|null $input_tokens
 * @property int|null $output_tokens
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'ai_conversation_id',
    'user_id',
    'ai_provider_connection_id',
    'ai_provider_connection_user_id',
    'provider',
    'provider_run_id',
    'model',
    'status',
    'error_code',
    'input_tokens',
    'output_tokens',
    'metadata',
    'started_at',
    'finished_at',
])]
class AiRun extends Model
{
    /** @use HasFactory<AiRunFactory> */
    use HasFactory;

    /** @return BelongsTo<AiConversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<AiProviderConnection, $this> */
    public function providerConnection(): BelongsTo
    {
        return $this->belongsTo(AiProviderConnection::class, 'ai_provider_connection_id');
    }

    /** @return HasMany<AiToolCall, $this> */
    public function toolCalls(): HasMany
    {
        return $this->hasMany(AiToolCall::class);
    }

    /**
     * Limit a query to runs attached to one private conversation.
     *
     * @param  Builder<AiRun>  $query
     * @return Builder<AiRun>
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
            'provider' => AiProvider::class,
            'status' => AiRunStatus::class,
            'metadata' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
