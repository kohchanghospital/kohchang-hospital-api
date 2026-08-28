<?php

namespace Database\Seeders;

use App\Models\OrganDonationPage;
use Illuminate\Database\Seeder;

class OrganDonationSeeder extends Seeder
{
    public function run(): void
    {
        $page = OrganDonationPage::query()->firstOrCreate([], [
            'eyebrow_text' => 'GIVE THE GIFT OF LIFE',
            'page_title' => 'บริจาคอวัยวะ',
            'headline' => 'สร้างกุศลผู้ให้ สร้างชีวิตใหม่ผู้รับ',
            'subheadline' => '1 ผู้ให้ ช่วยได้ 8 ชีวิต',
            'importance_title' => 'ความสำคัญของการบริจาคอวัยวะ',
            'importance_content' => '“การให้ชีวิตใหม่” ที่ยิ่งใหญ่ที่สุด ช่วยต่อลมหายใจให้ผู้ป่วยโรคเรื้อรังระยะสุดท้ายได้ถึง 8 รายต่อผู้บริจาค 1 ราย เป็นการเปลี่ยนชีวิตผู้รับให้ดีขึ้น ลดภาวะเจ็บป่วย และเป็นประโยชน์สูงสุดทางการแพทย์ ถือเป็นมหากุศลที่สร้างสรรค์สังคมและช่วยให้ผู้รับพ้นจากความทุกข์ทรมาน',
            'qualification_title' => 'คุณสมบัติผู้บริจาคอวัยวะ',
            'contact_title' => null,
            'contact_description' => null,
            'phone' => null,
            'external_url' => 'https://organdonate.redcross.or.th/',
            'external_url_label' => 'ลงทะเบียนบริจาคอวัยวะ',
        ]);

        if (!$page->wasRecentlyCreated || $page->organs()->exists() || $page->qualifications()->exists()) {
            return;
        }

        foreach (['หัวใจ', 'ไต', 'ตับ', 'ปอด', 'ตับอ่อน', 'เนื้อเยื่อ'] as $index => $title) {
            $page->organs()->create(['title' => $title, 'sort_order' => $index + 1, 'is_active' => true]);
        }

        $qualifications = [
            'ผู้บริจาคอวัยวะต้องมีอายุไม่เกิน 65 ปี',
            'เสียชีวิตจากสภาวะสมองตายด้วยสาเหตุต่าง ๆ',
            'ปราศจากโรคติดเชื้อ และโรคมะเร็ง',
            'ไม่เป็นโรคเรื้อรัง เช่น เบาหวาน, หัวใจ, โรคไต, ความดันโลหิตสูง, โรคตับ และไม่ติดสุรา',
            'อวัยวะที่จะนำไปปลูกถ่ายต้องทำงานได้ดี',
            'ปราศจากเชื้อที่ถ่ายทอดทางการปลูกถ่ายอวัยวะ เช่น ไวรัสตับอักเสบชนิดบี, ไวรัสเอดส์ ฯลฯ',
            'กรุณาแจ้งเรื่องการบริจาคอวัยวะแก่บุคคลในครอบครัวหรือญาติให้รับทราบด้วย',
        ];

        foreach ($qualifications as $index => $content) {
            $page->qualifications()->create(['content' => $content, 'sort_order' => $index + 1, 'is_active' => true]);
        }
    }
}
