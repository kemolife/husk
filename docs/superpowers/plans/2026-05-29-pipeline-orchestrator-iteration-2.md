# Pipeline Orchestrator — Iteration 2 Feature Roadmap

> **Reference:** https://dev.to/matt_frank_usa/designing-a-code-deployment-system-github-actions-architecture-4f33
>
> **Goal:** Bring the pipeline orchestrator to GitHub Actions feature parity — real execution, webhook triggers, secrets, notifications, observability, security.
>
> **Current state:** Iteration 1 complete. DAG execution, approval gates, FakeExecutorAdapter, REST API + Console, RabbitMQ async. 29/29 tests passing.

---

## Build Order

```
Phase 1 (1→2→3)   Real execution + logs        — makes system actually useful
Phase 2 (4)        Webhook triggers             — connects to real repos
Phase 3 (6→7→8)   YAML completeness            — GitHub Actions parity
Phase 4 (11→12)   Observability                — production trust
Phase 5 (13)       Notifications                — team workflow
Phase 6 (14→15)   Security                     — before real deployment
Phase 7 (16→17)   Operational completeness
Advanced (5→9→10→18→19)
```

---

## Phase 1 — Real Execution

### Task 1: DockerSocketExecutorAdapter

**Files:**
- Create: `src/Infrastructure/Executor/DockerSocketExecutorAdapter.php`
- Create: `tests/Infrastructure/Executor/DockerSocketExecutorAdapterTest.php`

Talk to `/var/run/docker.sock` via raw HTTP over Unix socket. No external library needed.

Flow per job:
1. `POST /images/create?fromImage={job->image}` — pull image
2. `POST /containers/create` — create container with script as entrypoint, workspace volume mounted
3. `POST /containers/{id}/start` — start
4. `POST /containers/{id}/wait` — block until exit
5. `GET /containers/{id}/logs?stdout=1&stderr=1` — capture output
6. `DELETE /containers/{id}` — cleanup

Returns `JobResult::success($output)` or `JobResult::failure($output)` based on exit code.

Port binding in `config/services.yaml`:
```yaml
App\Application\Port\ExecutorPort:
    class: App\Infrastructure\Executor\DockerSocketExecutorAdapter
    arguments:
        $socketPath: '/var/run/docker.sock'
```

---

### Task 2: Job log persistence

**Files:**
- Modify: `src/Domain/PipelineRun/JobRun.php` — add `output`, `startedAt`, `finishedAt` fields
- Modify: `src/Application/Command/ExecuteJob/ExecuteJobHandler.php` — store output from `JobResult`
- Create: `src/Application/Query/GetJobLogs/GetJobLogsQuery.php`
- Create: `src/Application/Query/GetJobLogs/GetJobLogsHandler.php`
- Modify: `src/Infrastructure/Http/Controller/` — add `GET /pipeline-runs/{runId}/jobs/{jobId}/logs`

Domain changes to `JobRun`:
```php
public function recordExecution(string $output, \DateTimeImmutable $startedAt, \DateTimeImmutable $finishedAt): void
```

`ExecuteJobHandler` calls `$jobRun->recordExecution($result->output, $start, new \DateTimeImmutable())` after executor returns.

---

### Task 3: Shared workspace volume

**Files:**
- Modify: `src/Infrastructure/Executor/DockerSocketExecutorAdapter.php`
- Modify: `src/Application/Command/TriggerPipeline/TriggerPipelineHandler.php` — create volume on trigger
- Modify: `src/Application/Command/ExecuteJob/ExecuteJobHandler.php` — delete volume when run completes

Volume name: `pipeline-run-{runId}`.

`TriggerPipelineHandler` creates volume via `POST /volumes/create` before dispatching first jobs.
`ExecuteJobHandler` deletes volume via `DELETE /volumes/{name}` when `$run->isComplete()`.
All job containers mount volume at `/workspace`.

---

## Phase 2 — Triggers

### Task 4: GitHub webhook trigger

**Files:**
- Create: `src/Domain/Pipeline/TriggerContext.php` — value object: commitSha, branch, actor, repoUrl
- Modify: `src/Domain/PipelineRun/PipelineRun.php` — add nullable `TriggerContext`
- Create: `src/Infrastructure/Http/Controller/GithubWebhookController.php`
- Create: `src/Application/Command/TriggerPipeline/TriggerPipelineCommand.php` — add optional context fields

`GithubWebhookController`:
- Validates `X-Hub-Signature-256`: `hash_hmac('sha256', $rawBody, $secret)`
- Parses `X-GitHub-Event: push` or `pull_request`
- Extracts branch, commit SHA, actor from payload
- Looks up matching pipeline by repo/branch convention (e.g. `pipelines/{repo-name}.yaml`)
- Dispatches `TriggerPipelineCommand`

YAML: add `on:` block support:
```yaml
on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main]
```

New env var: `GITHUB_WEBHOOK_SECRET` in `.env`.

---

### Task 5: Scheduled triggers

**Files:**
- Create: `src/Infrastructure/Console/ScheduledPipelineCommand.php` (cron via Symfony Scheduler or supervisor)
- Modify: YAML parser — support `on: schedule: - cron: "0 * * * *"`
- Create: `src/Domain/Pipeline/Schedule.php` — value object wrapping cron expression

Use `symfony/scheduler` component or add external cron calling `pipeline:run` via `bin/console`.

---

## Phase 3 — YAML Feature Completeness

### Task 6: Job-level retry policy

**Files:**
- Modify: `src/Domain/Pipeline/Job.php` — add `RetryPolicy` value object (maxAttempts, delaySeconds)
- Create: `src/Domain/Pipeline/RetryPolicy.php`
- Modify: `src/Infrastructure/Persistence/Yaml/YamlFilePipelineRepository.php` — parse `retry:` key
- Modify: `src/Application/Command/ExecuteJob/ExecuteJobHandler.php` — track attempt, re-dispatch on failure

YAML format:
```yaml
jobs:
  deploy:
    script: ./deploy.sh
    retry:
      max: 3
      delay: 30
```

`JobRun` tracks `attemptNumber`. On failure, if `attemptNumber < maxAttempts`, dispatch new `ExecuteJobCommand` with incremented attempt.

---

### Task 7: Job timeout

**Files:**
- Modify: `src/Domain/Pipeline/Job.php` — add `?int $timeoutSeconds`
- Modify: `src/Infrastructure/Persistence/Yaml/YamlFilePipelineRepository.php` — parse `timeout:` key
- Modify: `src/Infrastructure/Executor/DockerSocketExecutorAdapter.php` — pass `StopTimeout` to container, poll with deadline

YAML format:
```yaml
jobs:
  slow-test:
    timeout: 300
    script: php bin/phpunit
```

Executor: after `wait`, check if elapsed > timeout and kill container. `JobRun::markAsFailed('timeout')`.

---

### Task 8: Secrets injection

**Files:**
- Create: `src/Application/Port/SecretRepositoryPort.php` — `get(string $name): string`
- Create: `src/Infrastructure/Secrets/EnvSecretAdapter.php` — reads from `$_ENV`
- Modify: `src/Domain/Pipeline/Job.php` — add `array $secretNames`
- Modify: `src/Infrastructure/Executor/DockerSocketExecutorAdapter.php` — inject resolved secrets as container `Env`
- Modify: YAML parser — parse `secrets:` block per job

YAML format:
```yaml
jobs:
  deploy:
    script: ./deploy.sh
    secrets:
      - DB_PASSWORD
      - DEPLOY_KEY
    env:
      APP_ENV: production
```

Secrets resolved at execution time, never logged, injected only into container env.

---

### Task 9: `continue-on-error` flag

**Files:**
- Modify: `src/Domain/Pipeline/Job.php` — add `bool $continueOnError = false`
- Modify: YAML parser — parse `continue_on_error: true`
- Modify: `src/Application/Command/ExecuteJob/ExecuteJobHandler.php` — if job fails + flag set, mark SKIPPED and continue DAG

---

### Task 10: Matrix builds

**Files:**
- Modify: `src/Domain/Pipeline/Job.php` — add `?MatrixStrategy $matrix`
- Create: `src/Domain/Pipeline/MatrixStrategy.php` — holds axis definitions
- Modify: `src/Application/Command/TriggerPipeline/TriggerPipelineHandler.php` — expand matrix jobs into N `JobRun` instances
- Modify: YAML parser — parse `matrix:` block

YAML format:
```yaml
jobs:
  test:
    matrix:
      php: [8.2, 8.3, 8.4]
    image: php:${{ matrix.php }}-cli
    script: php bin/phpunit
```

Matrix expansion creates JobRuns: `test (8.2)`, `test (8.3)`, `test (8.4)` — all dispatched in parallel.

---

## Phase 4 — Observability

### Task 11: Pipeline event log

**Files:**
- Create: `src/Domain/PipelineRun/PipelineEvent.php` — entity: runId, type, payload, occurredAt
- Create: `src/Domain/PipelineRun/PipelineEventType.php` — enum: TRIGGERED, JOB_STARTED, JOB_FINISHED, JOB_FAILED, APPROVED, COMPLETED
- Create: `src/Infrastructure/Persistence/Doctrine/DoctrinePipelineEventRepository.php`
- Modify: handlers — emit events on state transitions
- Add endpoint: `GET /pipeline-runs/{runId}/events`

---

### Task 12: Log streaming

**Files:**
- Create: `src/Infrastructure/Http/Controller/StreamJobLogsController.php`
- Modify: `DockerSocketExecutorAdapter` — write log chunks to Redis pub/sub (key: `job-logs:{jobRunId}`) while container running
- Controller streams via SSE: `Content-Type: text/event-stream`

Requires `redis` PHP extension + Redis service in `docker-compose.yml`.

---

## Phase 5 — Notifications

### Task 13: Notification port + adapters

**Files:**
- Create: `src/Application/Port/NotificationPort.php` — `notify(PipelineRun $run, string $event): void`
- Create: `src/Infrastructure/Notification/SlackNotificationAdapter.php` — POST to Slack webhook URL
- Create: `src/Infrastructure/Notification/WebhookNotificationAdapter.php` — POST to configured URL
- Modify: `ExecuteJobHandler` + `ApprovePipelineJobHandler` — call notification port on run complete/fail
- Modify: YAML parser — parse `notifications:` block

YAML format:
```yaml
notifications:
  slack:
    channel: "#deployments"
    on: [success, failure]
  webhook:
    url: https://example.com/pipeline-events
    on: [success]
```

---

## Phase 6 — Security

### Task 14: API key authentication

**Files:**
- Create: `src/Domain/Auth/ApiKey.php` — entity: id, hashedKey, name, scopes[], createdAt
- Create: `src/Infrastructure/Persistence/Doctrine/DoctrineApiKeyRepository.php`
- Create: `src/Infrastructure/Http/Middleware/ApiKeyAuthenticator.php` — Symfony Guard authenticator
- Modify: `config/packages/security.yaml` — add firewall for `/pipelines` and `/pipeline-runs` paths
- Add Console command: `api-key:create` — generates + stores hashed key, outputs plain text once

All pipeline API endpoints require `X-Api-Key: {key}` header. Webhook endpoint uses its own `X-Hub-Signature-256`.

---

### Task 15: Approval audit log

**Files:**
- Modify: `src/Application/Command/ApprovePipelineJob/ApprovePipelineJobCommand.php` — add `actorId: string`
- Modify: `src/Application/Command/ApprovePipelineJob/ApprovePipelineJobHandler.php` — store approval record
- Create: `src/Domain/PipelineRun/ApprovalRecord.php` — entity: jobRunId, actorId, decision, ip, approvedAt

---

## Phase 7 — Operational Completeness

### Task 16: Doctrine migrations

**Files:**
- Run: `composer require doctrine/doctrine-migrations-bundle`
- Create initial migration from current schema
- Replace `doctrine:schema:create` with `doctrine:migrations:migrate` in startup

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate --no-interaction
```

Add to `docker-compose.yml` worker command or separate `app` entrypoint script.

---

### Task 17: List and filter endpoints

**Files:**
- Create: `src/Application/Query/ListPipelineRuns/ListPipelineRunsQuery.php` — filters: pipelineId, status, page, limit
- Create: `src/Application/Query/ListPipelineRuns/ListPipelineRunsHandler.php`
- Create: `src/Application/Query/ListPipelines/ListPipelinesQuery.php`
- Create: `src/Application/Query/ListPipelines/ListPipelinesHandler.php`
- Add endpoints: `GET /pipelines`, `GET /pipeline-runs?pipeline_id=X&status=Y&page=1`

---

### Task 18: Rollback support

**Files:**
- Modify: `src/Infrastructure/Persistence/Doctrine/DoctrinePipelineRunRepository.php` — add `findLastSuccess(string $pipelineId, Environment $env): ?PipelineRun`
- Create: `src/Application/Command/RollbackPipeline/RollbackPipelineCommand.php`
- Create: `src/Application/Command/RollbackPipeline/RollbackPipelineHandler.php`
- Add endpoint: `POST /pipelines/{id}/rollback` with `{"environment":"production"}`

Rollback = trigger new run identical to last SUCCESS run (same pipeline, same environment).

---

### Task 19: Health check endpoint

**Files:**
- Create: `src/Infrastructure/Http/Controller/HealthController.php`

```php
#[Route('/health', methods: ['GET'])]
public function __invoke(): JsonResponse
{
    // check DB: $this->em->getConnection()->executeQuery('SELECT 1')
    // check RabbitMQ: try AMQP connection
    return new JsonResponse(['status' => 'ok', 'db' => 'ok', 'queue' => 'ok']);
}
```

Returns 503 if any dependency down.
