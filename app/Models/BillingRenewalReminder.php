<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['billing_invoice_id', 'days_before_due', 'sent_at'])]
class BillingRenewalReminder extends Model
{
    /** @return BelongsTo<BillingInvoice, $this> */
    public function billingInvoice(): BelongsTo
    {
        return $this->belongsTo(BillingInvoice::class);
    }

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }
}
