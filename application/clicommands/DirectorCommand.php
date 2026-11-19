<?php
// SPDX-FileCopyrightText: 2026 Research Industrial Systems Engineering (RISE) Forschungs-, Entwicklungs- und Großprojektberatung GmbH
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Cpl\Clicommands;

use Icinga\Cli\Command;

use Icinga\Module\Cpl\Director\DirectorConfig;


class DirectorCommand extends Command
{

    /**
     * Sync the cpl check commands and their datafields into the Icinga Director
     *
     * Creates or updates the "icingacli_cpl_monitoring_services" and
     * "icingacli_cpl_icingadb_services" check commands together with their
     * datafield arguments in the Director configuration.
     *
     * USAGE
     *
     * icingacli cpl director setup
     *
     * OPTIONS
     *   --categoryId <id>     Director datafield category id under which the command datafields are created
     */
    public function setupAction()
    {
        $categoryId = $this->params->getParams("categoryId");
        $directorHelper = new DirectorConfig(
            $this->createCommandArray(
                "icingacli_cpl_monitoring_services",
                '/usr/bin/icingacli cpl monitoring checkServices'
            ), $categoryId
        );
        $command = $directorHelper->createCommand();
        $directorHelper->syncCommand($command);
        $directorHelper->syncDatafields($command);

        $categoryId = $this->params->getParams("categoryId");
        $directorHelper = new DirectorConfig(
            $this->createCommandArray(
                "icingacli_cpl_icingadb_services",
                '/usr/bin/icingacli cpl icingadb checkServices'
            ), $categoryId
        );
        $command = $directorHelper->createCommand();
        $directorHelper->syncCommand($command);
        $directorHelper->syncDatafields($command);
    }

    /**
     * @return array
     */
    public function createCommandArray($name, $command)
    {
        return [
            'methods_execute' => 'PluginCheck',
            'object_name' => $name,
            'object_type' => 'object',
            'command' => $command,
            'arguments' => [
                '--conjunction' => (object)[
                    'value' => '$icingacli_cpl_conjunction$',
                    'description' => 'A Conjunction to apply, use AND or OR, default is AND',
                ],
                '--dir' => (object)[
                    'value' => '$icingacli_cpl_sortdirection$',
                    'description' => 'The sort directions, defaults to desc',
                ],
                '--filter' => (object)[
                    'value' => '$icingacli_cpl_filter$',
                    'description' => 'A monitoring/icingadb module compatible filter query',
                ],
                '--name' => (object)[
                    'value' => '$icingacli_cpl_name$',
                    'description' => 'The name of the CheckPackage',
                ],
                '--sort' => (object)[
                    'value' => '$icingacli_cpl_sort$',
                    'description' => 'The sort of the objects that will be fetched, defaults to severity',
                ],
                '--allowEmpty' => (object)[
                    'set_if' => '$icingacli_cpl_allowEmpty$',
                    'description' => 'If the query returns an empty result, the check can be OK if this is set, otherwise it is unknown',
                ]
            ],
        ];
    }


}
