<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteSettingController extends Controller
{
    private const DEFAULTS = [
        'show_mourning_ribbon' => true,
    ];

    public function publicIndex(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => $this->settings(),
        ]);
    }

    public function adminIndex(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'data' => $this->settings(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'show_mourning_ribbon' => ['required', 'boolean'],
        ]);

        SiteSetting::query()->updateOrCreate(
            ['key' => 'show_mourning_ribbon'],
            ['value' => $validated['show_mourning_ribbon']],
        );

        return response()->json([
            'status' => true,
            'message' => 'บันทึกการตั้งค่าเว็บไซต์สำเร็จ',
            'data' => $this->settings(),
        ]);
    }

    private function settings(): array
    {
        $stored = SiteSetting::query()
            ->whereIn('key', array_keys(self::DEFAULTS))
            ->pluck('value', 'key')
            ->all();

        return array_replace(self::DEFAULTS, $stored);
    }
}
