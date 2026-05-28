---
description: DTO-based validation using MapRequestPayload and Symfony Validator constraints
globs: src/Modules/**/DTO/*.php,src/Dto/**/*.php
---

# DTO Validation

Use `#[MapRequestPayload]` with DTOs for request validation. This replaces the deprecated `ValidationRequest` pattern.

## DTO Structure

```php
namespace Core\Modules\Auth\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final class LoginDTO
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Email]
        public readonly string $username,

        #[Assert\NotBlank]
        #[Assert\Length(min: 8)]
        public readonly string $password
    ) {}
}
```

## Controller Usage

```php
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;

#[Route('/core/api/v1/auth/login', methods: ['POST'])]
class LoginController
{
    public function __invoke(#[MapRequestPayload] LoginDTO $dto): JsonResponse
    {
        // $dto is validated automatically
        // Invalid requests return 422 with validation errors
        return new OkResponse($this->authService->login($dto->username, $dto->password));
    }
}
```

## Common Constraints

```php
#[Assert\NotBlank]
#[Assert\NotNull]
#[Assert\Email]
#[Assert\Length(min: 1, max: 255)]
#[Assert\Positive]
#[Assert\Choice(['option1', 'option2'])]
#[Assert\Valid]  // For nested DTOs
```

## DTO Location

- Module DTOs: `Core\Modules\{Name}\DTO\`
- Shared DTOs: `Core\Dto\`

## Deprecated Pattern

Do NOT use `ValidationRequest` for new code:

```php
// DEPRECATED
class LoginRequest extends ValidationRequest
{
    protected function rules(): Assert\Collection { }
}

// USE THIS
final class LoginDTO
{
    public function __construct(
        #[Assert\NotBlank] public readonly string $field
    ) {}
}
```
