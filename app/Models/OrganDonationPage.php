<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrganDonationPage extends Model
{
    protected $fillable = [
        'eyebrow_text', 'page_title', 'headline', 'subheadline',
        'importance_title', 'importance_content', 'qualification_title',
        'contact_title', 'contact_description', 'phone',
        'external_url', 'external_url_label',
    ];

    public function organs(): HasMany
    {
        return $this->hasMany(OrganDonationOrgan::class)->orderBy('sort_order')->orderBy('id');
    }

    public function qualifications(): HasMany
    {
        return $this->hasMany(OrganDonationQualification::class)->orderBy('sort_order')->orderBy('id');
    }
}
