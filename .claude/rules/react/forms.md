---
description: Form patterns with React Hook Form, Zod, and shadcn/ui
globs: react/src/**/*.tsx
---

# Form Patterns

Use React Hook Form + Zod + shadcn/ui Form components.

## Basic Form

```typescript
import { useForm } from "react-hook-form";
import { zodResolver } from "@hookform/resolvers/zod";
import { z } from "zod";
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from "@2solar/ui";
import { Input } from "@2solar/ui";

const schema = z.object({
  name: z.string().min(1, "Required"),
  email: z.string().email(),
});

type FormValues = z.infer<typeof schema>;

export function MyForm() {
  const form = useForm<FormValues>({
    resolver: zodResolver(schema),
    defaultValues: { name: "", email: "" }, // Always provide defaults
  });

  return (
    <Form {...form}>
      <form onSubmit={form.handleSubmit(onSubmit)}>
        <FormField
          control={form.control}
          name="name"
          render={({ field }) => (
            <FormItem>
              <FormLabel>Name</FormLabel>
              <FormControl>
                <Input {...field} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <Button type="submit" disabled={form.formState.isSubmitting}>
          Submit
        </Button>
      </form>
    </Form>
  );
}
```

## With API Mutation

```typescript
import { useMutation } from "@tanstack/react-query";
import { postUsersMutation } from "@/api/client/@tanstack/react-query.gen";

const mutation = useMutation(postUsersMutation());

const onSubmit = (data: FormValues) => {
  mutation.mutate(
    { body: data },
    {
      onSuccess: () => form.reset(),
    },
  );
};
```

## ✅ DO: Use `useWatch` Instead of `form.watch`

**Always prefer `useWatch` hook over `form.watch` method:**

```typescript
// ✅ DO: Use useWatch hook
import { useWatch } from "react-hook-form";

const selectedEvents = useWatch({ control: form.control, name: "events" }) ?? [];
const productId = useWatch({ control: form.control, name: "productId" });

// ❌ DON'T: Use form.watch method
const selectedEvents = form.watch("events");
```

**Why:** `useWatch` is a hook that properly subscribes to form state changes and triggers re-renders when watched values change. `form.watch` is a method that doesn't create proper subscriptions.

## Key Rules

1. **Always use `zodResolver`** for validation
2. **Always provide `defaultValues`** for all fields (prevents uncontrolled input warnings)
3. **Use shadcn/ui Form components** (`FormField`, `FormItem`, `FormLabel`, `FormControl`, `FormMessage`)
4. **Use generated mutations** from `@/api/client/@tanstack/react-query.gen` for API calls
5. **Reset form in dialog** when it closes: `useEffect(() => { if (!open) form.reset(); }, [open])`
6. **Use generated Zod schemas** from `@/api/client/zod.gen` when available
7. **Prefer `useWatch` hook** over `form.watch` method for watching form values
