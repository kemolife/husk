---
description: Symfony controller patterns - invokable controllers, routing, responses
globs: src/Modules/**/Controller/*.php,src/Controllers/**/*.php
---

# Controller Patterns

## Routing

- Use `#[Route]` attribute on controller class
- Set firewall: `defaults: ['firewall' => PublicRequestMatcher::PUBLIC_FIREWALL]`
- Default firewall is `all` (requires authenticated user)

## Response Classes

Use standardized responses from `Core\Framework\Responses\` for **success responses only**:

```php
return new OkResponse($data);           // 200
return new CreatedResponse($data);      // 201
return new NoContentResponse();         // 204
```

For errors, throw exceptions — see Error Handling below.

## Error Handling

Throw exceptions — never return error response objects. The `ExceptionListener` formats all exceptions into the standard `ResourceErrors` JSON format (`{"errors": [...], "meta": {}}`).

**For controller-level checks**, use Symfony HTTP exceptions directly:

```php
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

// Null-check
$guide = $this->guideService->getGuide($guideId);
if ($guide === null) {
    throw new NotFoundHttpException('Guide not found');
}

// Business rule violation
if (!$service->canProcess($id)) {
    throw new BadRequestHttpException('Cannot process this request');
}
```

**For service-level errors**, use domain exceptions with `#[WithHttpStatus]`. The exception propagates through the controller uncaught — no try/catch needed:

```php
// In the exception class (in the module's Exception/ folder):
use Symfony\Component\HttpKernel\Attribute\WithHttpStatus;
use Symfony\Component\HttpKernel\Attribute\WithLogLevel;
use Psr\Log\LogLevel;

#[WithHttpStatus(404)]
#[WithLogLevel(LogLevel::WARNING)]
final class ProjectNotFoundException extends \RuntimeException {}

// In the service — just throw:
throw new ProjectNotFoundException('Project not found');

// In the controller — no try/catch, the exception propagates to ExceptionListener:
$result = $this->projectService->getProject($id);
```

**For user-facing flash messages** (frontend toast notifications), use `FlashableHttpException`:

```php
use Core\Framework\Exception\FlashableHttpException;
use Symfony\Component\HttpFoundation\Response;

throw new FlashableHttpException(
    statusCode: Response::HTTP_UNAUTHORIZED,
    message: 'Unauthorized',
    flashMessage: __('You are not authorized to access this resource.'),
);
```

**Do not:**
- Return `BadRequestResponse`, `NotFoundResponse`, or any error response class
- Catch domain exceptions in controllers just to convert them to error responses
- Use magic status code numbers — always use `Response::HTTP_*` constants

## OpenAPI Response Documentation

Use `ResourceResponse` attribute to document API responses. This enables typed response generation for the frontend API client.

**Single object response:**
```php
use Core\Framework\Attributes\OpenAPI\ResourceResponse;
use Core\Modules\Example\DTO\ExampleDto;

#[OA\Get(
    summary: 'Get example',
    responses: [
        new ResourceResponse(
            response: 200,
            description: 'Example details',
            content: ExampleDto::class,
        ),
    ],
)]
```

**Paginated list response:**
```php
use Core\Framework\Attributes\OpenAPI\ResourcePaginatedResponse;

#[OA\Get(
    summary: 'List examples',
    responses: [
        new ResourcePaginatedResponse(
            response: 200,
            description: 'List of examples',
            content: ExampleDto::class,
        ),
    ],
)]
```

This generates typed `meta` with `PaginationMetaData` (currentPage, pageSize, totalCount, totalPages).

**Response DTO requirements:**
- Add `#[OA\Schema()]` attribute to the DTO class
- Use typed properties for proper schema generation
- Place in `Core\Modules\{Module}\DTO\` namespace

```php
use OpenApi\Attributes as OA;

#[OA\Schema()]
final class ExampleDto
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
    ) {}
}
```

## Testing

- Must be covered by HTTP tests.

## Do Not

- Extend base controller classes
- Put business logic in controllers
- Use Table or Repository classes in controllers.
- Use `currentUser()` helper - use `#[CurrentUser] SymfonyUser $user`
- Return raw arrays - use `Resource::make()`
