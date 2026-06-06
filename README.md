# Conveyor

A self-hosted pipeline orchestrator that runs **any Linux command in an isolated Docker container**. Define a pipeline in YAML, trigger it, and watch jobs execute as a DAG with real-time status, log capture, and manual approval gates.

Think of it as a general-purpose job runner — closer to AWS Batch + Step Functions than a git-bound CI tool. Each job picks its own image; the workspace volume is shared across the run so jobs can pass artifacts along.

```
┌──────────┐    ┌──────────┐    ┌──────────┐
│  build   │───▶│ unit-test│───▶│  deploy  │
│ composer │    │  alpine  │    │  alpine  │
└──────────┘    └──────────┘    └──────────┘
   each job = one ephemeral container, shared /workspace volume
```

---

## Features

- **YAML pipelines** — jobs with `image`, `script`, `needs` (DAG dependencies), and `if` conditions
- **Real container execution** — talks to the Docker daemon over its Unix socket, pulls images, runs scripts, captures stdout/stderr
- **DAG scheduling** — independent jobs run in parallel; dependents wait for their `needs`
- **Approval gates** — pause the pipeline for manual approve/reject before continuing
- **Environment conditionals** — `if: environment == production` skips jobs that don't apply
- **Async execution** — jobs run on a worker via Symfony Messenger + RabbitMQ, with retry + dead-letter
- **Modern dashboard** — React + TanStack, live polling, terminal-style log viewer, approval banner
- **REST API + Console** — trigger and inspect runs over HTTP or CLI

---

## Architecture

Strict hexagonal (ports & adapters). The domain owns the DAG evaluation and state machines; infrastructure plugs in behind interfaces.

```
src/
├── Domain/                  # Pure domain logic (state machines, DAG)
│   ├── Pipeline/            # Pipeline, Job value objects
│   ├── PipelineRun/         # PipelineRun + JobRun aggregates, status enums
│   └── Shared/              # Environment, exceptions
│
├── Application/             # Use cases
│   ├── Command/             # TriggerPipeline, ExecuteJob, ApprovePipelineJob
│   ├── Query/               # GetPipelineRunStatus
│   └── Port/                # ExecutorPort, repository ports (interfaces)
│
└── Infrastructure/          # Adapters
    ├── Executor/            # DockerSocketExecutorAdapter, FakeExecutorAdapter
    ├── Persistence/         # Doctrine + YAML + InMemory repositories
    ├── Http/                # Controllers
    └── Console/             # CLI commands

frontend/                    # React + Vite dashboard (TanStack Router/Query, Tailwind)
```

**Execution topology:** the HTTP layer dispatches `TriggerPipelineCommand` (synchronous — creates the run record), then `ExecuteJobCommand`s go to RabbitMQ. A worker container with the Docker socket mounted consumes them and spawns one ephemeral container per job.

---

## Stack

| Layer | Tech |
|---|---|
| Backend | PHP 8.4, Symfony 8.0 |
| Persistence | Doctrine ORM 3, PostgreSQL 16 |
| Queue | Symfony Messenger + RabbitMQ 3.13 |
| Executor | Docker Engine API over Unix socket |
| Frontend | React, TanStack Router/Query, Tailwind CSS v4, Vite |

---

## Getting Started

### 1. Start the infrastructure

```bash
docker compose up -d --build
```

This starts four services: `app` (API on :8080), `worker` (job consumer, has the Docker socket), `db` (Postgres), `rabbitmq` (queue + management UI on :15672).

### 2. Create the database schema

```bash
docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction
```

### 3. Start the frontend

```bash
cd frontend
npm install
npm run dev
```

Dashboard at **http://localhost:5173**. API at **http://localhost:8080**.

---

## Defining a Pipeline

Drop a `.yaml` file into `pipelines/`. The filename (without extension) is the pipeline ID.

```yaml
name: Example Pipeline

jobs:
  build:
    image: composer:2
    script: composer --version

  unit-test:
    needs: build
    image: alpine:3.19
    script: echo "All tests passed"

  lint:
    needs: build
    image: alpine:3.19
    script: echo "Lint OK"

  deploy-staging:
    needs: [unit-test, lint]
    if: environment == staging
    image: alpine:3.19
    script: echo "Deployed to staging"

  approve-prod:
    needs: deploy-staging
    type: approval

  deploy-prod:
    needs: approve-prod
    if: environment == production
    image: alpine:3.19
    script: echo "Deployed to production"
```

| Field | Meaning |
|---|---|
| `image` | Docker image the job runs in. Must provide its own tools. |
| `script` | Shell command run via `/bin/sh -c`. |
| `needs` | Job ID or list of IDs that must succeed first (DAG edge). |
| `if` | `environment == <env>` — skip the job if it doesn't match the run's environment. |
| `type: approval` | Manual gate. Pipeline pauses until approved or rejected. |

---

## Usage

### Console

```bash
# Trigger a run
docker compose exec app php bin/console pipeline:run example --env=staging

# Check status
docker compose exec app php bin/console pipeline:status <run-id>

# Approve / reject a gate
docker compose exec app php bin/console pipeline:approve <run-id> <job-id> approve
docker compose exec app php bin/console pipeline:approve <run-id> <job-id> reject
```

### REST API

| Method | Path | Purpose |
|---|---|---|
| `GET` | `/pipelines` | List available pipelines |
| `POST` | `/pipelines/{id}/run` | Trigger a run (`{"environment":"staging"}`) → `202` + run ID |
| `GET` | `/pipeline-runs?pipeline_id={id}` | List runs for a pipeline |
| `GET` | `/pipeline-runs/{runId}` | Run status + per-job status & logs |
| `POST` | `/pipeline-runs/{runId}/jobs/{jobId}/approve` | Approve a gate |
| `POST` | `/pipeline-runs/{runId}/jobs/{jobId}/reject` | Reject a gate |

```bash
curl -X POST http://localhost:8080/pipelines/example/run \
  -H "Content-Type: application/json" \
  -d '{"environment":"staging"}'
```

---

## Testing

```bash
./vendor/bin/phpunit                          # full suite
./vendor/bin/phpunit --filter PipelineRunTest # single test

cd frontend && npx tsc --noEmit               # frontend type check
```

---

## State Machines

**JobRun:** `PENDING → RUNNING → SUCCESS | FAILED`, plus `AWAITING_APPROVAL` (approval jobs) and `SKIPPED` (condition unmet, or a `needs` job was skipped).

**PipelineRun:** `PENDING → RUNNING → SUCCESS | FAILED`, plus `AWAITING_APPROVAL` while a gate is open.

All transitions are guarded in the domain — invalid transitions throw `InvalidStatusTransitionException`.

---

## Roadmap

Next iteration is planned in [`docs/superpowers/plans/2026-05-29-pipeline-orchestrator-iteration-2.md`](docs/superpowers/plans/2026-05-29-pipeline-orchestrator-iteration-2.md): webhook triggers, secrets injection, job retry/timeout, matrix builds, log streaming (SSE), notifications, API authentication, and rollback support.

---

## License

MIT
