<?php
// SPDX-FileCopyrightText: 2026 Research Industrial Systems Engineering (RISE) Forschungs-, Entwicklungs- und Großprojektberatung GmbH
// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com> Jira Module
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Cpl\Director;

use Exception;
use Icinga\Application\Config;
use Icinga\Module\Director\DataType\DataTypeBoolean;
use Icinga\Module\Director\DataType\DataTypeString;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\DirectorDatafield;
use Icinga\Module\Director\Objects\DirectorDatafieldCategory;
use Icinga\Module\Director\Objects\IcingaCommand;
use Icinga\Module\Director\Objects\IcingaCommandField;
use Icinga\Module\Director\Web\Form\IcingaObjectFieldLoader;
use Throwable;

class DirectorConfig
{
    /** @var Db */
    protected $db;
    protected $categoryId;
    protected $commandOptions = [];

    public function __construct($commandOptions, $categoryId)
    {
        $this->commandOptions = $commandOptions;
        $this->categoryId = $categoryId;
    }

    public function commandExists(IcingaCommand $command)
    {
        return IcingaCommand::exists($command->getObjectName(), $this->db);
    }

    public function commandDiffers(IcingaCommand $command)
    {
        return IcingaCommand::load($command->getObjectName(), $this->db)
            ->replaceWith($command)
            ->hasBeenModified();
    }

    public function datafieldsDiffer(IcingaCommand $command)
    {
        $command = IcingaCommand::load($command->getObjectName(), $this->db) ?? $command;
        $changes = $this->syncDatafields($command, false);

        return $changes['outOfSync'];
    }

    public function syncDatafields(IcingaCommand $command, $save = true)
    {
        $db = $this->db;

        $name = $command->getObjectName();
        if ($command::exists($name, $db)) {
            $command = $command::load($name, $db);
        }
        return $this->createDatafields($command, $save);

    }

    public function createDatafields(IcingaCommand $command, $save)
    {
        try {
            if (!DirectorDatafieldCategory::loadWithAutoIncId($this->categoryId, $this->db())) {
                $category_id = null;
            }
        } catch (Throwable $e) {
            $category_id = null;
        }

        $datafields = $this->getAllDatafields();
        $result = [];
        $outOfsync = false;
        foreach ($this->commandOptions['arguments'] as $argument => $properties) {
            $isBoolean = false;
            if (isset($properties->set_if)) {
                $isBoolean = true;
                $name = $properties->set_if;
            } else {
                $name = $properties->value;
            }
            $name = trim($name, '$');
            if (!in_array($name, array_keys($datafields))) { //datafield does not exist
                $result[$name] = ['status' => 'new'];
                $field = DirectorDatafield::create([
                    'varname' => $name,
                    'category_id' => $category_id,
                    'caption' => $name,
                    'description' => $properties->description,
                    'datatype' => $isBoolean
                        ? DataTypeBoolean::class
                        : DataTypeString::class
                ]);
                if ($save) {
                    $field->store($this->db());
                }

                $addFieldToCommand = IcingaCommandField::create([
                    'command_id' => $command->getAutoincId(),
                    'datafield_id' => $field->getAutoincId(),
                    'is_required' => 'n',
                    'var_filter' => null,
                ]);
                if ($save) {
                    $addFieldToCommand->store($this->db());
                }
                $outOfsync = true;
            } else {
                //datafield must exist by now
                $field = DirectorDatafield::create([
                    'varname' => $name,
                    'caption' => $name,
                    'category_id' => $category_id,
                    'description' => $properties->description,
                    'datatype' => $isBoolean
                        ? DataTypeBoolean::class
                        : DataTypeString::class
                ]);
                /** @var $oldField DirectorDatafield */

                $oldField = $datafields[$name];

                foreach ($field->getProperties() as $key => $value) {
                    if (in_array($key, ['id', 'uuid'])) {
                        continue;
                    }
                    $oldField->set($key, $value);
                }


                if ($oldField->hasBeenModified()) {
                    $outOfsync = true;

                    $result[$name] = ['status' => 'changed'];
                    if ($save) {
                        $oldField->store($this->db());
                    }
                } else {
                    $result[$name] = ['status' => 'ok'];
                }

                $loader = new IcingaObjectFieldLoader($command);

                if (!in_array($name, array_keys($loader->getFields()))) {
                    $result[$name] = ['status' => 'not connected'];

                    $addFieldToCommand = IcingaCommandField::create([
                        'command_id' => $command->getAutoincId(),
                        'datafield_id' => $oldField->getAutoincId(),
                        'is_required' => 'n',
                        'var_filter' => null,
                    ]);
                    if ($save) {
                        $addFieldToCommand->store($this->db());
                    }
                    $outOfsync = true;
                }
            }
        }

        return ['outOfSync' => $outOfsync, 'fields' => $result];
    }

    public function db()
    {
        if ($this->db === null) {
            $this->db = $this->initializeDb();
        }

        return $this->db;
    }

    protected function initializeDb()
    {
        $resourceName = Config::module('director')->get('db', 'resource');
        return Db::fromResourceName($resourceName);
    }

    public function getAllDatafields()
    {
        $datafields = [];
        $datafieldsFromDb = DirectorDatafield::loadAll($this->db());
        foreach ($datafieldsFromDb as $datafield) {
            $datafields[$datafield->get('varname')] = $datafield;
        }
        return $datafields;
    }

    /**
     * @throws Exception
     */
    public function sync()
    {
        $commandsWasSynced = $this->syncCommand($this->createCommand());
        $datafieldsWereSynced = false;
        if (!$commandsWasSynced) {
            $this->syncDatafields($this->createCommand(), false);
        }
        if ($this->syncDatafields($this->createCommand(), false)['outOfSync']) {
            $this->syncDatafields($this->createCommand());
            $datafieldsWereSynced = true;
        }
        return $commandsWasSynced || $datafieldsWereSynced;
    }

    public function syncCommand(IcingaCommand $command)
    {
        $db = $this->db;

        $name = $command->getObjectName();
        if ($command::exists($name, $db)) {
            $new = $command::load($name, $db)
                ->replaceWith($command);
            if ($new->hasBeenModified()) {
                $new->store();

                return true;
            } else {
                return false;
            }
        } else {
            $command->store($db);

            return true;
        }
    }


    /**
     * @return mixed
     * @throws Exception
     */
    public function createCommand()
    {
        if ($this->commandOptions == [] || $this->commandOptions == null) {
            throw new Exception("Init CommandOptions first");
        }
        return IcingaCommand::create($this->commandOptions, $this->db());
    }
}
