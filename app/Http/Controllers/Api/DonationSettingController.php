<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DonationSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DonationSettingController extends Controller
{
    private array $defaults = [
        'bank_name' => 'ธนาคารกรุงไทย',
        'account_name' => 'โรงพยาบาลเกาะช้าง',
        'account_number' => 'XXX-X-XXXXX-X',
        'qr_code_image' => null,
        'email' => 'kohchanghealth123@gmail.com',
        'phone' => '039-586-131',
        'fax' => '039-586-131, 039-586-160',
        'facebook' => 'https://www.facebook.com/kochang.hospital/',
        'organization_name' => 'โรงพยาบาลเกาะช้าง',
        'description' => 'สมทบทุนเพื่อสนับสนุนการดำเนินงานของโรงพยาบาล',
        'address' => '21/1 หมู่ที่ 2 ตำบลเกาะช้าง อำเภอเกาะช้าง จังหวัดตราด 23170',
        'google_map_embed_url' => 'https://maps.google.com/maps?q=โรงพยาบาลเกาะช้าง&z=15&output=embed',
        'latitude' => 12.1030000,
        'longitude' => 102.3540000,
    ];

    public function show()
    {
        return response()->json([
            'status' => true,
            'data' => $this->serializeSetting($this->firstSetting()),
        ]);
    }

    public function update(Request $request)
    {
        $setting = $this->firstSetting();

        $validated = $request->validate([
            'bank_name' => ['required', 'string', 'max:255'],
            'account_name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'max:100'],
            'qr_code_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:100'],
            'fax' => ['required', 'string', 'max:100'],
            'facebook' => ['required', 'url', 'max:255'],
            'organization_name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'address' => ['required', 'string'],
            'google_map_embed_url' => ['required', 'url'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        unset($validated['qr_code_image']);

        if ($request->hasFile('qr_code_image')) {
            if ($setting->qr_code_image) {
                Storage::disk('public')->delete($setting->qr_code_image);
            }

            $file = $request->file('qr_code_image');
            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $validated['qr_code_image'] = $file->storeAs('donation-settings', $filename, 'public');
        }

        $setting->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'บันทึกข้อมูลสมทบทุนเรียบร้อย',
            'data' => $this->serializeSetting($setting->refresh()),
        ]);
    }

    private function firstSetting(): DonationSetting
    {
        return DonationSetting::query()->firstOrCreate([], $this->defaults);
    }

    private function serializeSetting(DonationSetting $setting): array
    {
        return [
            ...$setting->toArray(),
            'qr_code_image_url' => $setting->qr_code_image
                ? Storage::disk('public')->url($setting->qr_code_image)
                : null,
        ];
    }
}
