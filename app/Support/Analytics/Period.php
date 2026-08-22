<?php

namespace App\Support\Analytics;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class Period
{
    public const DATE_FORMAT = 'Y-m-d';

    public const MONTH_FORMAT = 'Y-m';

    public function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
    ) {}

    /**
     * Period between two dates (inclusive)
     */
    public static function between(string $from, string $to): self
    {
        return new self(
            CarbonImmutable::createFromFormat(self::DATE_FORMAT, $from)->startOfDay(),
            CarbonImmutable::createFromFormat(self::DATE_FORMAT, $to)->startOfDay(),
        );
    }

    /**
     * Period covering a whole calendar month given as YYYY-MM.
     */
    public static function forMonth(string $month): self
    {
        // The leading "!" resets unspecified fields, so a short month cannot overflow
        // from the server clock's day-of-month (e.g. "2026-02" parsed on a 31st).
        $start = CarbonImmutable::createFromFormat('!'.self::MONTH_FORMAT, $month);

        return new self($start, $start->endOfMonth());
    }

    /**
     * Period of whole calendar months.
     */
    public static function trailingMonths(CarbonInterface $anchor, int $months): self
    {
        $end = CarbonImmutable::instance($anchor)->endOfMonth();

        return new self($end->startOfMonth()->subMonths($months - 1), $end);
    }

    /**
     * Period covering the calendar month immediately before this one.
     */
    public function previousMonth(): self
    {
        return self::forMonth($this->from->startOfMonth()->subMonth()->format(self::MONTH_FORMAT));
    }

    /**
     * Number of days in the period (inclusive)
     */
    public function days(): int
    {
        return (int) $this->from->startOfDay()->diffInDays($this->to->startOfDay()) + 1;
    }

    /**
     * @return array{from: string, to: string}
     */
    public function toArray(): array
    {
        return [
            'from' => $this->from->format(self::DATE_FORMAT),
            'to' => $this->to->format(self::DATE_FORMAT),
        ];
    }

    /**
     * Query string parameters that reproduce this period on any analytics endpoint.
     *
     * @return array{date_from: string, date_to: string}
     */
    public function toQuery(): array
    {
        return [
            'date_from' => $this->from->format(self::DATE_FORMAT),
            'date_to' => $this->to->format(self::DATE_FORMAT),
        ];
    }
}
