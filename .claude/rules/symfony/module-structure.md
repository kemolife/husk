---
description: Module/Domain folder structure for Symfony code in src/Modules
globs: src/**/*.php
---

# Module Structure

**All new Symfony code** must use the Module/Domain pattern under `src/Modules/`.

Do NOT create new controllers in `src/Controllers/` or services in `src/Services/` - these are deprecated.

IMPORTANT: Check phparkitect.php to understand how to structure the application.

## Module Hierarchy

```
Modules/
├── CRM/
│   ├── Request/
│   │   ├── Types/
│   │   └── Sources/
│   ├── Person/
│   └── Location/
├── Quote/
│   ├── Templates/
│   ├── Invoice/
│   ├── Materiallist/
│   └── Integrations/          # Exact, Prets, other integrations
├── Solar/
│   ├── Drawing/
│   └── Configurators/
├── Heatpump/
│   └── Heatbox/
├── Fusebox/
│   ├── Vekto/
│   └── Elektramat/
├── Product/
│   ├── Prices/
│   └── Sets/
├── Form/
│   └── CustomCollection/
├── Client/
│   ├── User/
│   ├── Role/
│   └── Config/
├── Monitoring/
│   └── Alerts/
├── Checklist/
│   └── ...
└── Notification/
    ├── Email/
    └── SMS/
```

## Module Folder Structure

Each module/submodule follows this internal structure:

```
src/Modules/{ModuleName}/
├── Controller/        # Invokable controllers
├── DTO/              # Request/Response DTOs with validation
├── Service/          # Business logic services
├── Repository/       # Data access (if needed)
└── Event/            # Event listeners/subscribers
```

## Namespace Convention

- `Core\Modules\{ModuleName}\Controller\`
- `Core\Modules\{ModuleName}\DTO\`
- `Core\Modules\{ModuleName}\Service\`

Nested modules use nested namespaces:
- `Core\Modules\CRM\Request\Controller\`
- `Core\Modules\Quote\Invoice\Service\`

## Example

```php
// Core\Modules\CRM\Request\Controller\CreateRequestController
namespace Core\Modules\CRM\Request\Controller;

use Core\Modules\CRM\Request\DTO\CreateRequestDTO;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/core/api/v1/crm/request', name: 'crm_request_create', methods: ['POST'])]
class CreateRequestController
{
    public function __invoke(#[MapRequestPayload] CreateRequestDTO $dto): JsonResponse
    {
        // ...
    }
}
```

## Legacy Structure (Deprecated)

The flat structure (`Core\Controllers\`, `Core\Services\`) is deprecated for new code.
Only use for maintaining existing endpoints.

## When to Create a Module

Create a new Module when:
- Adding a new domain/feature area
- The feature has controllers + services + DTOs

Keep in existing flat structure when:
- Fixing bugs in existing code
- Small changes to legacy endpoints

## Class Design

- `final readonly class` for DTOs and value objects only. Services, handlers, and other classes that may need mocking must NOT be `final` (PHPUnit 11 cannot mock final classes).

## Doctrine: repositories vs services

Two rules, both enforced statically (PHPArkitect + PHPStan disallowed-calls):

**Repositories** extending `ServiceEntityRepository` MUST NOT inject `EntityManagerInterface`. The base class already exposes `$this->getEntityManager()`, plus inherited `$this->createQueryBuilder()`, `$this->find()`, `$this->findBy()`, `$this->findOneBy()`. Use those.

**Services** MUST NOT call `persist()`, `remove()`, `find()`, `getReference()`, or `getRepository()` on `EntityManagerInterface`. Inject the repository and call `$this->fooRepository->save($entity)` / `->delete($entity)` / `->find($id)` instead.

Services MAY inject `EntityManagerInterface` (as `$writerEntityManager`) only to control transaction boundaries — `wrapInTransaction()`, `flush()`, `beginTransaction()`, `commit()`, `rollback()`. Anything else is a smell.

Repositories that need to write must expose `save(Entity $entity): void` / `delete(Entity $entity): void` methods (which call `$this->getEntityManager()->persist()` / `->remove()`). Repositories never call `flush()` — the service controls the transaction boundary.

Use `$loggingEntityManager` only when targeting the non-default `logging` entity manager (audit DB).

**Use `findOneBy()` / `findBy()` for simple equality lookups** — only reach for `createQueryBuilder` when you genuinely need JOINs, `NOT IN` / `IN` with multiple values, comparison operators (`>`, `<`, `!=`), `ORDER BY`, or subqueries. A method that calls `createQueryBuilder` with nothing but `->where('p.id = :id')->andWhere('p.client = :clientId')` is shallow: delete it and the caller uses `findOneBy` directly with zero loss.

```php
// ❌ Unnecessary QueryBuilder
public function findByIdAndClient(int $id, int $clientId): ?FooEntity
{
    return $this->createQueryBuilder('f')
        ->where('f.id = :id')
        ->andWhere('f.client = :clientId')
        ->setParameter('id', $id)
        ->setParameter('clientId', $clientId)
        ->getQuery()
        ->getOneOrNullResult();
}

// ✅ Use inherited method
public function findByIdAndClient(int $id, int $clientId): ?FooEntity
{
    return $this->findOneBy(['id' => $id, 'client' => $clientId]);
}
```

## PHP Standards

- Never return `array<string, mixed>` from public service/repository methods -- use DTOs or value objects
- Always use `JSON_THROW_ON_ERROR` with `json_decode()` and `json_encode()`
- External API responses must be deserialized into DTOs, never used as raw arrays
- Use `wrapInTransaction()` for multi-step database writes
- Never catch bare `\Exception` -- catch specific exception types
- Document `@throws` on public methods that throw exceptions
