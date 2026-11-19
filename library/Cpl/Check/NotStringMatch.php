<?php
// SPDX-FileCopyrightText: 2026 Research Industrial Systems Engineering (RISE) Forschungs-, Entwicklungs- und Großprojektberatung GmbH
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Cpl\Check;

class NotStringMatch extends StringMatch
{

    public function doesViolate($value)
    {
        $criteria = $this->criteria;
        if ($criteria === null) {
            return [false, "is OK"];
        }
        $match = $this->wildcardMatch($criteria, $value);

        if (!$match) {
            return [true, "is not matching {$criteria}"];
        } else {
            return [false, "is matching {$criteria}"];
        }
    }
}