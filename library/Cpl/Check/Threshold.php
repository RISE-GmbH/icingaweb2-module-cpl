<?php
// SPDX-FileCopyrightText: 2026 Research Industrial Systems Engineering (RISE) Forschungs-, Entwicklungs- und Großprojektberatung GmbH
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Cpl\Check;

use Exception;

class Threshold
{
    protected $range = null;

    public function __construct($range)
    {
        $this->range = $range;
    }

    public function isSet()
    {
        return $this->range != null;
    }

    public function __toString()
    {
        if ($this->range != null) {
            return $this->range;
        } else {
            return "";
        }
    }

    /**
     * @param $value
     * @return array
     * @throws Exception
     */
    public function doesViolate($value)
    {
        $range = $this->range;
        if ($range === null) {
            return [false, "is OK"];
        }
        $parsedRange = $this->parseThresholdRange($range);
        if (false === $parsedRange) {
            throw new Exception("Invalid Range");
        }
        // Check if value is within or outside of the range
        if (false == $parsedRange['invert']) {
            if ($value < $parsedRange['start']) {
                return [true, "is not OK (smaller than {$parsedRange['start']})"];
            } elseif ($value > $parsedRange['end']) {
                return [true, "is not OK (bigger than {$parsedRange['end']})"];
            } else {
                return [false, "is OK (between {$parsedRange['start']} and {$parsedRange['end']})"];
            }
        } else {
            if ($value >= $parsedRange['start'] && $value <= $parsedRange['end']) {
                return [true, "is not OK (between {$parsedRange['start']} and {$parsedRange['end']})"];
            } else {
                return [false, "is OK (Outside {$parsedRange['start']} and {$parsedRange['end']})"];
            }
        }
    }

    public function parseThresholdRange($range)
    {
        // Regular expression pattern for parsing threshold range
        $pattern = '/^(@?)(-?\d*|\~):?(-?\d*|\~)?$/';
        if (preg_match($pattern, $range, $matches)) {
            $invert = ($matches[1] === '@'); // Check if inverted
            $start = ($matches[2] === '') ? ($matches[1] === '~' ? '-INF' : 0) : $matches[2];
            $end = ($matches[3] === '') ? 'INF' : $matches[3];
            if (!str_contains($range, ':')) {
                $end = $start;
                $start = 0;
            }
            // Convert start and end to integers if not infinity
            $start = ($start === '-INF') ? -PHP_FLOAT_MAX : (int)$start;
            $end = ($end === 'INF') ? PHP_FLOAT_MAX : (int)$end;

            return array('invert' => $invert, 'start' => $start, 'end' => $end);
        } else {
            return false; // Return false for invalid range format
        }
    }
}