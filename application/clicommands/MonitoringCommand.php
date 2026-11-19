<?php
// SPDX-FileCopyrightText: 2026 Research Industrial Systems Engineering (RISE) Forschungs-, Entwicklungs- und Großprojektberatung GmbH
// SPDX-FileCopyrightText: 2014 Icinga GmbH <https://icinga.com> Monitoring Module
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Cpl\Clicommands;

use Icinga\Cli\Command;
use Icinga\Data\Filter\Filter;
use Icinga\Module\Cpl\Check\Check;
use Icinga\Module\Cpl\Check\CheckPackage;
use Icinga\Module\Cpl\Check\ScreenWrapper;
use Icinga\Module\Icingadb\Common\Database;
use Icinga\Module\Monitoring\Backend\MonitoringBackend;

class MonitoringCommand extends Command
{
    use Database;

    protected $states = [
        0 => "OK",
        1 => "WARNING",
        2 => "CRITICAL",
        3 => "UNKNOWN"
    ];

    /**
     * Aggregate the status of all monitored services into a single check package
     *
     * USAGE
     *
     * icingacli cpl monitoring checkServices
     *
     * OPTIONS
     *   --filter <query>      A monitoring-backend compatible filter query (default: * )
     *   --name <name>         The name of the CheckPackage (default: CheckPackage)
     *   --conjunction <op>    Aggregation operator for the package: AND or OR (default: AND)
     *   --sort <column>       Column to sort by (default: service_severity)
     *   --dir <direction>     Sort direction: asc or desc (default: desc)
     *   --allowEmpty          If set and the query returns no services, the check is OK instead of UNKNOWN
     */
    public function checkServicesAction()
    {
        $filter = $this->params->get('filter', "*");
        $name = $this->params->get('name', "CheckPackage");
        $conjunction = $this->params->get('conjunction', "AND");
        $sort = $this->params->get('sort', "service_severity");
        $dir = $this->params->get('dir', "desc");
        $allowEmpty = $this->params->shift('allowEmpty', false);


        $services = $this->getBackend()->select()->from('servicestatus', array_merge(array(
            'host_name',
            'host_display_name',
            'host_state',
            'service_description',
            'service_display_name',
            'service_state' => 'service_hard_state',
            'service_in_downtime',
            'service_acknowledged',
            'service_handled',
            'service_output',
            'service_perfdata',
            'service_attempt',
            'service_last_state_change' => 'service_last_state_change',
            'service_icon_image',
            'service_icon_image_alt',
            'service_is_flapping',
            'service_state_type',
            'service_handled',
            'service_severity',
            'service_notifications_enabled',
            'service_active_checks_enabled',
            'service_passive_checks_enabled',
            'service_check_command',
            'service_next_update'
        ), $this->addColumns()));

        $filters = Filter::matchAll();
        if ($filter !== '*') {
            $filters->addFilter(Filter::fromQueryString($filter));
        }
        $services->applyFilter($filters);
        $services->order($sort, $dir);


        $packageName = $name . " (" . $conjunction . ")";
        $package = new CheckPackage($packageName, $conjunction, true, $allowEmpty, new ScreenWrapper($this->screen));

        foreach ($services as $service) {
            $checkName = $service->service_description . " on " . $service->host_name;

            $check = new Check($checkName, null, false);
            $check->setCustomCheckResult($service->service_state);
            $check->setCustomCheckOutput(
                "[{$this->states[$service->service_state]}] $checkName" . " is in {$this->states[$service->service_state]} State"
            );
            $check->setCustomPerfData("");
            $package->add($check);
        }
        $package->evaluate(true);
    }

    protected function getBackend()
    {
        MonitoringBackend::clearInstances();

        return MonitoringBackend::instance();
    }

    protected function addColumns()
    {
        $columns = preg_split(
            '~,~',
            $this->params->shift('addColumns', ''),
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        $customVars = [];
        $additionalCols = [];
        foreach ($columns as $column) {
            if (preg_match('~^_(host|service)_([a-zA-Z0-9_]+)$~', $column, $m)) {
                $customVars[$m[1]]['vars'][$m[2]] = null;
            } else {
                $additionalCols[] = $column;
            }
        }

        return $additionalCols;
    }


}
