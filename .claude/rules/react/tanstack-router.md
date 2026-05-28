---
description: TanStack Router patterns specific to this project
globs: react/src/routes/**/*.tsx
---

# TanStack Router

> For TanStack Router API documentation, use **Context7**.
> This file covers project-specific patterns only.

## Route Structure

```
src/routes/
├── __root.tsx                    # Root layout with devtools
├── _authenticated/               # Protected routes (requires auth)
│   ├── _layout.tsx              # Auth check, redirects if not logged in
│   ├── _app/                    # App shell with sidebar/header
│   │   ├── _layout.tsx          # Sidebar + header layout
│   │   └── monitoring/          # Feature routes
│   ├── modules/
│   │   └── $requestId/          # Dynamic route with request context
│   └── settings/
```

## Root Route Context

See `src/routes/__root.tsx` for context definition:

```tsx
// ✅ DO: Use createRootRouteWithContext with proper typing
export const Route = createRootRouteWithContext<{
  user?: ReturnType<typeof useUser>["data"];
  queryClient: QueryClient;
  config: ReactConfig;
}>()({
  component: RootLayout,
  wrapInSuspense: true,
});
```

## Authentication Pattern

See `src/routes/_authenticated/_layout.tsx`:

```tsx
// ✅ DO: Check auth in beforeLoad, redirect if not authenticated
import { authUserQueryOptions } from "@/features/auth/api/query-options";

export const Route = createFileRoute("/_authenticated")({
  beforeLoad: async ({ context }) => {
    const user = await context.queryClient.ensureQueryData(authUserQueryOptions);
    if (!user) {
      throw redirect({ to: "/ds" as never });
    }
    return { user }; // User available in child routes via context
  },
  component: AuthenticatedLayout,
});
```

## Data Loading

See `src/routes/_authenticated/_app/_layout.tsx` and `monitoring/overview.tsx`:

```tsx
// ✅ DO: Use loader with generated query options from OpenAPI client
import { getLayoutSidebarOptions } from "@/api/client/@tanstack/react-query.gen";

export const Route = createFileRoute("/_authenticated/_app")({
  loader: async ({ context }) => {
    await context.queryClient.ensureQueryData(getLayoutSidebarOptions());
  },
  component: RouteComponent,
  pendingComponent: () => <LoadingSkeleton />,
});

function RouteComponent() {
  // Data is guaranteed to exist - use useSuspenseQuery
  const { data } = useSuspenseQuery(getLayoutSidebarOptions());
  return <div>{data.data?.navigation}</div>;
}
```

```tsx
// ✅ DO: For routes with search params, validate and use in loader
import z from "zod";

export const Route = createFileRoute("/_authenticated/_app/monitoring/overview")({
  validateSearch: z.object({
    categoryId: z.number().optional(),
  }),
  loader: async ({ context }) => {
    await context.queryClient.ensureQueryData(overviewCustomersQueryOptions(defaultParams));
  },
  head: () => ({
    meta: [{ title: i18n.t("Monitoring Alerts") }],
  }),
  component: MonitoringOverviewPage,
});
```

## Accessing Route Context

```tsx
// ✅ DO: Use useRouteContext with specific route path
import { useRouteContext } from "@tanstack/react-router";

function MonitoringOverviewPage() {
  const { user } = useRouteContext({ from: "/_authenticated/_app/monitoring/overview" });
  return <div>{user.user.isSupport && <AdminPanel />}</div>;
}
```

## Route Parameters

```tsx
// ✅ DO: Use Route.useParams() for typed params
export const Route = createFileRoute("/_authenticated/modules/$requestId")({
  component: () => {
    const { requestId } = Route.useParams();
    return <div>Request: {requestId}</div>;
  },
});

// ✅ DO: Use Route.useSearch() for search params
function MonitoringPage() {
  const categoryId = Route.useSearch({ select: (search) => search.categoryId });
}
```

## Navigation

```tsx
// ✅ DO: Use Link component for declarative navigation
import { Link } from "@tanstack/react-router";

<Link to="/monitoring/overview" search={{ categoryId: 5 }}>
  View Category
</Link>;

// ✅ DO: Use useNavigate for programmatic navigation
import { useNavigate } from "@tanstack/react-router";

const navigate = useNavigate();
navigate({ to: "/settings/origins" });
```

## Pending States

```tsx
// ✅ DO: Provide pendingComponent for loading states
export const Route = createFileRoute("/_authenticated/_app")({
  loader: async ({ context }) => {
    /* ... */
  },
  component: RouteComponent,
  pendingComponent: () => (
    <div className="flex items-center justify-center">
      <Spinner />
    </div>
  ),
});
```
