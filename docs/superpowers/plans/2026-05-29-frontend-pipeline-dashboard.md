# Frontend Implementation Plan

## 1. Tech Stack

**Routing:** TanStack Router (file-based, type-safe params and search params, loader pattern for data prefetch)

**Server state:** TanStack Query v5 (polling via `refetchInterval`, optimistic mutations for approve/reject, automatic background refetch on tab focus)

**Client UI state:** Plain `useState` and `useRef` — no Zustand needed at this scope. The only non-server state is: which job is selected in the log panel, and whether log auto-scroll is active.

**Styling:** Tailwind CSS v4. No component library. Hand-rolled primitives stay small and match the Linear/Railway aesthetic without fighting a design system.

**DAG rendering:** CSS flexbox column with SVG connector lines for v1. Jobs in this domain are linear with occasional parallel branches — React Flow is not needed yet. If real DAG complexity arrives, drop in `@xyflow/react` without changing the surrounding architecture.

**Log rendering:** Plain `div` with monospace font and a scroll-anchor `div` at the bottom. No virtual list in v1 — logs are bounded per job run. Add `@tanstack/react-virtual` if log volumes grow.

**HTTP:** Native `fetch` wrapped in a 60-line typed client. No Axios.

**Real-time (v1):** Adaptive polling via TanStack Query `refetchInterval`. Polling rate is 2 s when PENDING or RUNNING, 5 s when AWAITING\_APPROVAL, stopped on terminal states.

**Real-time (v2, log streaming):** `EventSource` / SSE against the planned `GET /pipeline-runs/{runId}/jobs/{jobId}/logs/stream` endpoint. Client hook is prepared in v1 but SSE is not wired until the backend endpoint exists.

**Build:** Vite + TypeScript strict mode.

**Reasoning for what was rejected:**
- Axios: no benefit over typed fetch at this scale
- MUI / Chakra: fights the aesthetic and ships too much CSS
- Redux: TanStack Query owns all server state; nothing left for Redux to do
- WebSocket: logs are unidirectional; SSE is the correct primitive
- React Flow (v1): the job list is shallow enough that flexbox + SVG handles it

---

## 2. File Structure

```
src/
├── api/
│   └── client.ts                      # Typed fetch wrapper, base URL from VITE_API_BASE_URL
│
├── routes/
│   ├── __root.tsx                     # Root layout, QueryClientProvider, RouterProvider
│   ├── index.tsx                      # Redirect to /pipelines
│   ├── pipelines/
│   │   ├── index.tsx                  # PipelinesIndexPage
│   │   └── $pipelineId/
│   │       ├── index.tsx              # PipelineDetailPage (run history)
│   │       └── runs/
│   │           └── $runId.tsx         # RunDetailPage (core screen)
│   └── runs/
│       └── index.tsx                  # RunHistoryPage (all runs, all pipelines)
│
├── features/
│   ├── pipelines/
│   │   ├── api.ts                     # fetchPipelines, triggerPipelineRun
│   │   ├── hooks/
│   │   │   └── useTriggerRun.ts       # useMutation wrapper for POST /pipelines/{id}/run
│   │   └── components/
│   │       ├── PipelineRow.tsx        # Single row in pipeline list
│   │       ├── PipelineCard.tsx       # Larger card variant for mobile / detail header
│   │       └── EnvironmentPicker.tsx  # Inline radio pills: development / staging / production
│   │
│   └── pipeline-runs/
│       ├── api.ts                     # fetchPipelineRun, approveJob, rejectJob
│       ├── hooks/
│       │   ├── usePipelineRun.ts      # useQuery with adaptive refetchInterval
│       │   ├── useApproveJob.ts       # useMutation with optimistic update
│       │   └── useRejectJob.ts        # useMutation with optimistic update
│       └── components/
│           ├── RunRow.tsx             # Single row in run history list
│           ├── RunHeader.tsx          # Run ID, status badge, environment, elapsed timer
│           ├── JobGraph.tsx           # Left panel: vertical DAG with SVG connectors
│           ├── JobNode.tsx            # Single node in the DAG (pill with status icon)
│           ├── ApprovalBanner.tsx     # Full-width sticky banner for AWAITING_APPROVAL
│           ├── ApprovalGateInline.tsx # Expanded node card inside the DAG column
│           ├── LogPanel.tsx           # Right panel: terminal-style log output
│           └── StatusPill.tsx         # Reusable status chip used everywhere
│
└── lib/
    ├── utils.ts                       # cn() (clsx + tailwind-merge), formatDuration, relativeTime
    └── constants.ts                   # STATUS enum values, POLL_INTERVALS
```

---

## 3. Routing

| URL | Page | Notes |
|---|---|---|
| `/` | redirect | Sends to `/pipelines` |
| `/pipelines` | PipelinesIndexPage | List of all pipelines with last-run status and inline Run button. Uses `GET /pipelines` (iteration 2 — stub with mock data until live). |
| `/pipelines/:pipelineId` | PipelineDetailPage | Pipeline config summary + paginated run history list. |
| `/pipelines/:pipelineId/runs/:runId` | RunDetailPage | Live DAG + log panel + approval banner. Primary destination after triggering a run. |
| `/runs` | RunHistoryPage | All runs across all pipelines. Search params: `?status=failed&environment=production&page=2`. Uses `GET /pipeline-runs` (iteration 2). |

**Route behaviors:**

- Triggering a run via `POST /pipelines/{id}/run` navigates immediately to `/pipelines/{id}/runs/{newRunId}` on 202.
- RunDetailPage begins polling on first render regardless of run age. Terminal-state runs render static (poll stops automatically).
- Every RunDetailPage URL is a permanent shareable link. Historical runs show static final state. Active runs show live state.

---

## 4. Key Components

**`StatusPill`**
Reusable everywhere. Accepts a `status` string and renders a colored dot, icon, label, and optional animation.

| Status | Color | Icon | Animate |
|---|---|---|---|
| pending | gray | hollow circle | none |
| running | blue | spinner | pulse |
| success | green | checkmark | none |
| failed | red | X | none |
| awaiting\_approval | amber | pause | slow pulse |
| skipped | gray | dash | none |

**`PipelineRow`**
One row in the pipeline list. Renders: pipeline name (link to detail), `StatusPill` for last run, environment badge, relative timestamp, inline Run button. Clicking Run opens an `EnvironmentPicker` slide-in (three radio pills). Confirming fires `useTriggerRun` and navigates to the new run.

**`EnvironmentPicker`**
Three radio-style pill buttons: development / staging / production. Default is staging. Production selection shows a small warning text inline ("Deploying to production"). No modal.

**`RunRow`**
One row in run history. Renders: run number, `StatusPill`, environment badge, relative timestamp, duration, chevron link to RunDetailPage.

**`RunHeader`**
Sticky header within RunDetailPage. Shows: pipeline name (breadcrumb link), run number, `StatusPill`, environment badge, elapsed timer (ticks client-side from run start time while RUNNING, fixed once terminal). Re-run button on right.

**`JobGraph`**
Left panel (fixed 320 px width) in RunDetailPage. Renders jobs as a vertical flexbox column with SVG `<line>` connectors between nodes. Parallel jobs (if the domain needs them in future) appear side by side as a horizontal row within the column. Each node is a `JobNode`. Clicking a node sets `selectedJobId` state, which drives log panel content.

**`JobNode`**
Pill-shaped button (~36 px tall). Left side: status icon. Center: job name. Right: duration (if complete) or elapsed (if running). Selected state: blue left border, slightly elevated shadow. AWAITING\_APPROVAL state: renders `ApprovalGateInline` instead of the default pill.

**`ApprovalGateInline`**
Expanded card within the DAG column that replaces a JobNode when that job is AWAITING\_APPROVAL. Shows job name, amber pulse icon, short description "Manual approval required", and two buttons: Approve (green) and Reject (outline). Reject shows an inline "Are you sure?" toggle before firing. No modal.

**`ApprovalBanner`**
Full-width sticky banner rendered at the bottom of RunDetailPage when `run.status === 'awaiting_approval'` or any job has status `awaiting_approval`. Blue background, not red (this is a decision point, not an error). Contains job name, Approve button (primary), Reject button (secondary). Does not block the log panel — positioned below it. Fades out after approval is confirmed.

**`LogPanel`**
Right panel in RunDetailPage. Dark background (`#0d1117`), monospace font (JetBrains Mono or system-monospace), 13 px, 1.6 line height. Renders log lines for the `selectedJobId`. Auto-scrolls to bottom via a scroll-anchor `div ref`. A "Scroll to bottom" pill appears if user scrolls up manually; disappears when they return to bottom. "RUNNING" state shows a blinking cursor after the last line. Terminal states show a final status line in green (success) or red (failed). Prepared for SSE in v2: accepts either a static `lines: string[]` prop or a streaming mode prop.

---

## 5. State and Data Fetching

**Server state:** TanStack Query owns everything. No prop-drilling of API data.

```typescript
// src/features/pipeline-runs/hooks/usePipelineRun.ts
export function usePipelineRun(runId: string) {
  return useQuery({
    queryKey: ['pipeline-run', runId],
    queryFn: () => fetchPipelineRun(runId),
    staleTime: 0,
    refetchInterval: (query) => {
      const status = query.state.data?.status
      if (status === 'running' || status === 'pending') return 2000
      if (status === 'awaiting_approval') return 5000
      return false
    },
    refetchIntervalInBackground: false,
  })
}
```

```typescript
// src/features/pipeline-runs/hooks/useApproveJob.ts
export function useApproveJob(runId: string) {
  const queryClient = useQueryClient()
  return useMutation({
    mutationFn: ({ jobId }: { jobId: string }) => approveJob(runId, jobId),
    onMutate: async ({ jobId }) => {
      await queryClient.cancelQueries({ queryKey: ['pipeline-run', runId] })
      const previous = queryClient.getQueryData(['pipeline-run', runId])
      queryClient.setQueryData(['pipeline-run', runId], (old: PipelineRun) => ({
        ...old,
        jobs: old.jobs.map(j =>
          j.job_id === jobId ? { ...j, status: 'running' } : j
        ),
      }))
      return { previous }
    },
    onError: (_err, _vars, ctx) => {
      queryClient.setQueryData(['pipeline-run', runId], ctx?.previous)
    },
    onSettled: () => {
      queryClient.invalidateQueries({ queryKey: ['pipeline-run', runId] })
    },
  })
}
```

**Client UI state:** Plain `useState` in `RunDetailPage`:

```typescript
const [selectedJobId, setSelectedJobId] = useState<string | null>(null)
const [logAutoScroll, setLogAutoScroll] = useState(true)
```

`selectedJobId` defaults to the first RUNNING job (or the first FAILED job on a finished run), auto-set by a `useEffect` that watches the run data.

**Trigger mutation:**

```typescript
// src/features/pipelines/hooks/useTriggerRun.ts
export function useTriggerRun() {
  const navigate = useNavigate()
  return useMutation({
    mutationFn: ({ pipelineId, environment }: TriggerPayload) =>
      triggerPipelineRun(pipelineId, environment),
    onSuccess: (data, { pipelineId }) => {
      navigate({
        to: '/pipelines/$pipelineId/runs/$runId',
        params: { pipelineId, runId: data.pipeline_run_id },
      })
    },
  })
}
```

**No global store.** TanStack Query's cache IS the store. Query keys are the identifiers. No Zustand, no Context for data.

---

## 6. Real-Time Updates

**v1 — Adaptive polling:**

- PENDING / RUNNING: poll every 2 s
- AWAITING\_APPROVAL: poll every 5 s
- SUCCESS / FAILED: polling stops (`refetchInterval: false`)
- Tab hidden: polling pauses (`refetchIntervalInBackground: false`)
- Tab regains focus: TanStack Query refetches immediately (built-in behavior)

This is sufficient for status, job status, and any log snapshots the backend includes in the run response.

**v2 — SSE log streaming (prepared but not wired):**

The `LogPanel` component accepts a `mode` prop: `'snapshot'` (default, uses static lines from polling) or `'stream'` (uses `EventSource`). The hook is written but `mode='stream'` is only enabled once the backend SSE endpoint is live.

```typescript
// src/features/pipeline-runs/hooks/useLogStream.ts
export function useLogStream(runId: string, jobId: string, enabled: boolean) {
  const [lines, setLines] = useState<string[]>([])

  useEffect(() => {
    if (!enabled) return
    const es = new EventSource(
      `${import.meta.env.VITE_API_BASE_URL}/pipeline-runs/${runId}/jobs/${jobId}/logs/stream`
    )
    es.onmessage = (e) => setLines(prev => [...prev, e.data])
    es.onerror = () => es.close()
    return () => es.close()
  }, [runId, jobId, enabled])

  return lines
}
```

When SSE is active for a job, polling of that job's log snapshot is skipped. Run-level status polling continues independently on its own interval.

**Decision boundary:** Do not add WebSocket. Logs are unidirectional. SSE handles reconnect automatically via the browser `EventSource` API. WebSocket would be appropriate only for an interactive terminal (future shell-exec feature).

---

## 7. Implementation Tasks

---

### Task 1 — Project scaffold and API client

**Files to create:**
- `vite.config.ts`
- `tsconfig.json` (strict mode)
- `src/api/client.ts`
- `src/lib/utils.ts`
- `src/lib/constants.ts`
- `.env.example` (`VITE_API_BASE_URL=http://localhost:8000`)

**What to build:**

Scaffold Vite + React + TypeScript project. Install: `@tanstack/react-query`, `@tanstack/react-router`, `tailwindcss`, `clsx`, `tailwind-merge`.

Write `client.ts`: a typed `apiFetch<T>` function that reads `VITE_API_BASE_URL`, sets `Content-Type: application/json`, throws on non-2xx with the parsed error body, and returns `T`. Write thin typed wrappers for all four backend endpoints:

```typescript
export const fetchPipelineRun = (runId: string): Promise<PipelineRun> => ...
export const triggerPipelineRun = (pipelineId: string, environment: string): Promise<{ pipeline_run_id: string }> => ...
export const approveJob = (runId: string, jobId: string): Promise<{ status: string }> => ...
export const rejectJob = (runId: string, jobId: string): Promise<{ status: string }> => ...
```

Write TypeScript types for `PipelineRun`, `JobRun`, `PipelineRunStatus`, `JobRunStatus` matching the backend response shape exactly.

Write `utils.ts`: `cn()` (clsx + twMerge), `formatDuration(ms: number): string`, `relativeTime(isoString: string): string`.

Write `constants.ts`: `POLL_INTERVALS`, `TERMINAL_STATUSES`, `ENVIRONMENTS`.

**How to test:** `tsc --noEmit` passes. `apiFetch` can be imported and called against the running Symfony backend with a known run ID.

---

### Task 2 — Router setup and root layout

**Files to create:**
- `src/routes/__root.tsx`
- `src/routes/index.tsx`
- `src/main.tsx`

**What to build:**

Configure TanStack Router with `createRootRouteWithContext` carrying `{ queryClient: QueryClient }`. Wrap the app in `QueryClientProvider` and `RouterProvider` in `main.tsx`. Root layout renders a minimal top nav bar (app name left, active run indicator dot right, settings gear far right) and an `<Outlet />`.

`index.tsx` redirects immediately to `/pipelines`.

Top nav active-run indicator: a small colored dot that reads from `useIsFetching()` as a rough proxy, or from a shared query for active runs once the list endpoint exists.

**How to test:** App boots, navigating to `/` redirects to `/pipelines`, 404 page renders for unknown routes.

---

### Task 3 — StatusPill component

**Files to create:**
- `src/features/pipeline-runs/components/StatusPill.tsx`

**What to build:**

Single component, no dependencies beyond Tailwind. Accepts `status: PipelineRunStatus | JobRunStatus`. Returns a `<span>` with a colored dot and label. Uses Tailwind `animate-pulse` for running and awaiting\_approval. Exports a `STATUS_CONFIG` map so other components can reuse the color/icon/label lookup without re-implementing it.

```typescript
type Props = {
  status: string
  size?: 'sm' | 'md'
}
```

**How to test:** Render each of the seven statuses in a dev page or Storybook story, verify correct color and animation.

---

### Task 4 — Pipeline list page (stub data)

**Files to create:**
- `src/routes/pipelines/index.tsx`
- `src/features/pipelines/components/PipelineRow.tsx`
- `src/features/pipelines/components/EnvironmentPicker.tsx`
- `src/features/pipelines/api.ts`
- `src/features/pipelines/hooks/useTriggerRun.ts`

**What to build:**

`PipelinesIndexPage` renders a list of `PipelineRow` components. Because `GET /pipelines` does not exist until iteration 2, use a static mock array of three pipelines (deploy-api, run-migrations, nightly-tests) defined in the route file. Add a `TODO` comment: "Replace with useQuery(['pipelines'], fetchPipelines) when iteration 2 ships."

`PipelineRow`: name (link to `/pipelines/:id`), `StatusPill` for last-run status (mocked), environment badge, relative timestamp, Run button. Clicking Run opens `EnvironmentPicker` as an inline slide-down (CSS transition, no portal). Confirming calls `useTriggerRun`.

`EnvironmentPicker`: three radio pill buttons. Default: staging. Production shows a warning text "Deploying to production" in amber. No modal, no confirmation dialog.

`useTriggerRun`: `useMutation` wrapping `triggerPipelineRun`, navigates to RunDetailPage on success, shows inline error text on failure (not a toast — error appears beneath the Run button).

**How to test:** Click Run on a pipeline row, pick staging, confirm — verify navigation to `/pipelines/{id}/runs/{newRunId}` with a real backend run ID in the URL.

---

### Task 5 — Run detail page shell and data hook

**Files to create:**
- `src/routes/pipelines/$pipelineId/runs/$runId.tsx`
- `src/features/pipeline-runs/hooks/usePipelineRun.ts`
- `src/features/pipeline-runs/components/RunHeader.tsx`

**What to build:**

`RunDetailPage` fetches `usePipelineRun(runId)`. Handles three render states:
- Loading: skeleton placeholder for left and right panels
- Error (404): "Run not found" message with back link
- Data: two-column layout (JobGraph left 320 px fixed, LogPanel right fills remaining width)

`usePipelineRun`: `useQuery` with adaptive `refetchInterval` as shown in section 5. Stops polling on SUCCESS or FAILED.

`RunHeader`: pipeline name (link), run number, `StatusPill`, environment badge, elapsed timer. Timer uses a `useInterval` hook that ticks every second while the run is not in a terminal state.

`RunDetailPage` holds two pieces of `useState`: `selectedJobId` (string | null) and `logAutoScroll` (boolean). A `useEffect` auto-sets `selectedJobId` to the first RUNNING job when run data arrives, or to the first FAILED job once the run is in a terminal FAILED state.

**How to test:** Navigate to a known run URL. Verify the page polls and updates the header status while the run is active. Verify polling stops after terminal status. Verify 404 state with a non-existent run ID.

---

### Task 6 — JobGraph component

**Files to create:**
- `src/features/pipeline-runs/components/JobGraph.tsx`
- `src/features/pipeline-runs/components/JobNode.tsx`

**What to build:**

`JobGraph`: receives `jobs: JobRun[]`, `selectedJobId: string | null`, `onSelectJob: (id: string) => void`. Renders jobs in a vertical flex column. Between each adjacent pair of nodes, renders a short SVG `<line>` (or a CSS border) as the connector. The SVG approach: a thin 2 px `#374151` vertical line centered horizontally between nodes, 16 px tall.

For v1, treat the job list as fully sequential (no parallel branching). A `TODO` comment marks where horizontal split columns would be introduced for concurrent jobs.

`JobNode`: pill-shaped `<button>` (36 px height). Props: `job: JobRun`, `isSelected: boolean`, `onSelect`. Left: status icon (use `STATUS_CONFIG` from `StatusPill`). Center: `job.job_id` as the display name. Right: duration text if terminal, elapsed if running (driven by parent timer), empty if pending. Selected: `border-l-2 border-blue-500 shadow-md`. AWAITING\_APPROVAL: renders `ApprovalGateInline` instead of the normal pill layout.

**How to test:** Render a mock run with all seven job statuses. Click each node and verify `selectedJobId` updates. Verify the AWAITING\_APPROVAL node expands inline.

---

### Task 7 — LogPanel component

**Files to create:**
- `src/features/pipeline-runs/components/LogPanel.tsx`

**What to build:**

`LogPanel`: receives `lines: string[]` (from polling snapshot for now), `isStreaming: boolean`, `status: JobRunStatus`. Renders a `<div>` with dark background, monospace font, padding. Maps lines to `<div>` elements. A `<div ref={anchorRef} />` sits at the bottom as the scroll anchor.

Auto-scroll logic: a `useEffect` on `lines` calls `anchorRef.current?.scrollIntoView({ behavior: 'smooth' })` when `logAutoScroll` is true. A scroll event listener on the container detects manual upward scroll and sets `logAutoScroll` to false. A "Scroll to bottom" pill button appears when `logAutoScroll` is false; clicking it scrolls to anchor and re-enables auto-scroll.

When `isStreaming` is true and status is RUNNING: a blinking cursor `▋` appended to the last line via CSS `animate-ping` equivalent.

Final status lines: append a styled line after the last log line — green "Process exited with code 0" for SUCCESS, red "Process exited with code 1 (failed)" for FAILED.

`LogPanel` accepts an optional `mode: 'snapshot' | 'stream'` prop. In snapshot mode, `lines` comes from the parent. Stream mode is wired but disabled until Task 14.

**How to test:** Render with 200 mock log lines. Verify auto-scroll tracks new lines. Scroll up manually and verify the pill appears. Click the pill and verify it returns to bottom.

---

### Task 8 — ApprovalBanner and ApprovalGateInline components

**Files to create:**
- `src/features/pipeline-runs/components/ApprovalBanner.tsx`
- `src/features/pipeline-runs/components/ApprovalGateInline.tsx`
- `src/features/pipeline-runs/hooks/useApproveJob.ts`
- `src/features/pipeline-runs/hooks/useRejectJob.ts`

**What to build:**

`ApprovalBanner`: renders only when any job in the run has status `awaiting_approval`. Full-width sticky div at the bottom of RunDetailPage (not inside either panel column — positioned as a sibling below the two-column layout, `position: sticky bottom-0 z-50`). Blue background. Text: "Manual approval required — [job name] is waiting". Two buttons: Approve (primary green) and Reject (outline). Approve fires `useApproveJob` immediately. Reject shows an inline toggle: "Confirm rejection?" with a red Confirm button and a Cancel link. Both buttons show a spinner while mutation is in-flight. Banner fades out (`opacity-0 transition-opacity`) after a successful mutation, then the optimistic update from TanStack Query makes the status change visible in the DAG immediately.

`ApprovalGateInline`: rendered by `JobNode` when the job status is `awaiting_approval`. Expanded card (~120 px height) with amber pulsing icon, job name, "Manual approval required" text, and the same Approve/Reject buttons. Calls the same hooks as `ApprovalBanner`. Both can coexist — they share the same mutation hooks so either can resolve the approval.

`useApproveJob` and `useRejectJob`: as described in section 5. Optimistic update sets job status to `running` (approve) or `failed` (reject) immediately, then `invalidateQueries` lets the next poll correct it.

**How to test:** Use a run that is currently AWAITING\_APPROVAL. Verify the banner appears. Click Approve — verify optimistic update changes the job node color in the DAG immediately, verify the banner fades, verify polling resumes and eventually shows the job as RUNNING or SUCCESS.

---

### Task 9 — Wire RunDetailPage panels together

**Files to create:** Changes to `src/routes/pipelines/$pipelineId/runs/$runId.tsx`

**What to build:**

Connect `JobGraph`, `LogPanel`, and `ApprovalBanner` into the RunDetailPage layout.

Two-column layout:
```
<div class="flex h-full">
  <div class="w-80 shrink-0 overflow-y-auto border-r">
    <JobGraph ... />
  </div>
  <div class="flex-1 overflow-hidden">
    <LogPanel lines={selectedJobLines} ... />
  </div>
</div>
<ApprovalBanner ... />
```

`selectedJobLines`: derived from `run.jobs.find(j => j.id === selectedJobId)`. For now, this is the job ID only — actual log lines are not in the run status response. Render a placeholder: "Log streaming coming in v2. Job status: {status}." This is explicitly marked as a `TODO` to replace once SSE or a log snapshot endpoint is live.

If no `selectedJobId`, `LogPanel` shows "Select a job to view logs."

Elapsed timer in `RunHeader`: `useEffect` with `setInterval(1000)` increments a counter while the run is not terminal. Seeded from run start time if available in the response (add an optional `started_at` field to the TypeScript type now even if the backend does not yet return it, so the type is ready).

**How to test:** End-to-end: trigger a pipeline run from the list page, verify navigation to RunDetailPage, verify the DAG updates as jobs progress, verify clicking a job node highlights it in the DAG.

---

### Task 10 — Pipeline detail page (run history)

**Files to create:**
- `src/routes/pipelines/$pipelineId/index.tsx`
- `src/features/pipeline-runs/components/RunRow.tsx`

**What to build:**

`PipelineDetailPage`: header with pipeline name and a "Run Again" button (same `useTriggerRun` as pipeline list). Below: a "Recent Runs" list.

Because `GET /pipeline-runs?pipeline_id=X` does not exist until iteration 2, stub with a static array of three mock runs. Add a `TODO` comment to replace with a `useQuery` when the endpoint ships.

`RunRow`: run number (link to RunDetailPage), `StatusPill`, environment badge, relative timestamp, duration, right-pointing chevron.

Show last 20 runs. A "Load more" text link at the bottom (no-op for now, wired to pagination once iteration 2 ships).

**How to test:** Click a pipeline name from the list page, verify the detail page loads with the mock run history, click a run row, verify navigation to RunDetailPage.

---

### Task 11 — Run history page (all runs)

**Files to create:**
- `src/routes/runs/index.tsx`

**What to build:**

`RunHistoryPage`: table view of all runs across all pipelines. Columns: Status, Pipeline, Environment, Duration, Triggered by (stub: "—"), When.

Search params (use TanStack Router `validateSearch`):
```typescript
z.object({
  status: z.string().optional(),
  environment: z.string().optional(),
  page: z.number().optional().default(1),
})
```

Filter bar above the table: status filter (dropdown or pill toggles), environment filter, clear filters link.

Stub with mock data. `TODO` comment to replace with `GET /pipeline-runs` once iteration 2 ships.

Each row links to `/pipelines/{pipelineId}/runs/{runId}`.

**How to test:** Navigate to `/runs`, verify table renders, verify URL search params update when filters are changed, verify filter values persist on page refresh.

---

### Task 12 — Error and loading states

**Files to create:** Changes distributed across existing route files

**What to build:**

Add consistent loading and error states to every page.

**Loading:** Each route exports a `pendingComponent`. For PipelinesIndexPage and RunHistoryPage: a skeleton table (gray rounded rects matching the row layout). For RunDetailPage: a two-column skeleton (left: stacked gray pills; right: terminal-shaped dark rect).

**Error:** TanStack Router `errorComponent` prop on each route. Renders a centered card: "Something went wrong", error message in monospace, and a "Try again" button that calls `router.invalidate()`.

**404 state on RunDetailPage:** if `fetchPipelineRun` throws a 404, render a specific "Run not found" message with a back link to the pipeline detail page.

**Empty states:**
- No pipelines: centered "No pipelines configured yet" text.
- No runs for a pipeline: "No runs yet — trigger the first run from the list page."
- No runs in history: "No runs match the current filters."

**How to test:** Load RunDetailPage with a non-existent run ID — verify 404 state. Throttle the network to slow 3G — verify skeleton loaders appear before data.

---

### Task 13 — Approve/Reject rejection confirmation UX

**Files to create:** Changes to `ApprovalBanner.tsx` and `ApprovalGateInline.tsx`

**What to build:**

The Reject flow requires one extra confirmation step before firing (production deployments are destructive). This is inline — no modal.

In both `ApprovalBanner` and `ApprovalGateInline`, Reject button behavior:

1. First click: button text changes to "Confirm Reject?" with a Cancel link appearing next to it. The original Reject button turns red.
2. Second click on "Confirm Reject?": fires `useRejectJob` mutation.
3. Clicking Cancel: resets back to the original Reject button text.

State for this is local `useState<'idle' | 'confirming'>` inside each component.

Approve has no confirmation. Clicking Approve fires immediately (the main protection is the AWAITING\_APPROVAL gate existing at all — the user had to explicitly define an approval job in their pipeline).

**How to test:** Click Reject on an approval banner, verify the confirmation step appears, verify Cancel resets it, verify Confirm fires the mutation and updates the DAG.

---

### Task 14 — SSE log streaming hook (wired but gated)

**Files to create:**
- `src/features/pipeline-runs/hooks/useLogStream.ts`

**What to build:**

Write `useLogStream(runId: string, jobId: string, enabled: boolean): string[]` as described in section 6. The hook returns an array of log lines that grows as SSE events arrive.

In `LogPanel`, add a prop `sseEnabled: boolean`. When true, use `useLogStream` for lines instead of the snapshot prop. Default is false.

In `RunDetailPage`, set `sseEnabled={false}` for all jobs. Add a comment: "Set sseEnabled={true} once GET /pipeline-runs/{runId}/jobs/{jobId}/logs/stream is live (iteration 2 Task 12)."

This means the SSE infrastructure is present and testable against a manual backend stub without requiring any other changes to the page.

**How to test:** Point `VITE_API_BASE_URL` at a local SSE server stub (a simple Node script that streams fake log lines). Set `sseEnabled={true}` temporarily. Verify lines appear in `LogPanel` in real time. Verify the EventSource closes on component unmount.

---

### Task 15 — Visual polish and accessibility pass

**Files to create:** Changes across all component files

**What to build:**

- Add `aria-label` to all icon-only buttons (Run, Approve, Reject, scroll-to-bottom pill).
- Add `role="status"` and `aria-live="polite"` to `StatusPill` so screen readers announce status changes.
- Add `aria-busy="true"` to `RunHeader` while the run is not terminal.
- Keyboard navigation in `JobGraph`: each `JobNode` is a `<button>`, tab order follows the visual job order, Enter selects the job.
- Focus management after approval: after Approve or Reject mutation settles, move focus to the `RunHeader` status badge.
- Color contrast audit: verify all status colors pass WCAG AA (4.5:1) against the dark background. Adjust amber and gray tones if needed.
- Add `title` attributes to relative timestamps so hovering shows the absolute ISO timestamp.
- Ensure `LogPanel` is a `<section aria-label="Job logs">` with a live region for new lines (debounced to avoid announcing every line — announce only "New log output" once per second while streaming).

**How to test:** Run the app with VoiceOver (macOS) or NVDA (Windows). Verify status changes are announced. Verify all interactive elements are reachable by keyboard. Run Lighthouse accessibility audit, target score 90+.

---

## Dependency Install Summary

```bash
npm create vite@latest . -- --template react-ts
npm install @tanstack/react-query @tanstack/react-router
npm install tailwindcss @tailwindcss/vite
npm install clsx tailwind-merge
npm install -D @tanstack/router-devtools @tanstack/react-query-devtools
```

Total production dependencies: 4 packages plus Vite and React. No component library, no state management library beyond TanStack Query. The bundle stays small and the dependencies stay auditable.