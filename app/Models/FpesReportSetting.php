<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FpesReportSetting extends Model
{
    protected $fillable = [
        'institution_name',
        'office_name',
        'logo_path',
        'university_logo_path',
        'report_heading',
        'report_subheading',
        'prepared_by_title',
        'prepared_by_name',
        'prepared_by_user_id',
        'reviewed_by_title',
        'reviewed_by_name',
        'reviewed_by_user_id',
        'approved_by_title',
        'approved_by_name',
        'approved_by_user_id',
        'footer_text',
    ];

    public function preparedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by_user_id');
    }

    public function reviewedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
