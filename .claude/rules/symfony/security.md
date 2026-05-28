---
description: Security attributes, voters, firewalls, and user types in Symfony
globs: src/Modules/**/Controller/*.php,src/Controllers/**/*.php,src/Security/**/*.php
---

# Security

## Security Attributes

Apply attributes to controllers for access control:

```php
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Core\Security\Attribute\ModuleAccess;
use Core\Security\Attribute\UserAccess;
use Core\Security\Attribute\ApiAccess;
use Core\Security\Attribute\ServerToServerToken;
use Core\Security\Attribute\FeatureFlag;

#[IsGranted('IS_AUTHENTICATED_FULLY')]  // REQUIRED: Verifies user is authenticated
#[UserAccess(UserVoter::RIGHT_ADMIN)]   // User right check via voter
#[ModuleAccess(['monitoring'])]         // Requires module access
#[ApiAccess]                            // Requires API key
#[ServerToServerToken]                  // Server-to-server auth
#[FeatureFlag('feature_name')]          // Requires feature flag
class MyController { }
```

**Important**: Always use `#[IsGranted('IS_AUTHENTICATED_FULLY')]` on protected endpoints.

### Validating request ownership

For controllers operating on a `DsRequestEntity` ({requestId} route parameter), use `#[MapEntity]` to load the entity. The global `ClientScopeFilter` automatically scopes the lookup to the current user's client(s); cross-client access yields a 404. Prefer `repository.findActive(requestId)` to also exclude offline/deleted requests:

```php
use Core\Modules\CRM\Request\Entity\DsRequestEntity;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;

public function __invoke(
    #[MapEntity(expr: 'repository.findActive(requestId)')] DsRequestEntity $dsRequest,
): JsonResponse {
    // $dsRequest is guaranteed to belong to the user's client and be active
}
```

## User Types

Three user types available via `#[CurrentUser]`:

```php
use Symfony\Component\Security\Http\Attribute\CurrentUser;

// Standard user (session-based)
public function __invoke(#[CurrentUser] SymfonyUser $user): JsonResponse
{
    $coreUser = $user->getCoreUser();  // Access legacy User object
    $clientId = $user->getClientId();
}

// API key user
public function __invoke(#[CurrentUser] ApiKeyUser $user): JsonResponse

// Server-to-server
public function __invoke(#[CurrentUser] ExternalServerToServerUser $user): JsonResponse
```

## Firewalls

Set firewall in route defaults:

```php
// Public route (no auth required)
#[Route('/public', defaults: ['firewall' => PublicRequestMatcher::PUBLIC_FIREWALL])]

// API route (requires API key)
#[Route('/api', defaults: ['firewall' => ApiHeaderRequestMatcher::API_FIREWALL])]

// Default: requires authenticated user (no defaults needed)
#[Route('/protected')]
```

## Do Not

- Use `currentUser()` helper in Symfony code
- Check permissions inside controller logic - use attributes
- Create session-dependent services
