<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Content;
use App\Models\ContentTranslation;
use Illuminate\Http\Request;
use Mews\Purifier\Facades\Purifier;

class ContentController extends Controller
{
    public function show(Request $request, $slug)
    {
        $lang = $request->query('lang', 'th');

        $content = Content::where('slug', $slug)
            ->with(['translation' => fn($q) => $q->where('lang', $lang)])
            ->firstOrFail();

        return response()->json([
            'slug' => $content->slug,
            'type' => $content->type,
            'title' => optional($content->translation)->title,
            'body' => optional($content->translation)->body,
        ]);
    }

    public function getByType($type)
    {
        $order = [
            'district-history',
            'district-establishment',
            'hospital-history'
        ];

        return ContentTranslation::whereHas('content', function ($q) use ($type) {
            $q->where('type', $type);
        })
            ->where('lang', request('lang', 'th'))
            ->with('content')
            ->get()
            ->sortBy(function ($item) use ($order) {
                return array_search($item->content->slug, $order);
            })
            ->values()
            ->map(function ($item) {
                return [
                    'content_id' => $item->content_id,
                    'title' => $item->title,
                    'body' => $item->body,
                ];
            });
    }


    public function updateByType(Request $request, $type)
    {
        $request->validate([
            'lang' => 'required|string',
            'contents' => 'required|array',
            'contents.*.content_id' => 'required|integer',
            'contents.*.body' => 'nullable|string',
        ]);

        foreach ($request->contents as $item) {

            $body = html_entity_decode($item['body']);

            ContentTranslation::where('content_id', $item['content_id'])
                ->where('lang', $request->lang)
                ->update([
                    'body' => Purifier::clean($body, 'ckeditor')
                ]);
        }

        return response()->json(['status' => true]);
    }
}
