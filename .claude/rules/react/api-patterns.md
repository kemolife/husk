---
description: API integration patterns using hey-api openapi-ts generated code
globs: react/src/**/*.ts,react/src/**/*.tsx
---

# API Integration Patterns

## Overview

This project uses [hey-api/openapi-ts](https://heyapi.dev/openapi-ts/get-started) to automatically generate TypeScript clients, Zod schemas, and TanStack Query options from the Symfony backend's OpenAPI specification. **Always prefer using the generated code over manually writing API integration code.**

## Generated Code Location

All generated code is located in `src/api/client/`:

- **Query Options**: `@/api/client/@tanstack/react-query.gen`
- **Zod Schemas**: `@/api/client/zod.gen`
- **TypeScript Types**: `@/api/client/types.gen`
- **Client Functions**: `@/api/client/sdk.gen`

## ✅ DO: Use Generated Query Options

**Always use generated query options for API endpoints with OpenAPI specifications:**

```typescript
// ✅ DO: Import and use generated query options
import { useSuspenseQuery } from "@tanstack/react-query";
import { getLayoutSidebarSettingsOptions } from "@/api/client/@tanstack/react-query.gen";

export function MyComponent() {
  const { data } = useSuspenseQuery(getLayoutSidebarSettingsOptions());
  return <div>{data.data?.navigation}</div>;
}
```

```typescript
// ✅ DO: Use in route loaders
import { createFileRoute } from "@tanstack/react-router";
import { getLayoutSidebarSettingsOptions } from "@/api/client/@tanstack/react-query.gen";

export const Route = createFileRoute("/settings")({
  beforeLoad: async ({ context }) => {
    await context.queryClient.ensureQueryData(getLayoutSidebarSettingsOptions());
  },
  component: SettingsComponent,
});
```

## ✅ DO: Use Generated Mutations

```typescript
// ✅ DO: Use generated mutation options with mutationOptions helper
import { useMutation } from "@tanstack/react-query";
import { mutationOptions } from "@tanstack/react-query";
import { postSettingsOriginsCreateMutation } from "@/api/client/@tanstack/react-query.gen";

export function useCreateOrigin() {
  return useMutation(
    mutationOptions({
      ...postSettingsOriginsCreateMutation(),
      onSuccess: () => {
        // Handle success
      },
    }),
  );
}
```

## ✅ DO: Invalidate Queries After Mutations

**Always use generated query key functions to invalidate queries:**

```typescript
// ✅ DO: Use generated query key function with _id extraction
import { useQueryClient } from "@tanstack/react-query";
import { getSettingsOriginsListQueryKey } from "@/api/client/@tanstack/react-query.gen";

const queryClient = useQueryClient();

// After successful mutation:
queryClient.invalidateQueries({
  queryKey: [
    {
      _id: getSettingsOriginsListQueryKey({ query: { currentPage: 1, pageSize: 10 } })[0]._id,
    },
  ],
});
```

**Pattern explanation:**
- Generated query keys return an array where the first element has an `_id` property
- Extract `[0]._id` to match all queries with the same base key
- This invalidates all list queries regardless of pagination parameters

```typescript
// ❌ DON'T: Manual query key invalidation
queryClient.invalidateQueries({
  queryKey: ["settings", "origins"],
});
```

## ✅ DO: Use Generated Zod Schemas

```typescript
// ✅ DO: Use generated Zod schemas for validation
import { UserSchema } from "@/api/client/zod.gen";

function validateUserData(data: unknown) {
  return UserSchema.parse(data);
}
```

## ✅ DO: Extend Generated Options When Needed

If you need to add additional configuration to generated options, you can extend them:

```typescript
// ✅ DO: Extend generated options with additional config
import { queryOptions } from "@tanstack/react-query";
import { getLayoutSidebarSettingsOptions } from "@/api/client/@tanstack/react-query.gen";

export const customSidebarOptions = (enabled: boolean) =>
  queryOptions({
    ...getLayoutSidebarSettingsOptions(),
    enabled,
  });
```

## ❌ DON'T: Create Manual Query Keys

**Never create manual query key factories for API endpoints that have OpenAPI specs:**

```typescript
// ❌ DON'T: Manual query keys for OpenAPI endpoints
import { createQueryKeyFactory } from "@/lib/query-key-factory";

export const myFeatureKeys = createQueryKeyFactory("my-feature", {
  all: ["my-feature"] as const,
  detail: (id: number) => ["detail", id] as const,
});
```

**Instead, use the generated query options which already include proper query keys.**

## ❌ DON'T: Create Manual Fetch Functions

**Never create manual fetch functions for API endpoints that have OpenAPI specs:**

```typescript
// ❌ DON'T: Manual fetch functions for OpenAPI endpoints
import { client } from "@/api/client";

export const getMyData = async () => {
  return client.get("/core/api/v1/my-endpoint").json();
};
```

**Instead, use the generated client functions from `@/api/client/sdk.gen` or the generated query options.**

## ❌ DON'T: Create Manual Query Options

**Never create manual query options for API endpoints that have OpenAPI specs:**

```typescript
// ❌ DON'T: Manual query options for OpenAPI endpoints
import { queryOptions } from "@tanstack/react-query";
import { myFeatureKeys } from "./query-keys";

export const myFeatureQueryOptions = (id: number) =>
  queryOptions({
    queryKey: myFeatureKeys.detail(id),
    queryFn: () => getMyData(id),
  });
```

**Instead, use the generated query options from `@/api/client/@tanstack/react-query.gen`.**

## ⚠️ Legacy Endpoints Exception

**Only for legacy endpoints without OpenAPI specifications**, you may need to create custom fetch functions. However, prefer migrating these endpoints to the Core Framework with OpenAPI documentation:

```typescript
// ⚠️ ONLY for legacy endpoints without OpenAPI specs
// src/features/layout/api/settings.ts
import { client } from "@/api/client";

export const updateExperimentalFeatures = async (newDesign: boolean) => {
  const formData = new FormData();
  formData.append("form_type", "experimental_features");
  formData.append("new_design", newDesign ? "1" : "0");
  return client.post("/_ma/m/setting/a/save", { body: formData }).json<number>();
};
```

## ✅ DO: Always Use Generated Types

**CRITICAL: When creating fullstack features, ALWAYS infer types from generated backend types instead of creating manual types:**

```typescript
// ✅ DO: Import and use generated types
import type { CreateOriginRequest, Origin } from "@/api/client/types.gen";

// ✅ DO: Infer types from generated schemas
import { CreateOriginRequestSchema } from "@/api/client/zod.gen";
type CreateOriginFormSchema = z.infer<typeof CreateOriginRequestSchema>;

// ❌ DON'T: Create manual types for OpenAPI endpoints
type CreateOriginRequest = {
  name: string;
};
```

**When creating a feature:**
1. **If backend exists**: Check `src/api/client/types.gen.ts` FIRST for all types
2. **If backend doesn't exist yet**: Create backend with OpenAPI spec first, generate types (`npm run generate:api`), THEN create frontend
3. **Never create manual types** that duplicate what the backend already defines
4. **Always regenerate types** after backend changes: `npm run generate:api`

## Checking for Generated Code

Before creating any manual API code:

1. **Check generated types FIRST**: Look in `src/api/client/types.gen.ts` for TypeScript types - **ALWAYS use these for fullstack features**
2. **Check generated query options**: Look in `src/api/client/@tanstack/react-query.gen.ts` for your endpoint
3. **Check generated client**: Look in `src/api/client/sdk.gen.ts` for client functions
4. **Check generated schemas**: Look in `src/api/client/zod.gen.ts` for validation schemas

## Migration Path

If you encounter old code using manual patterns:

1. **Identify the endpoint**: Determine which API endpoint it calls
2. **Check for OpenAPI spec**: Verify if the endpoint has an OpenAPI specification
3. **Find generated code**: Look for generated query options in `@/api/client/@tanstack/react-query.gen`
4. **Replace imports**: Change from manual patterns to generated code
5. **Remove old code**: Delete manual query keys, fetch functions, and query options files

## Benefits

- **Type Safety**: Full TypeScript type safety based on OpenAPI specification
- **No Manual Maintenance**: Code is automatically generated from API specs
- **Consistency**: Frontend and backend stay in sync automatically
- **Less Code**: No need to write query keys, query options, or fetch functions
- **Validation**: Zod schemas are automatically generated for runtime validation
