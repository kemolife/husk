# Logging

Use PSR-3 `LoggerInterface` for all logging. ERROR+ is routed to Sentry via the Monolog handler.

## Acquiring a logger

**Symfony services** — constructor injection:

```php
use Psr\Log\LoggerInterface;

public function __construct(
    private readonly LoggerInterface $logger,
) {}
```

**Legacy Zend / procedural code** — global helper:

```php
logger()->error('Something broke', ['exception' => $e]);
```

**Static contexts** (DTO factories, value-object static methods) — `Core\Symfony\StaticLogger`:

```php
use Core\Symfony\StaticLogger;

public static function fromArray(array $data): ?self
{
    if (! self::isValid($data)) {
        StaticLogger::get()->warning('Bad shape', ['keys' => array_keys($data)]);
        return null;
    }
    // ...
}
```

`StaticLogger::get()` resolves the same `LoggerInterface` from the container as constructor injection — same Sentry routing, same fallback. Use it only when constructor injection is genuinely impossible (e.g. inside a `static` method on a `final readonly` DTO). For ordinary services, prefer constructor injection.

## Severity model

| Level | Goes to Sentry | When to use |
|---|---|---|
| `debug` | no | local debugging only |
| `info` | no | routine events, completions |
| `notice` | no | unusual but not problematic |
| `warning` | no | recoverable issues, deprecations |
| `error` | **yes** | something failed and needs investigation |
| `critical` | **yes** | broken subsystem |
| `alert` | **yes** | severe, immediate handling expected |
| `emergency` | **yes** | site down |

The Sentry threshold is set once in `src/config/packages/monolog.yaml`. Do not add per-call-site routing.

## Logging vs alerting

*Logging* = recording an event. *Alerting* = waking someone up. These are different concerns. Picking a higher severity does not "page someone harder" — alerting rules live in the Sentry UI. The right question at a call site is: what severity describes this event?

## Context conventions

- **Always pass exceptions via `['exception' => $e]`.** Sentry's Monolog handler extracts the full stack trace from this key.
- **Static log message, dynamic context.** Sentry groups events by message template — interpolating IDs into the message blows up the issue count.

```php
// good
$this->logger->error('Failed to sync customer', ['customerId' => $id, 'exception' => $e]);

// bad — dynamic ID in message fragments grouping
$this->logger->error("Failed to sync customer $id: " . $e->getMessage());
```

- **No PII in messages.** Free-form names, emails, and addresses go in context with descriptive keys.

## What `error` means here

- Use `error` for: a request failed and needs human investigation; an integration returned an unexpected response; an exception was caught but couldn't be recovered.
- Use `warning` for: a retry that eventually succeeded; a deprecated path was hit; a known-flaky third party was handled.
- Don't `error` for expected-but-noteworthy things — that's `warning`.

## Forbidden patterns

These are enforced by PHPStan and PHPArkitect; CI fails if they appear:

- `\Sentry\captureException(...)`
- `\Sentry\logger()->...`
- `\Sentry\withScope(...)`
- `\Sentry\init(...)` outside the Symfony bundle wiring
- `Rollbar::*` (the package is removed)
