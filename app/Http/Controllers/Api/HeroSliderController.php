<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HeroSlider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class HeroSliderController extends Controller
{
    public function index()
    {
        return response()->json(['data' => HeroSlider::where('is_active', true)->orderBy('display_order')->orderBy('id')->get()]);
    }

    public function adminIndex()
    {
        return response()->json(['data' => HeroSlider::orderBy('display_order')->orderBy('id')->get()]);
    }

    public function store(Request $request) { return $this->save($request, new HeroSlider); }
    public function update(Request $request, HeroSlider $heroSlider) { return $this->save($request, $heroSlider); }

    private function save(Request $request, HeroSlider $slide)
    {
        $data = $request->validate([
            'desktop_image' => [$slide->exists ? 'sometimes' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=8000,max_height=8000'],
            'mobile_image' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=8000,max_height=8000'],
            'remove_mobile_image' => ['sometimes', 'boolean'],
            'title' => ['nullable', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:2000'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'button_text' => ['nullable', 'string', 'max:100'],
            'button_url' => ['nullable', 'string', 'max:2048', function ($attribute, $value, $fail) {
                if (preg_match('/[\\x00-\\x20\\x7f\\\\\\\\]/', $value) ||
                    !(preg_match('~^/(?!/)~', $value) || (filter_var($value, FILTER_VALIDATE_URL) && in_array(strtolower(parse_url($value, PHP_URL_SCHEME) ?? ''), ['http', 'https'])))) {
                    $fail('Use an http(s) URL or a path starting with a single /.');
                }
            }],
            'text_alignment' => ['sometimes', Rule::in(['left', 'center', 'right'])],
            'overlay_enabled' => ['sometimes', 'boolean'],
            'display_order' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $created = !$slide->exists;
        $newPaths = [];
        $oldPaths = [];
        unset($data['remove_mobile_image'], $data['desktop_image'], $data['mobile_image']);
        try {
            foreach (['desktop_image', 'mobile_image'] as $field) {
                if ($request->hasFile($field)) {
                    $path = $request->file($field)->store('hero-sliders', 'public');
                    if (!$path) { throw new \RuntimeException('Unable to store image.'); }
                    $newPaths[] = $path;
                    $data[$field] = $path;
                }
            }
            if ($request->boolean('remove_mobile_image') && !$request->hasFile('mobile_image')) {
                $data['mobile_image'] = null;
            }
            DB::transaction(function () use (&$slide, $data, &$oldPaths) {
                // Lock current paths so concurrent replacements cannot orphan images.
                if ($slide->exists) {
                    $slide = HeroSlider::whereKey($slide->id)->lockForUpdate()->firstOrFail();
                }
                foreach (['desktop_image', 'mobile_image'] as $field) {
                    if (array_key_exists($field, $data)) { $oldPaths[] = $slide->$field; }
                }
                $slide->fill($data)->save();
            });
        } catch (\Throwable $e) {
            foreach ($newPaths as $path) { $this->deleteUnused($path); }
            throw $e;
        }
        foreach ($oldPaths as $path) { $this->deleteUnused($path); }
        return response()->json(['data' => $slide->fresh()], $created ? 201 : 200);
    }

    public function reorder(Request $request)
    {
        $data = $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['required', 'integer', 'distinct', 'exists:hero_sliders,id']]);
        DB::transaction(function () use ($data) {
            $all = HeroSlider::orderBy('id')->lockForUpdate()->pluck('id');
            $requested = array_map('intval', $data['ids']);
            sort($requested);
            abort_unless($all->all() === $requested, 409, 'Slides changed. Reload before reordering.');
            foreach ($data['ids'] as $order => $id) { HeroSlider::whereKey($id)->update(['display_order' => $order]); }
        });
        return $this->adminIndex();
    }

    public function destroy(HeroSlider $heroSlider)
    {
        $paths = DB::transaction(function () use ($heroSlider) {
            $current = HeroSlider::whereKey($heroSlider->id)->lockForUpdate()->firstOrFail();
            $paths = [$current->desktop_image, $current->mobile_image];
            $current->delete();
            return $paths;
        });
        foreach ($paths as $path) { $this->deleteUnused($path); }
        return response()->noContent();
    }

    private function deleteUnused(?string $path): void
    {
        if ($path && str_starts_with($path, 'hero-sliders/') && !HeroSlider::where('desktop_image', $path)->orWhere('mobile_image', $path)->exists()) {
            Storage::disk('public')->delete($path);
        }
    }
}
