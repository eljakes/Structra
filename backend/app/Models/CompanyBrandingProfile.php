<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanyBrandingProfile extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'logo_path',
        'dark_logo_path',
        'light_logo_path',
        'favicon_path',
        'login_background_path',
        'dashboard_background_path',
        'primary_color',
        'secondary_color',
        'accent_color',
        'sidebar_color',
        'button_color',
        'typography',
        'email_templates',
        'pdf_templates',
        'invoice_template',
        'quotation_template',
        'letterhead',
        'report_header',
        'watermark_path',
        'login_welcome_message',
        'company_motto',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'email_templates' => 'array',
            'pdf_templates' => 'array',
            'invoice_template' => 'array',
            'quotation_template' => 'array',
            'letterhead' => 'array',
            'report_header' => 'array',
        ];
    }
}
