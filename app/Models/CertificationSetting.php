<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CertificationSetting extends Model
{
    protected $fillable = [
        'institution_name', 'office_name', 'heading', 'additional_text',
        'first_signatory_name', 'first_signatory_title',
        'second_signatory_name', 'second_signatory_title',
        'allow_non_scopus',
    ];

    protected $attributes = [
        'institution_name' => 'Leyte Normal University',
        'office_name' => 'Office of Research and Innovation',
        'heading' => 'Certificate of Research Involvement',
        'allow_non_scopus' => false,
    ];

    protected function casts(): array
    {
        return ['allow_non_scopus' => 'boolean'];
    }
}
