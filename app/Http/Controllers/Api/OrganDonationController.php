<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrganDonationPage;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrganDonationController extends Controller
{
    public function showPublic(): JsonResponse
    {
        $page = OrganDonationPage::query()->with([
            'organs' => fn ($query) => $query->where('is_active', true),
            'qualifications' => fn ($query) => $query->where('is_active', true),
        ])->first();

        return response()->json([
            'status' => true,
            'data' => $page ? $this->publicData($page) : null,
        ]);
    }

    public function showAdmin(): JsonResponse
    {
        $page = OrganDonationPage::query()->with(['organs', 'qualifications'])->firstOrFail();

        return response()->json(['status' => true, 'data' => $page]);
    }

    public function update(Request $request): JsonResponse
    {
        $page = OrganDonationPage::query()->firstOrFail();
        $validated = $request->validate([
            'eyebrow_text' => ['required', 'string', 'max:255'],
            'page_title' => ['required', 'string', 'max:255'],
            'headline' => ['required', 'string', 'max:255'],
            'subheadline' => ['nullable', 'string', 'max:255'],
            'importance_title' => ['required', 'string', 'max:255'],
            'importance_content' => ['required', 'string', 'max:20000'],
            'qualification_title' => ['required', 'string', 'max:255'],
            'contact_title' => ['nullable', 'string', 'max:255'],
            'contact_description' => ['nullable', 'string', 'max:10000'],
            'phone' => ['nullable', 'string', 'max:100'],
            'external_url' => ['nullable', 'url:http,https', 'max:2048'],
            'external_url_label' => ['nullable', 'string', 'max:255'],
            'organs' => ['required', 'array', 'max:100'],
            'organs.*.id' => ['nullable', 'integer', 'distinct'],
            'organs.*.title' => ['required', 'string', 'max:255'],
            'organs.*.sort_order' => ['required', 'integer', 'min:1'],
            'organs.*.is_active' => ['required', 'boolean'],
            'qualifications' => ['required', 'array', 'max:100'],
            'qualifications.*.id' => ['nullable', 'integer', 'distinct'],
            'qualifications.*.content' => ['required', 'string', 'max:5000'],
            'qualifications.*.sort_order' => ['required', 'integer', 'min:1'],
            'qualifications.*.is_active' => ['required', 'boolean'],
        ]);

        $page = DB::transaction(function () use ($page, $validated) {
            $page->update(collect($validated)->except(['organs', 'qualifications'])->map(
                fn ($value) => is_string($value) ? (trim($value) !== '' ? trim($value) : null) : $value
            )->all());

            $this->syncItems($page->organs(), $validated['organs'], 'title');
            $this->syncItems($page->qualifications(), $validated['qualifications'], 'content');

            return $page->load(['organs', 'qualifications']);
        });

        return response()->json([
            'status' => true,
            'message' => 'บันทึกข้อมูลบริจาคอวัยวะเรียบร้อย',
            'data' => $page,
        ]);
    }

    private function syncItems(HasMany $relation, array $items, string $contentField): void
    {
        $keptIds = [];

        foreach (array_values($items) as $index => $item) {
            $model = isset($item['id']) ? (clone $relation)->whereKey($item['id'])->first() : null;
            $model ??= $relation->make();
            $model->fill([
                $contentField => trim($item[$contentField]),
                'sort_order' => $index + 1,
                'is_active' => $item['is_active'],
            ])->save();
            $keptIds[] = $model->getKey();
        }

        $query = (clone $relation)->getQuery();
        if ($keptIds === []) {
            $query->delete();
        } else {
            $query->whereNotIn('id', $keptIds)->delete();
        }
    }

    private function publicData(OrganDonationPage $page): array
    {
        return [
            'eyebrow_text' => $page->eyebrow_text,
            'page_title' => $page->page_title,
            'headline' => $page->headline,
            'subheadline' => $page->subheadline,
            'importance' => [
                'title' => $page->importance_title,
                'content' => $page->importance_content,
            ],
            'organs' => $page->organs->map->only(['id', 'title', 'sort_order'])->values(),
            'qualifications' => [
                'title' => $page->qualification_title,
                'items' => $page->qualifications->map->only(['id', 'content', 'sort_order'])->values(),
            ],
            'contact' => [
                'title' => $page->contact_title,
                'description' => $page->contact_description,
                'phone' => $page->phone,
                'external_url' => $page->external_url,
                'external_url_label' => $page->external_url_label,
            ],
        ];
    }
}
