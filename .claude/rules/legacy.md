---
globs: local/modules/**/*.php,local/single_page/**/*.php,local/library/**/*.php
description: Legacy Zend/Wolfeh patterns for maintaining existing code
---

# Legacy Code Patterns

> **For new development**, use Symfony patterns instead.
> **For bigger changes**, refactor legacy code to Symfony instead.
> This file covers patterns for maintaining existing Zend/Wolfeh code.

## Routing

Legacy AJAX routes use `/_ma` prefix:
- Module: `m` parameter
- Action: `a` parameter
- Example: `/_ma/m/ds_person/a/personInfo/ds_person_id/123`

Get parameters via `Wolfeh_Core::getParam()` or `Wolfeh_Core::getParams()`.

## Controllers

Legacy controllers in `local/modules/{Module}/Controller.php`:
- Extend `Wolfeh_Controller`
- Actions are public methods
- Views via `$this->view('edit.php')`

## Data Access

Use Zend_Db_Table classes:

```php
// Table class in local/modules/{Module}/Table.php
class Table extends Zend_Db_Table_Abstract {
    protected $_name = 'table_name';
    protected $_primary = 'id';
}

// Usage
$table = new \Local\modules\Module\Table();
$row = $table->find($id)->current();
```

- Default adapter: `Zend_Registry::get('DBZ')`
- Read-only operations: `DBSLAVE`
- Always use prepared statements or Table abstractions

## Auth & Sessions

- Access user via `currentUser()` helper
- Verify ownership with `Param\Controller::validateParams`
- Never trust client-provided IDs without verification

## Logging

Use the `logger()` global helper:

```php
logger()->error('Something broke', ['exception' => $e]);
```

It returns `Psr\Log\LoggerInterface` from the Symfony container. ERROR+ is routed to Sentry via Monolog. See `.agents/rules/symfony/logging.md` for the full severity model and conventions.

Direct `Rollbar::*` is removed — the package is uninstalled.

## Input Sanitization

```php
Wolfeh_Core::sanitizer($input);
filter_var($input, FILTER_SANITIZE_*);
```

## Feature Flags

```php
if (feature()->isEnabled(FeatureFlagKeys::FEATURE_NAME)) {
    // Feature code
}
```

## Do Not

- Use legacy patterns for new API endpoints
- Refactor unrelated legacy code when fixing bugs
- Introduce new global helpers

## NEVER Use safe* Functions

**Never use these functions in new code. Instead ensure that the values are of the correct type.** They exist for legacy compatibility only:
- `safeAdd`, `safeSubtract`, `safeMultiply`, `safeDivide`
- `safeCount`, `safeInArray`, `safeNumberFormat`

All new code must be type-safe from the start. Use proper type hints and strict types.
