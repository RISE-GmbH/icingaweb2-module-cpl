# Installation <a id="cpl-module-installation"></a>

`cpl` is a library module. It does not add any menu entries or configuration
pages of its own — it is enabled so that other modules can use its classes and
CLI commands.

## Requirements <a id="cpl-module-installation-requirements"></a>

* Icinga Web 2 (>= 2.12)
* PHP (>= 7.2)
* The modules that consume the library must have their own dependencies available:
  * `icingacli cpl monitoring checkServices` requires the `monitoring` module.
  * `icingacli cpl icingadb checkServices` requires the `icingadb` module.
  * `icingacli cpl director setup` requires the `director` module.

## Installation <a id="cpl-module-installation-manual"></a>

Extract the module to a folder named `cpl` in one of your IcingaWeb2 module
path directories.

Enable the module either on the CLI by running

```sh
icingacli module enable cpl
```

Or go to your Icinga Web 2 frontend, choose `Configuration` → `Modules`, select
the `cpl` module and `enable` it.

> **Note:** The module is also used indirectly. Once a consuming module such as
> `icingabridge` is installed, `cpl` must be enabled as well so that its classes
> and CLI commands are available.
