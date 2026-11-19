# Library Usage <a id="cpl-library-usage"></a>

This chapter describes how to use the reusable classes of the `cpl` module in
your own Icinga Web 2 module. All classes live in the namespace
`Icinga\Module\Cpl\Check` (or `Icinga\Module\Cpl\Director`).

## Check <a id="cpl-library-check"></a>

`Check` represents a single check result. It combines a value, optional
thresholds and optional custom output, and renders an Icinga-compatible result
consisting of a state code, a human-readable output line and performance data.

### Constructor

```php
new Check(
    $name,            // Display name of the check
    $value,           // Numeric value used for threshold evaluation
    $renderPerfData = true,
    $unit = "",       // Unit of the value, e.g. "%", "s", "MiB"
    $min = null,
    $max = null,
    $label = null,    // Perf data key label (defaults to the cleaned name)
    $metricIndex = null,
    $metricName = null,
    $metricTemplate = "generic",
    $round = 6
)
```

### Setting thresholds

Thresholds derive the check state from the numeric value:

| Method | Threshold type | Behavior |
|--------|----------------|----------|
| `warnOutOfRange($range)` | `Threshold` | WARNING when the value is outside the numeric range. |
| `critOutOfRange($range)` | `Threshold` | CRITICAL when the value is outside the numeric range. |
| `warnIfMatch($pattern)` | `StringMatch` | WARNING when the value matches the wildcard pattern. |
| `critIfMatch($pattern)` | `StringMatch` | CRITICAL when the value matches the wildcard pattern. |
| `warnIfNotMatch($pattern)` | `NotStringMatch` | WARNING when the value does not match the pattern. |
| `critIfNotMatch($pattern)` | `NotStringMatch` | CRITICAL when the value does not match the pattern. |

The numeric range syntax follows Icinga conventions, e.g. `70`, `70:90`, `:90`,
`70:` or the inverted `@70:90`.

### Setting custom values

When the state and output come from an external source (for example a remote
check result), override them directly:

| Method | Purpose |
|--------|---------|
| `setCustomCheckResult($state)` | Force the check state code. |
| `setCustomCheckOutput($text, $withStatus = false)` | Force the check output line. |
| `setCustomPerfData($perfdata)` | Force the performance data string. |

### Evaluating

```php
$check->evaluate(true);   // Echoes output + perfdata and exits with the state code
$check->evaluate();       // Returns [$state, $text, $perfData] without exiting
```

### Example

```php
use Icinga\Module\Cpl\Check\Check;

$check = new Check('Disk usage', 85.5, true, '%');
$check->warnOutOfRange('70');
$check->critOutOfRange('90');
$check->setCustomCheckResult(2); // sets result to critical even though it would evalutate to a warning
$check->evaluate(true);
// [CRITICAL] Disk usage: Value '85.500000%' ...
// 'disk_usage'=85.500000%;70;90;;
```

## CheckPackage <a id="cpl-library-checkpackage"></a>

`CheckPackage` aggregates one or more `Check` objects and nested packages into a
single state and an indented output tree. It is the building block used by the
CLI commands to render many services as one check.

### Constructor

```php
new CheckPackage(
    $name,            // Name of the package
    $operator = "AND",// Aggregation operator: "AND" or "OR"
    $showOk = true,   // Whether to render OK checks
    $allowEmpty = false, // Whether an empty package is OK instead of UNKNOWN
    $screenWrapper = null // Screen wrapper for coloring (defaults to a no-op wrapper)
)
```

### Aggregation semantics

* **AND** — the package state is the **maximum** state of all children.
* **OR** — the package state is the **minimum** state of all children.

### Methods

| Method | Description |
|--------|-------------|
| `add($child)` | Add a `Check` or nested `CheckPackage`. |
| `hasChecks()` | Returns whether any child has been added. |
| `evaluate($exit = false)` | Aggregate and render. With `$exit = true` it echoes the result and exits with the state code; otherwise it returns `[$state, $text, $perfData]`. |

### Example

```php
use Icinga\Module\Cpl\Check\Check;
use Icinga\Module\Cpl\Check\CheckPackage;
use Icinga\Module\Cpl\Check\ScreenWrapper;

$package = new CheckPackage('My Services', 'AND', true, false, new ScreenWrapper($screen));
$package->add($check1);
$package->add($check2);
$package->evaluate(true);
// [CRITICAL] My Services
//   _ [CRITICAL] Service A on host1 ...
//   _ [OK] Service B on host2 ...
// '...' | ...
```

## Threshold <a id="cpl-library-threshold"></a>

`Threshold` parses and evaluates numeric ranges. It is normally used indirectly
through the `warnOutOfRange()` / `critOutOfRange()` methods of `Check`, but can
also be used directly:

```php
$threshold = new Threshold('70:90');
list($violated, $text) = $threshold->doesViolate(95);
// [true, "is not OK (bigger than 90)"]
```

Supported range formats:

| Range | Meaning |
|-------|---------|
| `90` | Warning/critical at exactly `90`. |
| `70:90` | OK between `70` and `90`, outside of it is a violation. |
| `:90` | OK up to `90`. |
| `70:` | OK from `70`. |
| `@70:90` | Inverted — violation only *between* `70` and `90`. |

## ScreenWrapper / DummyScreen <a id="cpl-library-screen"></a>

`ScreenWrapper` colors the `[OK]`, `[WARNING]`, `[CRITICAL]` and `[UNKNOWN]`
tokens of rendered output. It wraps any object that provides a `colorize()`
method and falls back to `DummyScreen` (which returns the text unchanged) when no
such object is available.

```php
$screen = new ScreenWrapper($yourScreen); // $yourScreen must implement colorize()
$text = $screen->colorize('[CRITICAL] something failed');
```

## DirectorConfig <a id="cpl-library-directorconfig"></a>

`DirectorConfig` syncs a check command and its datafields from a PHP array into
the Icinga Director. It is used by the `icingacli cpl director setup` command to
create the `icingacli_cpl_monitoring_services` and `icingacli_cpl_icingadb_services`
commands.

```php
use Icinga\Module\Cpl\Director\DirectorConfig;

$helper = new DirectorConfig($commandOptions, $categoryId);
$command = $helper->createCommand();
$helper->syncCommand($command);
$helper->syncDatafields($command);
```

See [CLI Commands](04-CLI-Commands.md#cpl-cli-commands) for the shape of
`$commandOptions`.
