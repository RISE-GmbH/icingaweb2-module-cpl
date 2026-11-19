<?php
/* Icinga Web 2 | (c) 2013 Icinga Development Team | GPLv2+ */

namespace Icinga\Module\Cpl\Check;


class DummyScreen
{
    public function colorize($text, $fgColor = null, $bgColor = null)
    {
        return $text;
    }

}
