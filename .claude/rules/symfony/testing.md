---
description: Symfony testing patterns - Application/Integration/Unit test structure
globs: tests/Symfony/**/*.php
---

# Symfony Testing

All new Symfony/Core code tests go in `tests/Symfony/` following the Application/Integration/Unit pattern.

IMPORTANT: Mock as little as possible! Prefer real classes and default DI unless a class has side-effects we don't want in tests (HTTP calls, filesystem, queues, third-party SDKs). Use real DB for all database interactions.

## Required Coverage for Application Tests

Every Application test for a controller must include:
- Happy path (200/201)
- Validation failure (422) with invalid/missing payload
- Unauthorized (401) without session
- Forbidden (403) without required permission
- Not found (404) for non-existent resources (if applicable)

Use fixture builders for test data (never raw arrays or manual INSERT). Mock only external side-effects (HTTP clients, filesystem, queues) -- use real DB.

## Test Types

### Application Tests (E2E)

**Location:** `tests/Symfony/Application/Modules/{Module}/Controller/`
**Base Class:** `WebTestCase`

```php
#[BackupGlobals(enabled: true)]
class GetSomethingControllerTest extends WebTestCase
{
    private KernelBrowser $browser;
    private TestAuth $testAuth;

    protected function setUp(): void
    {
        parent::setUp();
        $this->browser = static::createClient();
        $this->testAuth = static::getContainer()->get(TestAuth::class);
    }

    public function testReturnsExpectedData(): void
    {
        $user = UserEntityFactory::createOne();
        $this->browser->loginUser($this->testAuth->userFor($user));

        $this->browser->jsonRequest('GET', '/core/api/v1/x/'.$user->getClientId());

        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $data = json_decode($this->browser->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('expected', $data['data']['field']);
    }
}
```

### Integration Tests

**Location:** `tests/Symfony/Integration/Modules/{Module}/Service/` or `/Repository/`
**Base Class:** `KernelTestCase`

```php
class SomeServiceTest extends KernelTestCase
{
    public function testServiceMethod(): void
    {
        $service = static::getContainer()->get(SomeService::class);
        $result = $service->doSomething();
        $this->assertNotNull($result);
    }
}
```

### Unit Tests

**Location:** `tests/Symfony/Unit/Modules/{Module}/` -- **Base Class:** `PHPUnit\Framework\TestCase`

Prefer real entities over mocks. No database, no container.

```php
class SomeTransformerTest extends TestCase
{
    public function testTransform(): void
    {
        $result = (new SomeTransformer())->toDto(new SomeEntity(name: 'Test'));
        $this->assertNull($result->id); // ID generated on save
        $this->assertEquals('Test', $result->name);
    }
}
```

## Factories

Factories live in `tests/Core/Factory/Modules/{Path}/{Name}Factory.php`.

```php
class SomeEntityFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return SomeEntity::class;
    }

    protected function defaults(): array
    {
        return [
            'name' => self::faker()->word(),
        ];
    }
}
```

## When to Use Each Type

| Scenario | Test Type |
|----------|-----------|
| Testing API endpoint response | Application |
| Testing authentication/authorization | Application |
| Testing service business logic with DB | Integration |
| Testing repository queries | Integration |
| Testing transformers/normalizers | Unit |
| Testing DTOs/Value Objects | Unit |
| Testing pure calculations | Unit |

## Naming Conventions

- Classes: `{ClassName}Test.php` -- Methods: `test{Behavior}` -- Factories: `{Entity}Factory.php`

## Foundry Factories and Doctrine Relations

**Use relation properties, not FK integer fields**, when an entity has a `ManyToOne` relation.

```php
// ❌ WRONG — clientId is not a property on the entity; Foundry silently ignores it
ClientPageUploadEntityFactory::createOne(['clientId' => 42]);

// ✅ CORRECT — pass a real entity object via the relation property name
$client = ClientEntityFactory::createOne();
ClientPageUploadEntityFactory::createOne(['client' => $client]);
```

**Detecting the pattern:** If the entity declares `#[ORM\ManyToOne] private ?FooEntity $foo`, the factory key is `'foo'`, not `'fooId'` or `'foo_id'`.

If the entity declares `#[ORM\Column] private ?int $fooId` (scalar FK, no relation), then `'fooId'` is correct.

In factory `defaults()`, nullable relations default to `null` — never to a random integer:

```php
protected function defaults(): array
{
    return [
        'client' => null,   // ManyToOne → null is a valid default
        'status' => Status::ONLINE,
    ];
}
```

**In tests:** always create the related entity first, then reference it. Use its auto-generated ID for subsequent assertions — never hardcode integer IDs like `42` when the entity is created via Foundry.

## Running Tests

```bash
npm run test                                                 # Migrate + run all tests
npm run test:run                                             # Run without migration
docker exec main-app vendor/bin/phpunit tests/Symfony/Application/.../Test.php  # Specific file
docker exec main-app vendor/bin/phpunit --filter testMethodName                 # Specific method
```
