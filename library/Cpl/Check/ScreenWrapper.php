<?php
// SPDX-FileCopyrightText: 2026 Research Industrial Systems Engineering (RISE) Forschungs-, Entwicklungs- und Großprojektberatung GmbH
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Cpl\Check;


class ScreenWrapper
{

    protected $screen;
    protected $colors = [
        "OK" => "green",
        "WARNING" => "yellow",
        "CRITICAL" => "red",
        "UNKNOWN" => "purple"
    ];

    public function __construct($screen = null)
    {
        if ($screen == null || !method_exists($screen, 'colorize') || !is_callable([$screen, 'colorize'])) {
            $this->screen = new DummyScreen();
        } else {
            $this->screen = $screen;
        }
    }

    public function colorize($text)
    {
        $screen = $this->screen;
        $colors = $this->colors;
        return preg_replace_callback(
            '/\[(OK|WARNING|CRITICAL|UNKNOWN)\]/',
            function ($matches) use ($screen, $colors) {
                $severity = $matches[1];
                $color = $colors[$severity];
                return $screen->colorize("[$severity]", $color);
            },
            $text
        );
    }

}
