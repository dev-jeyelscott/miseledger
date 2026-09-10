<?php

namespace App\Models;

use Database\Factories\ProblemReportAttachmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $problem_report_id
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['problem_report_id', 'disk', 'path', 'original_name', 'mime_type', 'size'])]
class ProblemReportAttachment extends Model
{
    /** @use HasFactory<ProblemReportAttachmentFactory> */
    use HasFactory;

    protected $hidden = ['disk', 'path'];

    /** @return BelongsTo<ProblemReport, $this> */
    public function report(): BelongsTo
    {
        return $this->belongsTo(ProblemReport::class, 'problem_report_id');
    }
}
