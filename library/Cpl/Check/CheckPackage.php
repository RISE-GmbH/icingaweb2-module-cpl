<?php
// SPDX-FileCopyrightText: 2026 Research Industrial Systems Engineering (RISE) Forschungs-, Entwicklungs- und Großprojektberatung GmbH
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Cpl\Check;


use Exception;

class CheckPackage
{
    protected $subChecks;
    protected $name;
    protected $screenWrapper;
    protected $showOk;
    protected $allowEmpty;
    protected $operator;
    protected $allowedOperators = ['AND', 'OR'];
    protected $icingaState = [0 => "OK", 1 => "WARNING", 2 => "CRITICAL", 3 => "UNKNOWN"];

    /**
     * @param $name
     * @param $operator
     * @param $showOk
     * @param $allowEmpty
     * @param $screenWrapper
     * @throws Exception
     */
    public function __construct($name, $operator = "AND", $showOk = true, $allowEmpty = false, $screenWrapper = null)
    {
        $this->showOk = $showOk;
        $this->allowEmpty = $allowEmpty;
        $this->operator = $operator;
        $this->name = $name;
        $this->subChecks = array();
        if ($screenWrapper instanceof ScreenWrapper) {
            $this->screenWrapper = $screenWrapper;
        } else {
            $this->screenWrapper = new ScreenWrapper();
        }

        if (!in_array($operator, $this->allowedOperators)) {
            throw new Exception("Invalid operator for check package");
        }
    }

    public function hasChecks()
    {
        return !empty($this->subChecks);
    }

    public function add($child)
    {
        if ($child instanceof CheckPackage || $child instanceof Check) {
            $this->subChecks[] = $child;
        } else {
            throw new Exception("Invalid child element for check package");
        }
    }

    /**
     * @param $exit
     * @return array|void
     * @throws Exception
     */
    public function evaluate($exit = false)
    {
        $ret_perfData = "";
        $ret_state = null;
        $ret_text = "{$this->name}\n";
        if ($this->operator == "AND") {
            $ret_state = 0;
        }
        if (count($this->subChecks) == 0) {
            if ($this->allowEmpty) {
                $ret_state = 0;
                $ret_text = "[OK] No checks added to this package";
                $ret_perfData = null;
            } else {
                $ret_state = 3;
                $ret_text = "[UNKNOWN] No checks added to this package";
            }
        } else {
            foreach ($this->subChecks as $toEvaluate) {
                /* @var $toEvaluate Check */

                list($state, $text, $perfdata) = $toEvaluate->evaluate();
                if ($this->showOk or $state != 0) {
                    foreach (explode("\n", $text) as $line) {
                        $ret_text .= "\\_ {$line}\n";
                    }
                }
                $ret_perfData .= " " . $perfdata;
                if ($ret_state == null) {
                    $ret_state = $state;
                }
                if ($this->operator == "AND") {
                    $ret_state = max($state, $ret_state);
                } elseif ($this->operator == "OR") {
                    $ret_state = min($state, $ret_state);
                }
            }
            $ret_text = rtrim($ret_text, "\n");
            $ret_text = str_replace("\\_ \\_", "   \\_", $ret_text);
            $ret_text = str_replace("\\_   ", "     ", $ret_text);
            $ret_perfData = trim($ret_perfData, " ");
            while (str_contains($ret_perfData, "  ")) {
                $ret_perfData = str_replace("  ", " ", $ret_perfData);
            }
            $ret_text = "[{$this->icingaState[$ret_state]}] {$ret_text}";
        }

        $ret_text = $this->screenWrapper->colorize($ret_text);
        if ($exit) {
            echo $ret_text . "\n";
            if (!empty($ret_perfData)) {
                echo "|" . $ret_perfData . "\n";
            }


            exit(intval($ret_state));
        } else {
            return [$ret_state, $ret_text, $ret_perfData];
        }
    }


}