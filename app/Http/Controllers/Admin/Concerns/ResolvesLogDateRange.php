<?php

namespace App\Http\Controllers\Admin\Concerns;

use Carbon\Carbon;
use Illuminate\Http\Request;

trait ResolvesLogDateRange
{
    /**
     * @return array{0: Carbon, 1: Carbon, 2: int}
     */
    protected function logDateRange(Request $request, int $defaultDays = 7): array
    {
        $days = (int) $request->input('days', $defaultDays);
        $days = in_array($days, [1, 7, 14, 28, 30, 60, 90], true) ? $days : $defaultDays;

        $since = $request->filled('date_from')
            ? Carbon::parse($request->input('date_from'))->startOfDay()
            : now()->subDays($days)->startOfDay();

        $until = $request->filled('date_to')
            ? Carbon::parse($request->input('date_to'))->endOfDay()
            : now()->endOfDay();

        return [$since, $until, $days];
    }
}
