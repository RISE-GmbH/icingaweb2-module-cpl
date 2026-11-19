<?php
// SPDX-FileCopyrightText: 2026 Research Industrial Systems Engineering (RISE) Forschungs-, Entwicklungs- und Großprojektberatung GmbH
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Cpl\Check;

class StringMatch
{
    protected $criteria = null;

    public function __construct($criteria)
    {
        $this->criteria = $criteria;
    }

    public function isSet()
    {
        return $this->criteria != null;
    }

    public function __toString()
    {
        if ($this->criteria != null) {
            return $this->criteria;
        } else {
            return "";
        }
    }

    public function doesViolate($value)
    {
        $criteria = $this->criteria;
        if ($criteria === null) {
            return [false, "is OK"];
        }
        $match = $this->wildcardMatch($criteria, $value);

        if ($match) {
            return [true, "is matching {$criteria}"];
        } else {
            return [false, "is not matching {$criteria}"];
        }
    }

    function wildcardMatch(string $pattern, string $subject): bool
    {
        // Escape regex special characters, except for '*'
        $pattern = preg_quote($pattern, '/');
        // Replace '*' with '.*' for wildcard match
        $pattern = str_replace('\*', '.*', $pattern);
        // Add start and end anchors to match full string
        $regex = '/^' . $pattern . '$/i';  // Case-insensitive

        return (bool)preg_match($regex, $subject);
    }
}