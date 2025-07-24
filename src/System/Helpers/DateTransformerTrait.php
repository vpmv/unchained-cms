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
        } else {
            $result = 0;
            $period = 'unknown';
        }
        if ($result > 1) {
            $period .= '_plural';
        }

        return new TranslatableValue($period . '.nn', ['nn' => $result], 'time');
    }
}