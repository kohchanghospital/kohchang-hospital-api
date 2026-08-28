<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WebsitePolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Mews\Purifier\Facades\Purifier;

class WebsitePolicyController extends Controller
{
    public function publicIndex(): JsonResponse
    {
        $policies = WebsitePolicy::query()->where('is_active', true)->orderBy('id')
            ->get(['policy_type', 'title_th', 'title_en', 'updated_at']);
        return response()->json(['status' => true, 'data' => $policies]);
    }

    public function publicShow(string $policyType): JsonResponse
    {
        $policy = WebsitePolicy::query()->where('policy_type', $policyType)
            ->where('is_active', true)->firstOrFail();
        return response()->json(['status' => true, 'data' => $policy->only([
            'policy_type', 'title_th', 'title_en', 'content_th', 'content_en', 'updated_at',
        ])]);
    }

    public function adminIndex(): JsonResponse
    {
        $policies = WebsitePolicy::query()->with('updater:id,name,email')->orderBy('id')->get();
        return response()->json(['status' => true, 'data' => $policies]);
    }

    public function update(Request $request, string $policyType): JsonResponse
    {
        $policy = WebsitePolicy::query()->where('policy_type', $policyType)->firstOrFail();
        $validated = $request->validate([
            'title_th' => ['required', 'string', 'max:255'],
            'title_en' => ['nullable', 'string', 'max:255'],
            'content_th' => ['nullable', 'string', 'max:1000000'],
            'content_en' => ['nullable', 'string', 'max:1000000'],
            'is_active' => ['required', 'boolean'],
        ]);

        foreach (['title_th', 'title_en'] as $field) {
            $validated[$field] = isset($validated[$field]) && trim($validated[$field]) !== '' ? trim($validated[$field]) : null;
        }
        foreach (['content_th', 'content_en'] as $field) {
            $validated[$field] = isset($validated[$field]) && trim($validated[$field]) !== ''
                ? Purifier::clean($validated[$field], 'tinymce') : null;
        }

        $policy->update($validated + ['updated_by' => $request->user()->id]);
        $policy->load('updater:id,name,email');
        return response()->json(['status' => true, 'message' => 'บันทึกนโยบายสำเร็จ', 'data' => $policy]);
    }
}
