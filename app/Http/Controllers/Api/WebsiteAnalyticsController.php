<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

class WebsiteAnalyticsController extends Controller
{
    public function heartbeat(Request $request)
    {
        $expected = config('analytics.ingest_key');
        $provided = $request->header('X-Analytics-Key', '');
        if (!is_string($expected) || strlen($expected) < 32 || !hash_equals($expected, $provided)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate(['visitor_uuid' => ['required', 'uuid']]);
        $uuid = strtolower($data['visitor_uuid']);
        $key = 'analytics-heartbeat:'.sha1($uuid.'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 120)) {
            return response()->json(['message' => 'Too many requests'], 429);
        }
        RateLimiter::hit($key, 60);
        $now = now();
        $date = $now->copy()->setTimezone(config('analytics.timezone'))->toDateString();

        DB::transaction(function () use ($uuid, $now, $date) {
            DB::table('website_visitors')->upsert([[
                'visitor_uuid' => $uuid,
                'first_seen_at' => $now,
                'last_seen_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]], ['visitor_uuid'], ['last_seen_at', 'updated_at']);
            $visitorId = DB::table('website_visitors')->where('visitor_uuid', $uuid)->value('id');
            DB::table('website_visitor_days')->insertOrIgnore([
                'visitor_id' => $visitorId,
                'visit_date' => $date,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }, 3);

        return response()->noContent();
    }

    public function summary()
    {
        $now = now();
        $date = $now->copy()->setTimezone(config('analytics.timezone'))->toDateString();
        $firstSeen = DB::table('website_visitors')->min('first_seen_at');

        return response()->json([
            'total_visitors' => DB::table('website_visitors')->count(),
            'online_visitors' => DB::table('website_visitors')->where('last_seen_at', '>=', $now->copy()->subMinutes(5))->count(),
            'today_visitors' => DB::table('website_visitor_days')->where('visit_date', $date)->count(),
            'tracking_started_at' => $firstSeen ? Carbon::parse($firstSeen, config('app.timezone'))->toIso8601String() : null,
            'as_of' => $now->toIso8601String(),
        ])->header('Cache-Control', 'private, no-store');
    }
}
