<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AnnouncementTypeSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('announcement_types')->insert([
            ['name' => 'ข่าวสาร/ประชาสัมพันธ์'],
            ['name' => 'จัดซื้อจัดจ้าง'],
        ]);
    }
}
