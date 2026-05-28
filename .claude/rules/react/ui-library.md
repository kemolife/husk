---
description: '@2solar/ui component library patterns'
globs: react/src/**/*.ts,react/src/**/*.tsx
---

## @2solar/ui Component Library

**Always use the Storybook MCP** when working with @2solar/ui components. Use `list-all-documentation` to discover components, `get-documentation` for props/usage, and `get-documentation-for-story` for specific variants.

When using components from @2solar/ui:

1. **Always wrap your app** with `SollitUIProvider` from `@2solar/ui`
2. **Import CSS**: Use `@import "@2solar/ui/tailwind.css"` (Tailwind) or `import "@2solar/ui/styles.css"` (precompiled)
3. **Component selection guide**: See `node_modules/@2solar/ui/docs/COMPONENT_GUIDE.md` for when to use which component
4. **TypeScript definitions**: All components have JSDoc - hover over imports for usage guidance
5. **i18n**: Components use `useLocale()` - configure via `SollitUIProvider` `localeConfig` prop
6. **Shadow DOM**: If rendering in Shadow DOM, configure `shadowPortal` on `SollitUIProvider`

### Common Patterns

- **Modals**: Use `Dialog` for regular modals, `AlertDialog` for destructive confirmations
- **Tables**: Use `DataTable` for client-side, `ApiDataTable` for server-side pagination/filtering
- **Forms**: Use `Form`, `FormField`, `FormItem` with react-hook-form + Zod
- **Overlays**: `Popover` for lightweight, `Sheet` for side panels, `Drawer` for mobile
- **Command palette**: Use `CommandDialog` with `Command`, `CommandInput`, `CommandList`, `CommandItem`

### Gotchas

- DataTable columns need `meta.label` for display names
- Portal components (Dialog, Popover, Select) respect Shadow DOM config automatically
- Many components support `loading` prop for async states
- Use `aria-invalid` for form validation error states
