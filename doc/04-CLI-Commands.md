# CLI Commands <a id="cpl-cli-commands"></a>

The `cpl` module exposes three `icingacli` command groups. They build on the
library classes to aggregate many check results into a single Icinga-compatible
check output and performance data line.

All commands exit with the standard Icinga state code (`0` = OK, `1` = WARNING,
`2` = CRITICAL, `3` = UNKNOWN).

## monitoring <a id="cpl-cli-monitoring"></a>

### `icingacli cpl monitoring checkServices`

Aggregates the status of all services from the **monitoring backend**
(`icinga-monitoring-module`) into a single check package.

```sh
icingacli cpl monitoring checkServices [--filter <query>] [--name <name>]
    [--conjunction <op>] [--sort <column>] [--dir <direction>] [--allowEmpty]
```

| Option | Default | Description |
|--------|---------|-------------|
| `--filter <query>` | `*` | A monitoring-backend compatible filter query. |
| `--name <name>` | `CheckPackage` | The name of the CheckPackage. |
| `--conjunction <op>` | `AND` | Aggregation operator for the package: `AND` or `OR`. |
| `--sort <column>` | `service_severity` | Column to sort by. |
| `--dir <direction>` | `desc` | Sort direction: `asc` or `desc`. |
| `--allowEmpty` | off | If the query returns no services, the check is OK instead of UNKNOWN. |

## icingadb <a id="cpl-cli-icingadb"></a>

### `icingacli cpl icingadb checkServices`

Aggregates the status of all services from **IcingaDB** into a single check
package.

```sh
icingacli cpl icingadb checkServices [--filter <query>] [--name <name>]
    [--conjunction <op>] [--sort <column>] [--dir <direction>] [--allowEmpty]
```

| Option | Default | Description |
|--------|---------|-------------|
| `--filter <query>` | — | An IcingaDB compatible filter query. |
| `--name <name>` | `CheckPackage` | The name of the CheckPackage. |
| `--conjunction <op>` | `AND` | Aggregation operator for the package: `AND` or `OR`. |
| `--sort <column>` | `service.state.severity` | Column to sort by. |
| `--dir <direction>` | `desc` | Sort direction: `asc` or `desc`. |
| `--allowEmpty` | off | If the query returns no services, the check is OK instead of UNKNOWN. |

## director <a id="cpl-cli-director"></a>

### `icingacli cpl director setup`

Syncs the two check commands above into the Icinga Director. For each command it
creates (or updates) the `IcingaCommand` object and its datafield arguments.

```sh
icingacli cpl director setup [--categoryId <id>]
```

| Option | Description |
|--------|-------------|
| `--categoryId <id>` | Director datafield category id under which the command datafields are created. |

The command syncs the following Director check commands:

| Director command name | Invoked CLI command |
|-----------------------|---------------------|
| `icingacli_cpl_monitoring_services` | `icingacli cpl monitoring checkServices` |
| `icingacli_cpl_icingadb_services` | `icingacli cpl icingadb checkServices` |

Each synced command exposes the following Director arguments:

| Argument | Description |
|----------|-------------|
| `--conjunction` | Aggregation operator to apply, `AND` or `OR` (default: `AND`). |
| `--dir` | Sort direction, defaults to `desc`. |
| `--filter` | A monitoring/icingadb compatible filter query. |
| `--name` | The name of the CheckPackage. |
| `--sort` | Sort of the fetched objects, defaults to severity. |
| `--allowEmpty` | If set and the query returns an empty result, the check is OK instead of UNKNOWN. |

## Typical workflow

1. Run `icingacli cpl director setup` to create the Director check commands.
2. In the Director, assign the synced command to a host/service and configure the
   arguments (`--filter`, `--conjunction`, ...).
3. The resulting check returns a single aggregated state and performance data
   line covering all matched services.
