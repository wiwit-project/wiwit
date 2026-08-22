<?php

namespace App\Enums;

use Carbon\CarbonImmutable;
use Carbon\Constants\UnitValue;

/**
 * Interval granularity for the analytics time series.
 */
enum Interval: string
{
    case Day = 'day';

    case Week = 'week';

    case Month = 'month';

    public function format(CarbonImmutable $date): string
    {
        return match ($this) {
            self::Day => $date->format('Y-m-d'),
            self::Week => $date->format('o-\\WW'),
            self::Month => $date->format('Y-m'),
        };
    }

    /**
     * First day of the interval the given date falls in.
     */
    public function startOf(CarbonImmutable $date): CarbonImmutable
    {
        return match ($this) {
            self::Day => $date->startOfDay(),
            self::Week => $date->startOfWeek(UnitValue::MONDAY),
            self::Month => $date->startOfMonth(),
        };
    }

    /**
     * Last day of the interval the given date falls in.
     */
    public function endOf(CarbonImmutable $date): CarbonImmutable
    {
        return match ($this) {
            self::Day => $date->endOfDay(),
            self::Week => $this->startOf($date)->addDays(6)->endOfDay(),
            self::Month => $date->endOfMonth(),
        };
    }

    /**
     * Start of the interval immediately after the given one.
     */
    public function next(CarbonImmutable $intervalStart): CarbonImmutable
    {
        return match ($this) {
            self::Day => $intervalStart->addDay(),
            self::Week => $intervalStart->addWeek(),
            self::Month => $intervalStart->addMonth(),
        };
    }
}
