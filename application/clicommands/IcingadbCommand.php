<?php
// SPDX-FileCopyrightText: 2026 Research Industrial Systems Engineering (RISE) Forschungs-, Entwicklungs- und Großprojektberatung GmbH
// SPDX-FileCopyrightText: 2019 Icinga GmbH <https://icinga.com> IcingaDB Web Module
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Cpl\Clicommands;

use Icinga\Cli\Command;
use Icinga\Module\Cpl\Check\Check;
use Icinga\Module\Cpl\Check\CheckPackage;
use Icinga\Module\Cpl\Check\ScreenWrapper;
use Icinga\Module\Icingadb\Common\Database;
use Icinga\Module\Icingadb\Model\Service;
use Icinga\Module\Icingadb\Redis\VolatileStateResults;
use ipl\Web\Filter\QueryString;


class IcingadbCommand extends Command
{
    use Database;

    protected $states = [
        0 => "OK",
        1 => "WARNING",
        2 => "CRITICAL",
        3 => "UNKNOWN"
    ];

    /**
     * Aggregate the status of all IcingaDB services into a single check package
     *
     * USAGE
     *
     * icingacli cpl icingadb checkServices
     *
     * OPTIONS
     *   --filter <query>      An IcingaDB compatible filter query
     *   --name <name>         The name of the CheckPackage (default: CheckPackage)
     *   --conjunction <op>    Aggregation operator for the package: AND or OR (default: AND)
     *   --sort <column>       Column to sort by (default: service.state.severity)
     *   --dir <direction>     Sort direction: asc or desc (default: desc)
     *   --allowEmpty          If set and the query returns no services, the check is OK instead of UNKNOWN
     */
    public function checkServicesAction()
    {
        $filter = $this->params->get('filter');
        $name = $this->params->get('name', "CheckPackage");
        $conjunction = $this->params->get('conjunction', "AND");

        $sort = $this->params->get('sort', "service.state.severity");
        $dir = $this->params->get('dir', "desc");
        $allowEmpty = $this->params->shift('allowEmpty', false);

        $services = Service::on($this->getDb())->with([
            'state',
            'state.last_comment',
            'host',
            'host.state',
            'icon_image'
        ]);
        $services->getWith()['service.state']->setJoinType('INNER');
        $services->setResultSetClass(VolatileStateResults::class);

        $filter = QueryString::parse((string)$filter);
        $services->filter($filter);
        $services->orderBy($sort, $dir);
        $packageName = $name . " (" . $conjunction . ")";
        $package = new CheckPackage($packageName, $conjunction, true, $allowEmpty, new ScreenWrapper($this->screen));

        foreach ($services as $service) {
            $checkName = $service->name . " on " . $service->host->name;

            $check = new Check($checkName, null, false);
            $check->setCustomCheckResult($service->state->hard_state);
            $check->setCustomCheckOutput(
                "[{$this->states[$service->state->hard_state]}] $checkName" . " is in {$this->states[$service->state->hard_state]} State"
            );


            $package->add($check);
        }
        $package->evaluate(true);
    }
}
