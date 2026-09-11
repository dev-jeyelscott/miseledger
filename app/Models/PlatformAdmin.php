<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Identity-level grant authorizing access to the MiseLedger platform console.
 */
#[Fillable(['user_id'])]
class PlatformAdmin extends Model
{
    /**
     * Get the MiseLedger user holding this platform grant.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
