---
description: React project structure and feature organization patterns
globs: react/src/**/*.ts,react/src/**/*.tsx
---

# Project Structure

This React app uses a **micro-frontend architecture** embedded in a PHP application via Shadow DOM.

## Folder Layout

```
src/
├── api/
│   ├── client/            # Generated OpenAPI client (types, Zod schemas, TanStack Query options)
│   └── client.ts          # Legacy API client (deprecated)
├── components/
│   └── ui/                # shadcn/ui components (Radix-based)
├── context/               # React contexts (e.g., shadow-root.tsx)
├── features/              # Feature-based organization
│   └── {feature}/
│       ├── api/           # Feature-specific API (legacy endpoints only)
│       ├── components/    # Feature-specific components
│       ├── hooks/         # Feature-specific hooks
│       └── types/         # Feature-specific TypeScript types
├── hooks/                 # Global custom hooks
├── lib/                   # Utility libraries
│   ├── mount.tsx          # Component mounting for PHP
│   ├── query-client.ts    # TanStack Query config
│   ├── query-key-factory.ts
│   └── i18n.ts            # Internationalization
├── routes/                # TanStack Router file-based routes
├── types/                 # Global TypeScript types
├── utils/                 # Global utility functions
└── index.css              # Global styles + Tailwind
```

## Feature Organization

Each feature in `src/features/` mirrors the root structure:

```
src/features/monitoring/
├── api/           # Only for legacy endpoints without OpenAPI specs
├── components/    # MonitoringCard.tsx, StationList.tsx, etc.
├── hooks/         # useStations.ts, useMonitoringData.ts
└── types/         # monitoring.types.ts
```

### Where to Put Code

| Type                        | Location                                    |
| --------------------------- | ------------------------------------------- |
| Feature-specific components | `src/features/{feature}/components/`        |
| Global hooks                | `src/hooks/`                                |
| Feature-specific hooks      | `src/features/{feature}/hooks/`             |
| API calls (OpenAPI)         | Use generated client from `src/api/client/` |
| API calls (legacy)          | `src/features/{feature}/api/`               |
| Global types                | `src/types/`                                |
| Feature types               | `src/features/{feature}/types/`             |
| Routes/pages                | `src/routes/`                               |
| Utilities                   | `src/utils/`                                |

## Key Technologies

| Technology      | Purpose       | Location                       |
| --------------- | ------------- | ------------------------------ |
| TanStack Query  | Server state  | `src/lib/query-client.ts`      |
| TanStack Router | Routing       | `src/routes/`                  |
| @2solar/ui      | UI components | `@2solar/ui`                   |
| OpenAPI client  | API calls     | `src/api/client/`              |
| i18next         | Translations  | `src/lib/i18n.ts`              |
| Zod             | Validation    | Generated in `src/api/client/` |
| React Hook Form | Forms         | Via shadcn Form component      |

## Creating a New Feature

1. Create folder: `src/features/{feature-name}/`
2. Add subfolders as needed: `components/`, `hooks/`, `types/`
3. Only add `api/` if calling legacy endpoints without OpenAPI specs
4. For new API endpoints, use the generated client from `src/api/client/`

## Mounting in PHP

Components are registered in `config/components.ts` and mounted into PHP pages via Shadow DOM. The mounting logic is in `src/lib/mount.tsx`.
