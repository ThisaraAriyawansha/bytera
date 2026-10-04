<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Throwable;

class DateRange
{
    /**
     * History lists default to the last 30 days (SPEC §2.3).
     */
    public const DEFAULT_DAYS = 30;

    /**
     * Resolve the From / To filter from the request, falling back to the last 30 days.
     * `from` is the start of its day and `to` the end of its day, ready for `whereBetween`.
     *
     * @return array{from: CarbonImmutable, to: CarbonImmutable}
     */
    public static function fromRequest(Request $request, string $fromKey = 'from', string $toKey = 'to'): array
    {
        $to = self::parse($request->query($toKey)) ?? CarbonImmutable::today();
        $from = self::parse($request->query($fromKey)) ?? $to->subDays(self::DEFAULT_DAYS);

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        return ['from' => $from->startOfDay(), 'to' => $to->endOfDay()];
    }

    private static function parse(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', $value) ?: null;
        } catch (Throwable) {
            return null;
        }
    }
}
