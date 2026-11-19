# About the CPL Module <a id="cpl-module-about"></a>

`cpl` is the **Check PHP Library**. It is a library module that provides reusable
PHP classes for use across other RISE modules in IcingaWeb2, as well as tools
for rendering Icinga check results — either as a single check or aggregated into
a linked package.

## Purpose

The module exists to solve two recurring problems:

* **Code reuse** — many modules need to build check results, evaluate thresholds
  and format performance data in the same way. The classes in this module centralise
  that logic so it only has to be maintained once.
* **Check result rendering** — Icinga check results (state, output and performance
  data) can be produced and aggregated in a consistent, Icinga-compatible format,
  either for a single check or for a whole package of checks.

## Library

The reusable classes live under `library/Cpl/`:

| Class | Description |
|-------|-------------|
| `Cpl\Check\Check` | Represents a single check result. Holds the name, value, thresholds and performance data, and renders an Icinga-style state/output/perfdata result. |
| `Cpl\Check\CheckPackage` | A container that aggregates one or more `Check` objects and nested packages into a single state and output tree. |
| `Cpl\Check\Threshold` | A numeric threshold range used to derive a check state from a value. |
| `Cpl\Check\StringMatch` | A wildcard string threshold that flags a value as problematic when it matches a pattern. |
| `Cpl\Check\NotStringMatch` | The inverse of `StringMatch` — flags a value when it matches the pattern. |
| `Cpl\Check\ScreenWrapper` | Applies ANSI colors to the `[OK]`, `[WARNING]`, `[CRITICAL]` and `[UNKNOWN]` tokens of rendered output. |
| `Cpl\Check\DummyScreen` | A no-op screen used when no real screen is available (returns text unchanged). |
| `Cpl\Director\DirectorConfig` | Syncs a check command and its datafields from a PHP array into the Icinga Director configuration. |

## CLI Commands

The module also exposes three `icingacli` command groups that build on the library:

| Command | Description |
|---------|-------------|
| `icingacli cpl monitoring checkServices` | Aggregates the status of all services from the monitoring backend into a single check package. |
| `icingacli cpl icingadb checkServices` | Aggregates the status of all services from IcingaDB into a single check package. |
| `icingacli cpl director setup` | Syncs the two check commands above (with their datafields) into the Icinga Director. |

See [CLI Commands](04-CLI-Commands.md#cpl-cli-commands) for the full reference.

## Related Documentation

* [Installation](02-Installation.md#cpl-module-installation)
* [Library Usage](03-Library-Usage.md#cpl-library-usage)
* [CLI Commands](04-CLI-Commands.md#cpl-cli-commands)
