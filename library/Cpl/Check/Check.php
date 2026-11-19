<?php
// SPDX-FileCopyrightText: 2026 Research Industrial Systems Engineering (RISE) Forschungs-, Entwicklungs- und Großprojektberatung GmbH
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Cpl\Check;

use Exception;
use Icinga\Application\Logger;
use Throwable;

class Check
{
    protected $name;
    protected $value;
    protected $renderPerfData;
    protected $unit;
    protected $min;
    protected $max;
    protected $label;
    protected $metricIndex;
    protected $metricName;
    protected $metricTemplate;
    protected $round;
    protected $warning;
    protected $critical;

    protected $customPerfData = null;
    protected $customCheckResult = null;
    protected $customCheckOutput = null;

    protected $icingaState = [0 => "OK", 1 => "WARNING", 2 => "CRITICAL", 3 => "UNKNOWN"];

    //d::ifw_partitionspace::used
    //metricIndex::pfw_checkname::metricname
    public function __construct(
        $name,
        $value,
        $renderPerfData = true,
        $unit = "",
        $min = null,
        $max = null,
        $label = null,
        $metricIndex = null,
        $metricName = null,
        $metricTemplate = "generic",
        $round = 6
    ) {
        $this->name = $name;
        $this->value = $value;
        $this->renderPerfData = $renderPerfData;
        $this->unit = $unit;
        $this->min = $min;
        $this->max = $max;
        $this->metricIndex = $metricIndex;
        $this->metricName = $metricName;
        $this->metricTemplate = $metricTemplate;
        $this->round = $round;

        if ($label != null) {
            $this->label = $this->clearLabel($label);
        } else {
            $this->label = $this->clearLabel($this->name);
        }
        $this->warning = new Threshold(null);
        $this->critical = new Threshold(null);
    }

    protected function clearLabel($label)
    {
        return str_replace(" ", "_", $label);
    }

    public function setCustomPerfData($customPerfData)
    {
        $this->customPerfData = $customPerfData;
        return $this;
    }

    public function setCustomCheckResult($customCheckResult)
    {
        $this->customCheckResult = $customCheckResult;
        return $this;
    }

    public function setCustomCheckOutput($customCheckOutput, $withStatus = false)
    {
        $this->customCheckOutput = $customCheckOutput;
        if ($withStatus) {
            if (!preg_match('/^\[(OK|WARNING|CRITICAL|UNKNOWN|UP|DOWN)\]/', $this->customCheckOutput)) {
                if ($this->customCheckResult === null) {
                    Logger::error("SET CUSTOM CHECK RESULT FIRST");
                    exit(3);
                }
                $this->customCheckOutput = "[" . $this->icingaState[$this->customCheckResult] . "] " . $this->customCheckOutput;
            }
        }
        return $this;
    }

    public function warnOutOfRange($warning)
    {
        $this->warning = new Threshold($warning);
        return $this;
    }

    public function critOutOfRange($critical)
    {
        $this->critical = new Threshold($critical);
        return $this;
    }

    public function warnIfNotMatch($warning)
    {
        $this->warning = new NotStringMatch($warning);
        $this->renderPerfData = false;
        return $this;
    }

    public function critIfNotMatch($critical)
    {
        $this->critical = new NotStringMatch($critical);
        $this->renderPerfData = false;
        return $this;
    }

    public function warnIfMatch($warning)
    {
        $this->warning = new StringMatch($warning);
        $this->renderPerfData = false;
        return $this;
    }

    public function critIfMatch($critical)
    {
        $this->critical = new StringMatch($critical);
        $this->renderPerfData = false;
        return $this;
    }

    /**
     * @param $exit
     * @return array|void
     * @throws Exception
     */
    public function evaluate($exit = false)
    {
        $state = 0;
        $threshold_text = "is OK (Threshold(s) not set)";
        if ($this->warning->isSet()) {
            list($violation, $threshold_text) = $this->warning->doesViolate($this->value);
            if ($violation) {
                $state = 1;
            }
        }
        if ($this->critical->isSet()) {
            list($violation, $critical_threshold_text) = $this->critical->doesViolate($this->value);
            if ($violation) {
                $threshold_text = $critical_threshold_text;
                $state = 2;
            } elseif (!$this->warning->isSet()) {
                $threshold_text = $critical_threshold_text;
            } else {
                $threshold_text .= " / " . $critical_threshold_text;
            }
        }
        if ($this->customCheckResult != null) {
            $state = $this->customCheckResult;
        }
        $ret_perfData = $this->generatePerfData();

        if ($exit) {
            echo $this->getOutput($state, $threshold_text) . "\n";
            if (!empty($ret_perfData)) {
                if (is_array($ret_perfData)) {
                    echo "|" . implode(" ", $ret_perfData) . "\n";
                } else {
                    echo "|" . $ret_perfData, true . "\n";
                }
            }

            exit(intval($state));
        } else {
            return [$state, $this->getOutput($state, $threshold_text), $ret_perfData];
        }
    }

    protected function generatePerfData()
    {
        if ($this->customPerfData != null) {
            return $this->customPerfData;
        }
        if (!$this->renderPerfData) {
            return "";
        }
        $valueAndUnit = "{$this->value}{$this->unit}";

        if (!empty($this->metricIndex) && !empty($this->metricName) && !empty($this->metricTemplate)) {
            $name = "{$this->metricIndex}::nfw_{$this->metricTemplate}::{$this->metricName}";
        } elseif (!empty($this->label)) {
            $name = $this->label;
        } else {
            $name = $this->metricTemplate;
        }
        $ret = "'{$name}'={$valueAndUnit};{$this->warning};{$this->critical};{$this->min};{$this->max}";
        return rtrim($ret, ";");
    }

    public function getOutput($state, $text)
    {
        if ($this->customCheckOutput != null) {
            return $this->customCheckOutput;
        }
        $translation = "";
        $value = $this->value;
        $unit = $this->unit;
        if ($unit == "s") {
            try {
                $translation = $this->formatSeconds($value, $unit, $this->round);
                $value = floatval($value);
                $value = round($value, $this->round);
            } catch (Throwable $exception) {
                //$value = $value;
            }
        }
        if ($this->isBytes($unit)) {
            list($value, $unit) = $this->formatBytes($unit, $value, $this->round);
        }
        $text = "{$this->name}: Value '{$value}{$unit}'{$translation} {$text}";
        return "[{$this->icingaState[$state]}] {$text}";
    }

    public function formatSeconds($unit, $value, $precision)
    {
        $translation = "";
        $time = $value;
        $days = round($time / (24 * 3600));
        $time = $time % (24 * 3600);
        $hours = round($time / (60 * 60));
        $time = $time % (60);
        $minutes = round($time / (60));
        $time = $time % (60);
        $seconds = round($time);
        if ($days > 0) {
            $translation .= "{$days} day";
        }
        if ($days > 1) {
            $translation .= "s";
        }
        if ($hours > 0) {
            $translation .= "{$hours} hour";
        }
        if ($hours > 1) {
            $translation .= "s";
        }
        if ($minutes > 0) {
            $translation .= "{$minutes} minute";
        }
        if ($minutes > 1) {
            $translation .= "s";
        }
        if ($seconds > 0) {
            $translation .= "{$seconds} second";
        }
        if ($seconds > 1) {
            $translation .= "s";
        }
        if ($translation != "") {
            $translation = "~ {$translation}";
        }

        return $translation;
    }

    public function isBytes($unit)
    {
        $bytes = ["B", "KiB", "MiB", "GiB", "TiB", "PiB", "EiB", "ZiB", "YiB"];
        $index = in_array($unit, $bytes);
        if ($index === false) {
            return false;
        }
        return true;
    }

    public function formatBytes($unit, $value, $precision = 2)
    {
        $bytes = ["B", "KiB", "MiB", "GiB", "TiB", "PiB", "EiB", "ZiB", "YiB"];
        $index = array_search($unit, $bytes);

        if (!$this->isBytes($unit)) {
            throw new Exception("Invalid unit value");
        }
        while ($value > 1024) {
            $value /= 1024;
            $index++;
        }
        $unit = $bytes[$index];
        $value = round($value, $precision);

        return [$value, $unit];
    }

}