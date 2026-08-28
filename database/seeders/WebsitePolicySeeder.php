<?php

namespace Database\Seeders;

use App\Models\WebsitePolicy;
use Illuminate\Database\Seeder;

class WebsitePolicySeeder extends Seeder
{
    public function run(): void
    {
        $policies = [
            ['policy_type' => 'privacy_policy', 'title_th' => 'นโยบายการคุ้มครองข้อมูลส่วนบุคคล', 'title_en' => 'Privacy Policy'],
            ['policy_type' => 'cookie_policy', 'title_th' => 'นโยบายคุกกี้', 'title_en' => 'Cookie Policy'],
            ['policy_type' => 'terms_of_service', 'title_th' => 'ข้อกำหนดการให้บริการ', 'title_en' => 'Terms of Service'],
        ];

        foreach ($policies as $policy) {
            WebsitePolicy::query()->firstOrCreate(
                ['policy_type' => $policy['policy_type']],
                $policy + ['content_th' => null, 'content_en' => null, 'is_active' => true]
            );
        }
    }
}
