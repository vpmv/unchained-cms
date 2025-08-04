<?php

namespace App\System\Helpers;

use App\System\Application\Translation\TranslatableValue;
use App\System\Constructs\Translatable;

trait DateTransformerTrait
{
    public static function timeAgo(mixed $start, mixed $end = null): ?Translatable
    {
        if (!$start) {
            return null;
        }

        $now = new \DateTime();
        if (!$start instanceof \DateTime) {
            $start = new \DateTime($start);
        }
        if (!empty($end)) {
            $now = new \DateTime($end);
        }
        $interval = $start->diff($now);

        if ($interval->y >= 1) {
            $result = $interval->y;
            $period = 'year';
        } elseif ($interval->m >= 1) {
            $result = $interval->m;
            $period = 'month';
        } elseif ($interval->d >= 1) {
            $result = $interval->d;
            $period = 'day';
        } elseif ($start->getTimestamp() == $now->getTimestamp()) {
            $result = 1;
            $period = 'day';
        } else {
            return new TranslatableValue('time.unknown');
        }
        if ($result > 1) {
            $period .= '_plural';
        }

        return new TranslatableValue($period . '.nn', ['nn' => $result], 'time');
    }

    /**
     * DateTime comparison keeping original data input
     *
     * @param callable(int, int): bool $comp     Compare function
     * @param \DateTime|string         ...$dates Input date-time values
     *
     * @return mixed
     * @throws \DateMalformedStringException
     */
    public static function findExtremeDate(callable $comp, \DateTime|string ...$dates): mixed
    {
        $extreme    = null;
        $extremeKey = 0;

        foreach ($dates as $k => $d) {
            $dt        = $d instanceof \DateTime ? $d : new \DateTime($d);
            $timestamp = $dt->getTimestamp();

            if (
                $extreme === null ||
                $comp($timestamp, $extreme)
            ) {
                $extreme    = $timestamp;
                $extremeKey = $k;
            }
        }

        return $dates[$extremeKey]; // preserve original input data
    }

    /**
     * Compare date-time values and return earliest occurrence
     *
     * @param \DateTime|string ...$dates Date-times
     *
     * @return \DateTime|string          The earliest occurrence
     * @throws \DateMalformedStringException
     */
    public static function earliest(\DateTime|string ...$dates): \DateTime|string
    {
        return static::findExtremeDate(fn($a, $b) => $a < $b, ...$dates);
    }

    /**
     * Compare date-time values and return latest occurrence
     *
     * @param \DateTime|string ...$dates Date-times
     *
     * @return \DateTime|string          The earliest occurrence
     * @throws \DateMalformedStringException
     */
    public static function latest(\DateTime|string ...$dates): \DateTime|string
    {
        return static::findExtremeDate(fn($a, $b) => $a > $b, ...$dates);
    }
}