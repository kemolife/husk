# Deployment Pipeline Orchestrator — Design Spec

## Overview

A Symfony 8 backend implementing a CI/CD pipeline orchestrator using hexagonal architecture. Pipelines are defined in YAML, executed asynchronously via Symfony Messenger, with a DAG job execution engine, approval gates, and pluggable executor adapters. Entry points: REST API + Symfony Console.

---

## Scope

### In scope (iteration 1)
- YAML pipeline definition parsing
- DAG job ordering (`needs:`)
- Simple conditionals (`if: environment == X`)
- State machine: `PENDING → RUNNING → AWAITING_APPROVAL → SUCCESS/FAILED/SKIPPED`
- Approval gates (manual approval via API/Console before continuing)
- Async execution via Symfony Messenger
- REST API + Console command entry points
- `FakeExecutorAdapter` (simulates execution, no real SSH)
- PHPUnit tests at domain, application, and HTTP layers

### Next iteration
- `SSHExecutorAdapter` (real SSH execution via phpseclib)
- Log streaming (real-time stdout/stderr capture)

---

## Pipeline YAML Format

```yaml
name: main-pipeline

jobs:
  build:
    image: php:8.4-cli        # container image (used by DockerExecutorAdapter, next iteration)
    script: composer install --no-dev

  unit-test:
    needs: build
    image: php:8.4-cli
    script: php bin/phpunit

  lint:
    needs: build
    image: php:8.4-cli
    script: php bin/console lint:yaml config/

  docker-build:
    needs: [unit-test, lint]
    image: docker:24-cli
    script: docker build -t myapp:latest .

  deploy-staging:
    needs: docker-build
    if: environment == staging
    image: alpine:3.19
    script: ./deploy.sh staging

  approve-prod:
    needs: deploy-staging
    type: approval

  deploy-prod:
    needs: approve-prod
    if: environment == production
    image: alpine:3.19
    script: ./deploy.sh production
```

`needs:` accepts string (single) or array (multiple). Jobs with satisfied `needs:` and passing `if:` run in parallel.

`image:` is stored in `Job` value object but ignored by `FakeExecutorAdapter` (iteration 1). Used by `DockerSocketExecutorAdapter` (next iteration) to pull and run the container.

---

## Architecture

Strict hexagonal. No Symfony/Doctrine imports in `Domain/` or `Application/`.

```
src/
├── Domain/
│   ├── Pipeline/
│   │   ├── Pipeline.php             # aggregate: name, job definitions
│   │   ├── Job.php                  # value object: id, script, needs[], condition, type
│   │   ├── JobType.php              # enum: SCRIPT | APPROVAL
│   │   └── PipelineId.php
│   │
│   ├── PipelineRun/
│   │   ├── PipelineRun.php          # aggregate: owns state machine, DAG evaluation
│   │   ├── JobRun.php               # entity: one job execution instance
│   │   ├── JobRunStatus.php         # enum: PENDING|RUNNING|SUCCESS|FAILED|AWAITING_APPROVAL|SKIPPED
│   │   ├── PipelineRunStatus.php    # enum: PENDING|RUNNING|SUCCESS|FAILED|AWAITING_APPROVAL
│   │   └── PipelineRunId.php
│   │
│   └── Shared/
│       ├── Environment.php          # enum: DEVELOPMENT | STAGING | PRODUCTION
│       └── InvalidStatusTransitionException.php
│
├── Application/
│   ├── Command/
│   │   ├── TriggerPipeline/
│   │   │   ├── TriggerPipelineCommand.php
│   │   │   └── TriggerPipelineHandler.php
│   │   ├── ExecuteJob/
│   │   │   ├── ExecuteJobCommand.php
│   │   │   └── ExecuteJobHandler.php
│   │   └── ApprovePipelineJob/
│   │       ├── ApprovePipelineJobCommand.php
│   │       └── ApprovePipelineJobHandler.php
│   ├── Query/
│   │   └── GetPipelineRunStatus/
│   │       ├── GetPipelineRunStatusQuery.php
│   │       └── GetPipelineRunStatusHandler.php
│   └── Port/
│       ├── PipelineRepositoryPort.php      # findById(PipelineId): Pipeline
│       ├── PipelineRunRepositoryPort.php   # save/findById for PipelineRun
│       └── ExecutorPort.php               # run(Job, Environment): JobResult
│
└── Infrastructure/
    ├── Persistence/
    │   ├── Doctrine/
    │   │   ├── DoctrinePipelineRunRepository.php
    │   │   └── DoctrineJobRunRepository.php
    │   └── Yaml/
    │       └── YamlFilePipelineRepository.php
    ├── Executor/
    │   └── FakeExecutorAdapter.php
    ├── Http/
    │   └── Controller/
    │       ├── TriggerPipelineController.php
    │       ├── GetPipelineRunController.php
    │       └── ApprovePipelineJobController.php
    └── Console/
        ├── TriggerPipelineCommand.php
        └── ApprovePipelineJobCommand.php
```

---

## Domain Model

### Pipeline (aggregate)
Immutable. Parsed from YAML files stored in `pipelines/` directory at project root (e.g. `pipelines/main-pipeline.yaml`). Pipeline ID = filename without extension. Contains ordered `Job` value objects.

### Job (value object)
```
id: string
image: string|null       # Docker image (e.g. "php:8.4-cli") — ignored by FakeExecutorAdapter
script: string|null
needs: string[]
condition: string|null   # "environment == staging" | "environment == production" | null (always run)
type: JobType            # SCRIPT | APPROVAL
```

### PipelineRun (aggregate)
Stateful. One instance per execution. Core domain logic lives here:

```php
// Returns jobs whose needs: are all SUCCESS and condition passes
public function readyJobs(Environment $env): JobRun[]

// Returns true when all JobRuns are in terminal state
public function isComplete(): bool

// Domain enforces valid transitions only
public function markAsRunning(): void
public function markAsComplete(): void
```

### JobRun (entity)
Tracks state of one job within a PipelineRun. Enforces valid transitions. Throws `InvalidStatusTransitionException` on invalid state change.

---

## Persistence

Domain classes carry Doctrine attributes (pragmatic choice — avoids dual-model boilerplate, focus stays on ports/adapters pattern).

`Pipeline` is not persisted — loaded fresh from YAML on each trigger via `YamlFilePipelineRepository`.

`PipelineRun` + `JobRun` persisted via Doctrine + PostgreSQL.

---

## Local Infrastructure (docker-compose.yml)

```
┌─────────┐  ┌──────────────────────┐  ┌──────────┐  ┌───────────┐
│   app   │  │       worker         │  │    db    │  │ rabbitmq  │
│Symfony  │  │messenger:consume     │  │Postgres  │  │AMQP queue │
│API :8080│  │has /docker.sock      │  │:5432     │  │UI :15672  │
└─────────┘  └──────────┬───────────┘  └──────────┘  └───────────┘
                        │ via Docker socket (next iteration)
                        ▼ HOST Docker daemon
               creates dynamically per job:
            ┌──────────────┐  ┌──────────────┐
            │ job-{runId}  │  │ job-{runId}  │
            │ build job    │  │ test job     │
            │ /workspace ◄─┼──┼─► /workspace │  ← shared named volume
            │ DIES after   │  │ DIES after   │
            └──────────────┘  └──────────────┘
                   volume: pipeline-run-{runId} (created before first job, deleted on completion)
```

```yaml
# docker-compose.yml
services:
  app:
    build: .
    ports: ["8080:80"]
    environment:
      DATABASE_URL: postgresql://app:app@db:5432/pipeline
      MESSENGER_TRANSPORT_DSN: amqp://app:app@rabbitmq:5672/%2f/messages
    depends_on: [db, rabbitmq]

  worker:
    build: .
    command: php bin/console messenger:consume async --time-limit=3600
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock
    environment:
      DATABASE_URL: postgresql://app:app@db:5432/pipeline
      MESSENGER_TRANSPORT_DSN: amqp://app:app@rabbitmq:5672/%2f/messages
    depends_on: [db, rabbitmq]

  db:
    image: postgres:16-alpine
    environment:
      POSTGRES_DB: pipeline
      POSTGRES_USER: app
      POSTGRES_PASSWORD: app
    volumes:
      - db_data:/var/lib/postgresql/data

  rabbitmq:
    image: rabbitmq:3.13-management-alpine
    ports: ["15672:15672"]
    environment:
      RABBITMQ_DEFAULT_USER: app
      RABBITMQ_DEFAULT_PASS: app

volumes:
  db_data:
```

**Same image for `app` and `worker`** — different entrypoint. Worker gets Docker socket for next iteration's `DockerSocketExecutorAdapter`.

---

## Async Execution Flow

```
1. HTTP POST /pipelines/{id}/run  OR  bin/console pipeline:run {id} --env=staging
   → Controller dispatches TriggerPipelineCommand to Messenger queue
   → Returns 202 Accepted + pipelineRunId

2. TriggerPipelineHandler (worker)
   → Loads Pipeline via PipelineRepositoryPort
   → Creates PipelineRun aggregate
   → Persists via PipelineRunRepositoryPort
   → Calls readyJobs() → dispatches ExecuteJobCommand per ready job

3. ExecuteJobHandler (worker, per job)
   → Calls ExecutorPort::run(job, env) → JobResult
   → Calls jobRun->markAsSuccess() or markAsFailed()
   → Persists PipelineRun
   → If job type is APPROVAL → jobRun->markAsAwaitingApproval(), stops
   → Else calls readyJobs() again → dispatches next batch
   → If isComplete() → marks PipelineRun terminal

4. Approval (separate trigger)
   → HTTP POST /pipeline-runs/{runId}/jobs/{jobId}/approve
   → ApprovePipelineJobHandler marks job SUCCESS → resumes DAG
```

---

## State Machines

### JobRun
```
PENDING
  ├─(condition false)──────────────→ SKIPPED
  └─(needs satisfied + ready)──→ RUNNING
                                     ├─(type: APPROVAL)──→ AWAITING_APPROVAL
                                     │                          ├─(approved)──→ SUCCESS
                                     │                          └─(rejected)──→ FAILED
                                     ├─(executor success)──→ SUCCESS
                                     └─(executor failure)──→ FAILED
```

### PipelineRun
```
PENDING → RUNNING → SUCCESS | FAILED | AWAITING_APPROVAL
```

---

## Error Handling

| Zone | Failure | Handling |
|------|---------|----------|
| Input validation | Pipeline not found, invalid env | Exception → HTTP 404/422 or Console error |
| Job execution | Executor returns `JobResult::failure()` | Application calls `markAsFailed()`, pipeline stops |
| Infrastructure | DB/queue crash mid-execution | Messenger retries → dead letter queue |

Domain never throws for expected outcomes. Only throws `InvalidStatusTransitionException` for invariant violations.

---

## Testing Strategy

**Domain (unit)** — pure PHP, no framework:
- `PipelineRunTest`: DAG evaluation, condition checks, state transitions, `isComplete()`
- `JobRunTest`: valid/invalid state transitions

**Application (integration)** — `InMemoryPipelineRunRepository` + `FakeExecutorAdapter`:
- `TriggerPipelineHandlerTest`: full pipeline flow, correct job sequencing
- `ExecuteJobHandlerTest`: parallel job dispatch, approval gate behaviour

**HTTP (functional)** — `WebTestCase`, real DB:
- Contract tests only: correct status codes, response shape

---

## API Endpoints

```
POST   /pipelines/{id}/run                          Trigger pipeline run
GET    /pipeline-runs/{runId}                       Get run status + job statuses
POST   /pipeline-runs/{runId}/jobs/{jobId}/approve  Approve a waiting job
POST   /pipeline-runs/{runId}/jobs/{jobId}/reject   Reject a waiting job
```

---

## Console Commands

```bash
bin/console pipeline:run {pipelineId} --env=staging
bin/console pipeline:status {runId}
bin/console pipeline:approve {runId} {jobId}
```
