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
            'storage' => $this->check(function () {
                Storage::disk('local')->put('healthz.tmp', 'ok');
                if (Storage::disk('local')->get('healthz.tmp') !== 'ok') {
                    throw new \RuntimeException('storage read mismatch');
                }
                Storage::disk('local')->delete('healthz.tmp');
            }),
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
