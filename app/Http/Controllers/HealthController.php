<?php

namespace App\Http\Controllers;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'db' => $this->check(fn () => DB::select('select 1')),
            'redis' => $this->check(fn () => Redis::ping()),
            'storage' => $this->check(fn () => Storage::disk('local')->exists('') || true),
            'version' => trim((string) @file_get_contents(base_path('VERSION'))) ?: 'dev',
        ]);
    }

    private function check(Closure $fn): string
    {
        try {
            $fn();

            return 'ok';
        } catch (Throwable $e) {
            return 'error';
        }
    }
}
