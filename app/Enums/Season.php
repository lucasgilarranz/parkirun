<?php

namespace App\Enums;

use Carbon\CarbonImmutable;

enum Season
{
    case OpenSeason;
    case ClosedSeason;

    public function label(): string
    {
        return match ($this) {
            self::OpenSeason => 'Open Season',
            self::ClosedSeason => 'Closed Season',
        };
    }

    public function startDate(int $year): CarbonImmutable
    {
        return match ($this) {
            self::OpenSeason => CarbonImmutable::create($year, 1, 1),
            self::ClosedSeason => CarbonImmutable::create($year, 7, 1),
        };
    }

    public function endDate(int $year): CarbonImmutable
    {
        return match ($this) {
            self::OpenSeason => CarbonImmutable::create($year, 6, 30)->endOfDay(),
            self::ClosedSeason => CarbonImmutable::create($year, 12, 31)->endOfDay(),
        };
    }

    public function targetField(): string
    {
        return match ($this) {
            self::OpenSeason => 'open_season_target_km',
            self::ClosedSeason => 'closed_season_target_km',
        };
    }
}
