# Deployment Pipeline Orchestrator Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a Symfony 8 hexagonal pipeline orchestrator with DAG job execution, approval gates, async RabbitMQ-based execution, REST API and Console entry points, running in Docker.

**Architecture:** Hexagonal ports & adapters. `PipelineRun` aggregate owns DAG evaluation and state machine in pure PHP. Symfony Messenger dispatches async job commands consumed by a dedicated worker container. `FakeExecutorAdapter` simulates job execution (SSH/Docker deferred to next iteration).

**Tech Stack:** PHP 8.4, Symfony 8.0, Doctrine ORM 3.x (attributes on domain), Symfony Messenger + AMQP, RabbitMQ 3.13, PostgreSQL 16, PHPUnit 11, symfony/uid, symfony/yaml

---

### Task 1: Docker setup

**Files:**
- Create: `Dockerfile`
- Create: `docker-compose.yml`
- Create: `.env`

- [ ] **Step 1: Create Dockerfile**

```dockerfile
FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
        postgresql-dev \
        rabbitmq-c-dev \
        $PHPIZE_DEPS \
    && docker-php-ext-install pdo pdo_pgsql sockets opcache \
    && pecl install amqp \
    && docker-php-ext-enable amqp \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/app

COPY composer.json composer.lock* ./
RUN composer install --no-interaction --prefer-dist

COPY . .

RUN chown -R www-data:www-data var/

CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
```

- [ ] **Step 2: Create docker-compose.yml**

```yaml
services:
  app:
    build: .
    ports:
      - "8080:8080"
    environment:
      APP_ENV: dev
      APP_SECRET: devsecret
      DATABASE_URL: postgresql://app:app@db:5432/pipeline
      MESSENGER_TRANSPORT_DSN: amqp://app:app@rabbitmq:5672/%2f/messages
    volumes:
      - .:/var/www/app
    depends_on:
      db:
        condition: service_healthy
      rabbitmq:
        condition: service_healthy

  worker:
    build: .
    command: php bin/console messenger:consume async --time-limit=3600 -vv
    environment:
      APP_ENV: dev
      APP_SECRET: devsecret
      DATABASE_URL: postgresql://app:app@db:5432/pipeline
      MESSENGER_TRANSPORT_DSN: amqp://app:app@rabbitmq:5672/%2f/messages
    volumes:
      - .:/var/www/app
      - /var/run/docker.sock:/var/run/docker.sock
    depends_on:
      db:
        condition: service_healthy
      rabbitmq:
        condition: service_healthy

  db:
    image: postgres:16-alpine
    environment:
      POSTGRES_DB: pipeline
      POSTGRES_USER: app
      POSTGRES_PASSWORD: app
    volumes:
      - db_data:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U app -d pipeline"]
      interval: 5s
      timeout: 5s
      retries: 5

  rabbitmq:
    image: rabbitmq:3.13-management-alpine
    ports:
      - "15672:15672"
    environment:
      RABBITMQ_DEFAULT_USER: app
      RABBITMQ_DEFAULT_PASS: app
    healthcheck:
      test: ["CMD", "rabbitmq-diagnostics", "ping"]
      interval: 10s
      timeout: 5s
      retries: 5

volumes:
  db_data:
```

- [ ] **Step 3: Update .env**

```
APP_ENV=dev
APP_SECRET=devsecret
DATABASE_URL=postgresql://app:app@localhost:5432/pipeline
MESSENGER_TRANSPORT_DSN=amqp://app:app@localhost:5672/%2f/messages
```

- [ ] **Step 4: Start containers**

```bash
docker compose up -d
```

Expected: all 4 containers running, RabbitMQ UI at http://localhost:15672 (app/app)

- [ ] **Step 5: Commit**

```bash
git init && git add Dockerfile docker-compose.yml .env .gitignore
git commit -m "feat: add Docker setup with PostgreSQL and RabbitMQ"
```

---

### Task 2: Install PHP dependencies

**Files:**
- Modify: `composer.json`
- Create: `phpunit.xml.dist`
- Create: `tests/` directory structure

- [ ] **Step 1: Install runtime dependencies**

```bash
composer require \
  doctrine/doctrine-bundle \
  doctrine/orm \
  symfony/messenger \
  symfony/amqp-messenger \
  symfony/uid \
  symfony/serializer \
  symfony/property-access \
  symfony/validator
```

- [ ] **Step 2: Install dev dependencies**

```bash
composer require --dev \
  phpunit/phpunit \
  symfony/phpunit-bridge \
  symfony/browser-kit \
  symfony/http-client
```

- [ ] **Step 3: Create phpunit.xml.dist**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true">
    <testsuites>
        <testsuite name="unit">
            <directory>tests/Domain</directory>
        </testsuite>
        <testsuite name="integration">
            <directory>tests/Application</directory>
        </testsuite>
        <testsuite name="functional">
            <directory>tests/Infrastructure</directory>
        </testsuite>
    </testsuites>
    <php>
        <ini name="error_reporting" value="-1"/>
        <server name="APP_ENV" value="test" force="true"/>
        <server name="SYMFONY_DEPRECATIONS_HELPER" value="999999"/>
        <server name="DATABASE_URL" value="postgresql://app:app@localhost:5432/pipeline_test" force="true"/>
        <server name="MESSENGER_TRANSPORT_DSN" value="in-memory://" force="true"/>
    </php>
</phpunit>
```

- [ ] **Step 4: Create test directories**

```bash
mkdir -p tests/Domain/Shared tests/Domain/Pipeline tests/Domain/PipelineRun tests/Application tests/Infrastructure/Http tests/Infrastructure/Persistence
```

- [ ] **Step 5: Commit**

```bash
git add composer.json composer.lock phpunit.xml.dist tests/
git commit -m "feat: install Doctrine, Messenger, PHPUnit dependencies"
```

---

### Task 3: Configure Doctrine and Messenger

**Files:**
- Create/Modify: `config/packages/doctrine.yaml`
- Create: `config/packages/messenger.yaml`
- Create: `config/packages/doctrine.yaml`

- [ ] **Step 1: Configure Doctrine**

Replace `config/packages/doctrine.yaml`:
```yaml
doctrine:
    dbal:
        url: '%env(resolve:DATABASE_URL)%'
        profiling_collect_backtrace: '%kernel.debug%'
    orm:
        auto_generate_proxy_classes: true
        enable_lazy_ghost_objects: true
        naming_strategy: doctrine.orm.naming_strategy.underscore_number_aware
        auto_mapping: true
        mappings:
            App:
                is_bundle: false
                dir: '%kernel.project_dir%/src/Domain'
                prefix: 'App\Domain'
                alias: App
```

- [ ] **Step 2: Configure Messenger**

Create `config/packages/messenger.yaml`:
```yaml
framework:
    messenger:
        failure_transport: failed

        transports:
            async:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                retry_strategy:
                    max_retries: 3
                    delay: 1000
                    multiplier: 2
            failed:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                options:
                    queues:
                        failed: ~

        routing:
            App\Application\Command\TriggerPipeline\TriggerPipelineCommand: async
            App\Application\Command\ExecuteJob\ExecuteJobCommand: async
            App\Application\Command\ApprovePipelineJob\ApprovePipelineJobCommand: async
```

- [ ] **Step 3: Add pipelines directory**

```bash
mkdir -p pipelines
```

- [ ] **Step 4: Commit**

```bash
git add config/ pipelines/
git commit -m "feat: configure Doctrine ORM and Symfony Messenger with AMQP"
```

---

### Task 4: Domain shared types

**Files:**
- Create: `src/Domain/Shared/Environment.php`
- Create: `src/Domain/Shared/InvalidStatusTransitionException.php`
- Create: `tests/Domain/Shared/EnvironmentTest.php`

- [ ] **Step 1: Write failing test**

Create `tests/Domain/Shared/EnvironmentTest.php`:
```php
<?php

namespace App\Tests\Domain\Shared;

use App\Domain\Shared\Environment;
use PHPUnit\Framework\TestCase;

class EnvironmentTest extends TestCase
{
    public function test_it_creates_from_string(): void
    {
        $env = Environment::fromString('staging');
        $this->assertSame(Environment::STAGING, $env);
    }

    public function test_it_throws_for_invalid_value(): void
    {
        $this->expectException(\ValueError::class);
        Environment::fromString('invalid');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
./vendor/bin/phpunit tests/Domain/Shared/EnvironmentTest.php
```

Expected: FAIL — class not found

- [ ] **Step 3: Create Environment**

Create `src/Domain/Shared/Environment.php`:
```php
<?php

namespace App\Domain\Shared;

enum Environment: string
{
    case DEVELOPMENT = 'development';
    case STAGING = 'staging';
    case PRODUCTION = 'production';

    public static function fromString(string $value): self
    {
        return self::from(strtolower($value));
    }
}
```

- [ ] **Step 4: Create InvalidStatusTransitionException**

Create `src/Domain/Shared/InvalidStatusTransitionException.php`:
```php
<?php

namespace App\Domain\Shared;

class InvalidStatusTransitionException extends \DomainException
{
    public static function for(string $entity, string $from, string $to): self
    {
        return new self("Invalid transition for {$entity}: {$from} → {$to}");
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

```bash
./vendor/bin/phpunit tests/Domain/Shared/EnvironmentTest.php
```

Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add src/Domain/Shared/ tests/Domain/Shared/
git commit -m "feat: add domain shared types (Environment, InvalidStatusTransitionException)"
```

---

### Task 5: Domain Pipeline objects

**Files:**
- Create: `src/Domain/Pipeline/PipelineId.php`
- Create: `src/Domain/Pipeline/JobType.php`
- Create: `src/Domain/Pipeline/Job.php`
- Create: `src/Domain/Pipeline/Pipeline.php`
- Create: `src/Domain/Pipeline/PipelineNotFoundException.php`
- Create: `tests/Domain/Pipeline/PipelineTest.php`

- [ ] **Step 1: Write failing test**

Create `tests/Domain/Pipeline/PipelineTest.php`:
```php
<?php

namespace App\Tests\Domain\Pipeline;

use App\Domain\Pipeline\Job;
use App\Domain\Pipeline\JobType;
use App\Domain\Pipeline\Pipeline;
use App\Domain\Pipeline\PipelineId;
use PHPUnit\Framework\TestCase;

class PipelineTest extends TestCase
{
    public function test_it_finds_job_by_id(): void
    {
        $job = new Job('build', JobType::SCRIPT, 'php:8.4-cli', 'composer install', [], null);
        $pipeline = new Pipeline(new PipelineId('my-pipeline'), 'My Pipeline', [$job]);

        $found = $pipeline->job('build');

        $this->assertSame($job, $found);
    }

    public function test_it_throws_when_job_not_found(): void
    {
        $pipeline = new Pipeline(new PipelineId('p1'), 'P1', []);

        $this->expectException(\InvalidArgumentException::class);
        $pipeline->job('nonexistent');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
./vendor/bin/phpunit tests/Domain/Pipeline/PipelineTest.php
```

Expected: FAIL — class not found

- [ ] **Step 3: Create PipelineId**

Create `src/Domain/Pipeline/PipelineId.php`:
```php
<?php

namespace App\Domain\Pipeline;

final readonly class PipelineId
{
    public function __construct(public readonly string $value)
    {
        if (empty($value)) {
            throw new \InvalidArgumentException('PipelineId cannot be empty');
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
```

- [ ] **Step 4: Create JobType**

Create `src/Domain/Pipeline/JobType.php`:
```php
<?php

namespace App\Domain\Pipeline;

enum JobType: string
{
    case SCRIPT = 'script';
    case APPROVAL = 'approval';
}
```

- [ ] **Step 5: Create Job**

Create `src/Domain/Pipeline/Job.php`:
```php
<?php

namespace App\Domain\Pipeline;

final readonly class Job
{
    /** @param string[] $needs */
    public function __construct(
        public readonly string $id,
        public readonly JobType $type,
        public readonly ?string $image,
        public readonly ?string $script,
        public readonly array $needs,
        public readonly ?string $condition,
    ) {}

    public function isApproval(): bool
    {
        return $this->type === JobType::APPROVAL;
    }
}
```

- [ ] **Step 6: Create Pipeline**

Create `src/Domain/Pipeline/Pipeline.php`:
```php
<?php

namespace App\Domain\Pipeline;

final class Pipeline
{
    /** @param Job[] $jobs */
    public function __construct(
        private readonly PipelineId $id,
        private readonly string $name,
        private readonly array $jobs,
    ) {}

    public function id(): PipelineId { return $this->id; }
    public function name(): string { return $this->name; }

    /** @return Job[] */
    public function jobs(): array { return $this->jobs; }

    public function job(string $jobId): Job
    {
        foreach ($this->jobs as $job) {
            if ($job->id === $jobId) {
                return $job;
            }
        }
        throw new \InvalidArgumentException("Job '{$jobId}' not found in pipeline '{$this->id}'");
    }
}
```

- [ ] **Step 7: Create PipelineNotFoundException**

Create `src/Domain/Pipeline/PipelineNotFoundException.php`:
```php
<?php

namespace App\Domain\Pipeline;

class PipelineNotFoundException extends \DomainException
{
    public static function forId(string $id): self
    {
        return new self("Pipeline '{$id}' not found");
    }
}
```

- [ ] **Step 8: Run tests**

```bash
./vendor/bin/phpunit tests/Domain/Pipeline/PipelineTest.php
```

Expected: PASS

- [ ] **Step 9: Commit**

```bash
git add src/Domain/Pipeline/ tests/Domain/Pipeline/
git commit -m "feat: add Pipeline domain aggregate and Job value object"
```

---

### Task 6: Domain PipelineRun types

**Files:**
- Create: `src/Domain/PipelineRun/PipelineRunId.php`
- Create: `src/Domain/PipelineRun/JobRunStatus.php`
- Create: `src/Domain/PipelineRun/PipelineRunStatus.php`
- Create: `src/Domain/PipelineRun/PipelineRunNotFoundException.php`

- [ ] **Step 1: Create PipelineRunId**

Create `src/Domain/PipelineRun/PipelineRunId.php`:
```php
<?php

namespace App\Domain\PipelineRun;

use Symfony\Component\Uid\Uuid;

final readonly class PipelineRunId
{
    public function __construct(public readonly string $value)
    {
        if (empty($value)) {
            throw new \InvalidArgumentException('PipelineRunId cannot be empty');
        }
    }

    public static function generate(): self
    {
        return new self(Uuid::v4()->toRfc4122());
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
```

- [ ] **Step 2: Create JobRunStatus**

Create `src/Domain/PipelineRun/JobRunStatus.php`:
```php
<?php

namespace App\Domain\PipelineRun;

enum JobRunStatus: string
{
    case PENDING = 'pending';
    case RUNNING = 'running';
    case SUCCESS = 'success';
    case FAILED = 'failed';
    case AWAITING_APPROVAL = 'awaiting_approval';
    case SKIPPED = 'skipped';

    public function isTerminal(): bool
    {
        return in_array($this, [self::SUCCESS, self::FAILED, self::SKIPPED], true);
    }
}
```

- [ ] **Step 3: Create PipelineRunStatus**

Create `src/Domain/PipelineRun/PipelineRunStatus.php`:
```php
<?php

namespace App\Domain\PipelineRun;

enum PipelineRunStatus: string
{
    case PENDING = 'pending';
    case RUNNING = 'running';
    case SUCCESS = 'success';
    case FAILED = 'failed';
    case AWAITING_APPROVAL = 'awaiting_approval';

    public function isTerminal(): bool
    {
        return in_array($this, [self::SUCCESS, self::FAILED], true);
    }
}
```

- [ ] **Step 4: Create PipelineRunNotFoundException**

Create `src/Domain/PipelineRun/PipelineRunNotFoundException.php`:
```php
<?php

namespace App\Domain\PipelineRun;

class PipelineRunNotFoundException extends \DomainException
{
    public static function forId(string $id): self
    {
        return new self("PipelineRun '{$id}' not found");
    }
}
```

- [ ] **Step 5: Commit**

```bash
git add src/Domain/PipelineRun/
git commit -m "feat: add PipelineRun domain types (enums, value objects)"
```

---

### Task 7: JobRun entity with state machine

**Files:**
- Create: `src/Domain/PipelineRun/JobRun.php`
- Create: `tests/Domain/PipelineRun/JobRunTest.php`

- [ ] **Step 1: Write failing tests**

Create `tests/Domain/PipelineRun/JobRunTest.php`:
```php
<?php

namespace App\Tests\Domain\PipelineRun;

use App\Domain\Pipeline\JobType;
use App\Domain\PipelineRun\JobRun;
use App\Domain\PipelineRun\JobRunStatus;
use App\Domain\Shared\InvalidStatusTransitionException;
use PHPUnit\Framework\TestCase;

class JobRunTest extends TestCase
{
    private function makeJobRun(JobType $type = JobType::SCRIPT): JobRun
    {
        return new JobRun('run-id-1', 'build', $type);
    }

    public function test_starts_as_pending(): void
    {
        $this->assertSame(JobRunStatus::PENDING, $this->makeJobRun()->status());
    }

    public function test_pending_transitions_to_running(): void
    {
        $run = $this->makeJobRun();
        $run->markAsRunning();
        $this->assertSame(JobRunStatus::RUNNING, $run->status());
    }

    public function test_running_transitions_to_success(): void
    {
        $run = $this->makeJobRun();
        $run->markAsRunning();
        $run->markAsSuccess();
        $this->assertSame(JobRunStatus::SUCCESS, $run->status());
    }

    public function test_running_transitions_to_failed(): void
    {
        $run = $this->makeJobRun();
        $run->markAsRunning();
        $run->markAsFailed();
        $this->assertSame(JobRunStatus::FAILED, $run->status());
    }

    public function test_running_approval_job_transitions_to_awaiting_approval(): void
    {
        $run = $this->makeJobRun(JobType::APPROVAL);
        $run->markAsRunning();
        $run->markAsAwaitingApproval();
        $this->assertSame(JobRunStatus::AWAITING_APPROVAL, $run->status());
    }

    public function test_awaiting_approval_transitions_to_success(): void
    {
        $run = $this->makeJobRun(JobType::APPROVAL);
        $run->markAsRunning();
        $run->markAsAwaitingApproval();
        $run->markAsSuccess();
        $this->assertSame(JobRunStatus::SUCCESS, $run->status());
    }

    public function test_pending_transitions_to_skipped(): void
    {
        $run = $this->makeJobRun();
        $run->markAsSkipped();
        $this->assertSame(JobRunStatus::SKIPPED, $run->status());
    }

    public function test_throws_on_invalid_transition_success_to_running(): void
    {
        $run = $this->makeJobRun();
        $run->markAsRunning();
        $run->markAsSuccess();

        $this->expectException(InvalidStatusTransitionException::class);
        $run->markAsRunning();
    }

    public function test_throws_on_invalid_transition_running_to_skipped(): void
    {
        $run = $this->makeJobRun();
        $run->markAsRunning();

        $this->expectException(InvalidStatusTransitionException::class);
        $run->markAsSkipped();
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
./vendor/bin/phpunit tests/Domain/PipelineRun/JobRunTest.php
```

Expected: FAIL — class not found

- [ ] **Step 3: Create JobRun**

Create `src/Domain/PipelineRun/JobRun.php`:
```php
<?php

namespace App\Domain\PipelineRun;

use App\Domain\Pipeline\JobType;
use App\Domain\Shared\InvalidStatusTransitionException;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'job_runs')]
class JobRun
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 100)]
    private string $jobId;

    #[ORM\Column(type: 'string', enumType: JobRunStatus::class)]
    private JobRunStatus $status;

    #[ORM\Column(type: 'string', enumType: JobType::class)]
    private JobType $type;

    #[ORM\ManyToOne(targetEntity: PipelineRun::class, inversedBy: 'jobRuns')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private PipelineRun $pipelineRun;

    public function __construct(string $id, string $jobId, JobType $type)
    {
        $this->id = $id;
        $this->jobId = $jobId;
        $this->type = $type;
        $this->status = JobRunStatus::PENDING;
    }

    public function id(): string { return $this->id; }
    public function jobId(): string { return $this->jobId; }
    public function status(): JobRunStatus { return $this->status; }
    public function isPending(): bool { return $this->status === JobRunStatus::PENDING; }
    public function isApproval(): bool { return $this->type === JobType::APPROVAL; }

    public function setPipelineRun(PipelineRun $run): void
    {
        $this->pipelineRun = $run;
    }

    public function markAsRunning(): void
    {
        if ($this->status !== JobRunStatus::PENDING) {
            throw InvalidStatusTransitionException::for('JobRun', $this->status->value, 'running');
        }
        $this->status = JobRunStatus::RUNNING;
    }

    public function markAsSuccess(): void
    {
        if (!in_array($this->status, [JobRunStatus::RUNNING, JobRunStatus::AWAITING_APPROVAL], true)) {
            throw InvalidStatusTransitionException::for('JobRun', $this->status->value, 'success');
        }
        $this->status = JobRunStatus::SUCCESS;
    }

    public function markAsFailed(): void
    {
        if (!in_array($this->status, [JobRunStatus::RUNNING, JobRunStatus::AWAITING_APPROVAL], true)) {
            throw InvalidStatusTransitionException::for('JobRun', $this->status->value, 'failed');
        }
        $this->status = JobRunStatus::FAILED;
    }

    public function markAsAwaitingApproval(): void
    {
        if ($this->status !== JobRunStatus::RUNNING) {
            throw InvalidStatusTransitionException::for('JobRun', $this->status->value, 'awaiting_approval');
        }
        $this->status = JobRunStatus::AWAITING_APPROVAL;
    }

    public function markAsSkipped(): void
    {
        if ($this->status !== JobRunStatus::PENDING) {
            throw InvalidStatusTransitionException::for('JobRun', $this->status->value, 'skipped');
        }
        $this->status = JobRunStatus::SKIPPED;
    }
}
```

- [ ] **Step 4: Run tests**

```bash
./vendor/bin/phpunit tests/Domain/PipelineRun/JobRunTest.php
```

Expected: PASS (9 tests)

- [ ] **Step 5: Commit**

```bash
git add src/Domain/PipelineRun/JobRun.php tests/Domain/PipelineRun/JobRunTest.php
git commit -m "feat: add JobRun entity with state machine"
```

---

### Task 8: PipelineRun aggregate with DAG logic

**Files:**
- Create: `src/Domain/PipelineRun/PipelineRun.php`
- Create: `tests/Domain/PipelineRun/PipelineRunTest.php`

- [ ] **Step 1: Write failing tests**

Create `tests/Domain/PipelineRun/PipelineRunTest.php`:
```php
<?php

namespace App\Tests\Domain\PipelineRun;

use App\Domain\Pipeline\Job;
use App\Domain\Pipeline\JobType;
use App\Domain\Pipeline\Pipeline;
use App\Domain\Pipeline\PipelineId;
use App\Domain\PipelineRun\JobRunStatus;
use App\Domain\PipelineRun\PipelineRun;
use App\Domain\PipelineRun\PipelineRunId;
use App\Domain\PipelineRun\PipelineRunStatus;
use App\Domain\Shared\Environment;
use App\Domain\Shared\InvalidStatusTransitionException;
use PHPUnit\Framework\TestCase;

class PipelineRunTest extends TestCase
{
    private function makePipeline(array $jobs): Pipeline
    {
        return new Pipeline(new PipelineId('p1'), 'P1', $jobs);
    }

    private function makeJob(string $id, array $needs = [], ?string $condition = null, JobType $type = JobType::SCRIPT): Job
    {
        return new Job($id, $type, 'php:8.4-cli', 'echo done', $needs, $condition);
    }

    public function test_starts_as_pending(): void
    {
        $pipeline = $this->makePipeline([$this->makeJob('build')]);
        $run = new PipelineRun(PipelineRunId::generate(), $pipeline, Environment::STAGING);

        $this->assertSame(PipelineRunStatus::PENDING, $run->status());
    }

    public function test_ready_jobs_returns_jobs_with_no_needs(): void
    {
        $pipeline = $this->makePipeline([
            $this->makeJob('build'),
            $this->makeJob('lint'),
        ]);
        $run = new PipelineRun(PipelineRunId::generate(), $pipeline, Environment::STAGING);

        $ready = $run->readyJobs($pipeline);

        $this->assertCount(2, $ready);
    }

    public function test_ready_jobs_respects_needs(): void
    {
        $pipeline = $this->makePipeline([
            $this->makeJob('build'),
            $this->makeJob('test', ['build']),
        ]);
        $run = new PipelineRun(PipelineRunId::generate(), $pipeline, Environment::STAGING);

        $ready = $run->readyJobs($pipeline);

        $this->assertCount(1, $ready);
        $this->assertSame('build', $ready[0]->jobId());
    }

    public function test_ready_jobs_unlocks_after_dependency_succeeds(): void
    {
        $pipeline = $this->makePipeline([
            $this->makeJob('build'),
            $this->makeJob('test', ['build']),
        ]);
        $run = new PipelineRun(PipelineRunId::generate(), $pipeline, Environment::STAGING);
        $run->markAsRunning();

        $buildRun = $run->jobRunByJobId('build');
        $buildRun->markAsRunning();
        $buildRun->markAsSuccess();

        $ready = $run->readyJobs($pipeline);

        $this->assertCount(1, $ready);
        $this->assertSame('test', $ready[0]->jobId());
    }

    public function test_ready_jobs_skips_jobs_failing_condition(): void
    {
        $pipeline = $this->makePipeline([
            $this->makeJob('deploy-prod', [], 'environment == production'),
        ]);
        $run = new PipelineRun(PipelineRunId::generate(), $pipeline, Environment::STAGING);

        $ready = $run->readyJobs($pipeline);

        $this->assertCount(0, $ready);
        $this->assertSame(JobRunStatus::SKIPPED, $run->jobRunByJobId('deploy-prod')->status());
    }

    public function test_is_complete_when_all_terminal(): void
    {
        $pipeline = $this->makePipeline([$this->makeJob('build')]);
        $run = new PipelineRun(PipelineRunId::generate(), $pipeline, Environment::STAGING);
        $run->markAsRunning();
        $run->jobRunByJobId('build')->markAsRunning();
        $run->jobRunByJobId('build')->markAsSuccess();

        $this->assertTrue($run->isComplete());
    }

    public function test_is_not_complete_when_pending_jobs_remain(): void
    {
        $pipeline = $this->makePipeline([
            $this->makeJob('build'),
            $this->makeJob('test', ['build']),
        ]);
        $run = new PipelineRun(PipelineRunId::generate(), $pipeline, Environment::STAGING);

        $this->assertFalse($run->isComplete());
    }

    public function test_throws_on_invalid_status_transition(): void
    {
        $pipeline = $this->makePipeline([$this->makeJob('build')]);
        $run = new PipelineRun(PipelineRunId::generate(), $pipeline, Environment::STAGING);
        $run->markAsRunning();

        $this->expectException(InvalidStatusTransitionException::class);
        $run->markAsRunning();
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
./vendor/bin/phpunit tests/Domain/PipelineRun/PipelineRunTest.php
```

Expected: FAIL — class not found

- [ ] **Step 3: Create PipelineRun**

Create `src/Domain/PipelineRun/PipelineRun.php`:
```php
<?php

namespace App\Domain\PipelineRun;

use App\Domain\Pipeline\Pipeline;
use App\Domain\Shared\Environment;
use App\Domain\Shared\InvalidStatusTransitionException;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'pipeline_runs')]
class PipelineRun
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 100)]
    private string $pipelineId;

    #[ORM\Column(type: 'string', enumType: PipelineRunStatus::class)]
    private PipelineRunStatus $status;

    #[ORM\Column(type: 'string', enumType: Environment::class)]
    private Environment $environment;

    #[ORM\OneToMany(targetEntity: JobRun::class, mappedBy: 'pipelineRun', cascade: ['persist', 'remove'])]
    private Collection $jobRuns;

    public function __construct(PipelineRunId $id, Pipeline $pipeline, Environment $environment)
    {
        $this->id = $id->value;
        $this->pipelineId = $pipeline->id()->value;
        $this->environment = $environment;
        $this->status = PipelineRunStatus::PENDING;
        $this->jobRuns = new ArrayCollection();

        foreach ($pipeline->jobs() as $job) {
            $jobRun = new JobRun(Uuid::v4()->toRfc4122(), $job->id, $job->type);
            $jobRun->setPipelineRun($this);
            $this->jobRuns->add($jobRun);
        }
    }

    public function id(): PipelineRunId { return new PipelineRunId($this->id); }
    public function pipelineId(): string { return $this->pipelineId; }
    public function status(): PipelineRunStatus { return $this->status; }
    public function environment(): Environment { return $this->environment; }

    /** @return JobRun[] */
    public function jobRuns(): array { return $this->jobRuns->toArray(); }

    public function jobRunByJobId(string $jobId): JobRun
    {
        foreach ($this->jobRuns as $jr) {
            if ($jr->jobId() === $jobId) {
                return $jr;
            }
        }
        throw new \InvalidArgumentException("JobRun for job '{$jobId}' not found");
    }

    /** @return JobRun[] */
    public function readyJobs(Pipeline $pipeline): array
    {
        $ready = [];

        foreach ($this->jobRuns as $jr) {
            if (!$jr->isPending()) {
                continue;
            }

            $job = $pipeline->job($jr->jobId());

            if (!$this->conditionMatches($job->condition)) {
                $jr->markAsSkipped();
                continue;
            }

            if ($this->allNeedsSatisfied($job->needs)) {
                $ready[] = $jr;
            }
        }

        return $ready;
    }

    public function isComplete(): bool
    {
        foreach ($this->jobRuns as $jr) {
            if (!$jr->status()->isTerminal()) {
                return false;
            }
        }
        return true;
    }

    public function markAsRunning(): void
    {
        if (!in_array($this->status, [PipelineRunStatus::PENDING, PipelineRunStatus::AWAITING_APPROVAL], true)) {
            throw InvalidStatusTransitionException::for('PipelineRun', $this->status->value, 'running');
        }
        $this->status = PipelineRunStatus::RUNNING;
    }

    public function markAsSuccess(): void
    {
        $this->status = PipelineRunStatus::SUCCESS;
    }

    public function markAsFailed(): void
    {
        $this->status = PipelineRunStatus::FAILED;
    }

    public function markAsAwaitingApproval(): void
    {
        $this->status = PipelineRunStatus::AWAITING_APPROVAL;
    }

    private function conditionMatches(?string $condition): bool
    {
        if ($condition === null) {
            return true;
        }

        if (preg_match('/^environment\s*==\s*(\w+)$/', $condition, $matches)) {
            return $this->environment->value === strtolower($matches[1]);
        }

        return true;
    }

    /** @param string[] $needs */
    private function allNeedsSatisfied(array $needs): bool
    {
        foreach ($needs as $neededJobId) {
            $satisfied = false;
            foreach ($this->jobRuns as $jr) {
                if ($jr->jobId() === $neededJobId && $jr->status() === JobRunStatus::SUCCESS) {
                    $satisfied = true;
                    break;
                }
            }
            if (!$satisfied) {
                return false;
            }
        }
        return true;
    }
}
```

- [ ] **Step 4: Run tests**

```bash
./vendor/bin/phpunit tests/Domain/PipelineRun/PipelineRunTest.php
```

Expected: PASS (8 tests)

- [ ] **Step 5: Run full domain test suite**

```bash
./vendor/bin/phpunit --testsuite unit
```

Expected: all PASS

- [ ] **Step 6: Commit**

```bash
git add src/Domain/PipelineRun/PipelineRun.php tests/Domain/PipelineRun/PipelineRunTest.php
git commit -m "feat: add PipelineRun aggregate with DAG evaluation and state machine"
```

---

### Task 9: Application ports and JobResult

**Files:**
- Create: `src/Application/Port/PipelineRepositoryPort.php`
- Create: `src/Application/Port/PipelineRunRepositoryPort.php`
- Create: `src/Application/Port/ExecutorPort.php`
- Create: `src/Application/Port/JobResult.php`

- [ ] **Step 1: Create PipelineRepositoryPort**

Create `src/Application/Port/PipelineRepositoryPort.php`:
```php
<?php

namespace App\Application\Port;

use App\Domain\Pipeline\Pipeline;
use App\Domain\Pipeline\PipelineId;

interface PipelineRepositoryPort
{
    public function findById(PipelineId $id): Pipeline;
}
```

- [ ] **Step 2: Create PipelineRunRepositoryPort**

Create `src/Application/Port/PipelineRunRepositoryPort.php`:
```php
<?php

namespace App\Application\Port;

use App\Domain\PipelineRun\PipelineRun;
use App\Domain\PipelineRun\PipelineRunId;

interface PipelineRunRepositoryPort
{
    public function save(PipelineRun $run): void;
    public function findById(PipelineRunId $id): PipelineRun;
}
```

- [ ] **Step 3: Create JobResult**

Create `src/Application/Port/JobResult.php`:
```php
<?php

namespace App\Application\Port;

final readonly class JobResult
{
    private function __construct(
        public readonly bool $success,
        public readonly string $output,
    ) {}

    public static function success(string $output = ''): self
    {
        return new self(true, $output);
    }

    public static function failure(string $output = ''): self
    {
        return new self(false, $output);
    }

    public function isSuccess(): bool { return $this->success; }
}
```

- [ ] **Step 4: Create ExecutorPort**

Create `src/Application/Port/ExecutorPort.php`:
```php
<?php

namespace App\Application\Port;

use App\Domain\Pipeline\Job;
use App\Domain\Shared\Environment;

interface ExecutorPort
{
    public function run(Job $job, Environment $environment): JobResult;
}
```

- [ ] **Step 5: Commit**

```bash
git add src/Application/Port/
git commit -m "feat: add application ports and JobResult"
```

---

### Task 10: FakeExecutorAdapter and InMemoryPipelineRunRepository

**Files:**
- Create: `src/Infrastructure/Executor/FakeExecutorAdapter.php`
- Create: `src/Infrastructure/Persistence/InMemory/InMemoryPipelineRunRepository.php`

- [ ] **Step 1: Create FakeExecutorAdapter**

Create `src/Infrastructure/Executor/FakeExecutorAdapter.php`:
```php
<?php

namespace App\Infrastructure\Executor;

use App\Application\Port\ExecutorPort;
use App\Application\Port\JobResult;
use App\Domain\Pipeline\Job;
use App\Domain\Shared\Environment;

class FakeExecutorAdapter implements ExecutorPort
{
    public function run(Job $job, Environment $environment): JobResult
    {
        return JobResult::success(
            sprintf('Simulated: would run "%s" on image "%s" in %s', $job->script, $job->image, $environment->value)
        );
    }
}
```

- [ ] **Step 2: Create InMemoryPipelineRunRepository**

Create `src/Infrastructure/Persistence/InMemory/InMemoryPipelineRunRepository.php`:
```php
<?php

namespace App\Infrastructure\Persistence\InMemory;

use App\Application\Port\PipelineRunRepositoryPort;
use App\Domain\PipelineRun\PipelineRun;
use App\Domain\PipelineRun\PipelineRunId;
use App\Domain\PipelineRun\PipelineRunNotFoundException;

class InMemoryPipelineRunRepository implements PipelineRunRepositoryPort
{
    /** @var PipelineRun[] */
    private array $store = [];

    public function save(PipelineRun $run): void
    {
        $this->store[$run->id()->value] = $run;
    }

    public function findById(PipelineRunId $id): PipelineRun
    {
        if (!isset($this->store[$id->value])) {
            throw PipelineRunNotFoundException::forId($id->value);
        }
        return $this->store[$id->value];
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add src/Infrastructure/Executor/ src/Infrastructure/Persistence/InMemory/
git commit -m "feat: add FakeExecutorAdapter and InMemoryPipelineRunRepository"
```

---

### Task 11: YamlFilePipelineRepository and sample pipeline

**Files:**
- Create: `src/Infrastructure/Persistence/Yaml/YamlFilePipelineRepository.php`
- Create: `pipelines/example.yaml`
- Create: `tests/Infrastructure/Persistence/YamlFilePipelineRepositoryTest.php`

- [ ] **Step 1: Write failing test**

Create `tests/Infrastructure/Persistence/YamlFilePipelineRepositoryTest.php`:
```php
<?php

namespace App\Tests\Infrastructure\Persistence;

use App\Domain\Pipeline\JobType;
use App\Domain\Pipeline\PipelineId;
use App\Domain\Pipeline\PipelineNotFoundException;
use App\Infrastructure\Persistence\Yaml\YamlFilePipelineRepository;
use PHPUnit\Framework\TestCase;

class YamlFilePipelineRepositoryTest extends TestCase
{
    private string $fixturesDir;
    private YamlFilePipelineRepository $repo;

    protected function setUp(): void
    {
        $this->fixturesDir = __DIR__ . '/fixtures';
        mkdir($this->fixturesDir, 0777, true);
        $this->repo = new YamlFilePipelineRepository($this->fixturesDir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->fixturesDir . '/*.yaml'));
        rmdir($this->fixturesDir);
    }

    public function test_it_loads_pipeline_from_yaml(): void
    {
        file_put_contents($this->fixturesDir . '/my-pipeline.yaml', <<<YAML
name: My Pipeline
jobs:
  build:
    image: php:8.4-cli
    script: composer install
  test:
    needs: build
    image: php:8.4-cli
    script: php bin/phpunit
YAML);

        $pipeline = $this->repo->findById(new PipelineId('my-pipeline'));

        $this->assertSame('My Pipeline', $pipeline->name());
        $this->assertCount(2, $pipeline->jobs());
        $this->assertSame('build', $pipeline->jobs()[0]->id);
        $this->assertSame(['build'], $pipeline->jobs()[1]->needs);
    }

    public function test_it_parses_approval_job_type(): void
    {
        file_put_contents($this->fixturesDir . '/approve-pipeline.yaml', <<<YAML
name: Approve Pipeline
jobs:
  gate:
    type: approval
YAML);

        $pipeline = $this->repo->findById(new PipelineId('approve-pipeline'));

        $this->assertSame(JobType::APPROVAL, $pipeline->jobs()[0]->type);
    }

    public function test_it_throws_when_pipeline_not_found(): void
    {
        $this->expectException(PipelineNotFoundException::class);
        $this->repo->findById(new PipelineId('nonexistent'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
./vendor/bin/phpunit tests/Infrastructure/Persistence/YamlFilePipelineRepositoryTest.php
```

Expected: FAIL — class not found

- [ ] **Step 3: Create YamlFilePipelineRepository**

Create `src/Infrastructure/Persistence/Yaml/YamlFilePipelineRepository.php`:
```php
<?php

namespace App\Infrastructure\Persistence\Yaml;

use App\Application\Port\PipelineRepositoryPort;
use App\Domain\Pipeline\Job;
use App\Domain\Pipeline\JobType;
use App\Domain\Pipeline\Pipeline;
use App\Domain\Pipeline\PipelineId;
use App\Domain\Pipeline\PipelineNotFoundException;
use Symfony\Component\Yaml\Yaml;

class YamlFilePipelineRepository implements PipelineRepositoryPort
{
    public function __construct(private readonly string $pipelinesDir) {}

    public function findById(PipelineId $id): Pipeline
    {
        $file = $this->pipelinesDir . '/' . $id->value . '.yaml';

        if (!file_exists($file)) {
            throw PipelineNotFoundException::forId($id->value);
        }

        $data = Yaml::parseFile($file);

        $jobs = [];
        foreach ($data['jobs'] ?? [] as $jobId => $jobData) {
            $type = isset($jobData['type']) && $jobData['type'] === 'approval'
                ? JobType::APPROVAL
                : JobType::SCRIPT;

            $needs = isset($jobData['needs'])
                ? (array) $jobData['needs']
                : [];

            $jobs[] = new Job(
                id: $jobId,
                type: $type,
                image: $jobData['image'] ?? null,
                script: $jobData['script'] ?? null,
                needs: $needs,
                condition: $jobData['if'] ?? null,
            );
        }

        return new Pipeline(new PipelineId($id->value), $data['name'] ?? $id->value, $jobs);
    }
}
```

- [ ] **Step 4: Create sample pipeline**

Create `pipelines/example.yaml`:
```yaml
name: Example Pipeline

jobs:
  build:
    image: php:8.4-cli
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
    script: echo "Deploying to staging"

  approve-prod:
    needs: deploy-staging
    type: approval

  deploy-prod:
    needs: approve-prod
    if: environment == production
    image: alpine:3.19
    script: echo "Deploying to production"
```

- [ ] **Step 5: Run tests**

```bash
./vendor/bin/phpunit tests/Infrastructure/Persistence/YamlFilePipelineRepositoryTest.php
```

Expected: PASS (3 tests)

- [ ] **Step 6: Commit**

```bash
git add src/Infrastructure/Persistence/Yaml/ tests/Infrastructure/Persistence/ pipelines/
git commit -m "feat: add YamlFilePipelineRepository and example pipeline"
```

---

### Task 12: TriggerPipeline command and handler

**Files:**
- Create: `src/Application/Command/TriggerPipeline/TriggerPipelineCommand.php`
- Create: `src/Application/Command/TriggerPipeline/TriggerPipelineHandler.php`
- Create: `tests/Application/TriggerPipelineHandlerTest.php`

- [ ] **Step 1: Write failing test**

Create `tests/Application/TriggerPipelineHandlerTest.php`:
```php
<?php

namespace App\Tests\Application;

use App\Application\Command\TriggerPipeline\TriggerPipelineCommand;
use App\Application\Command\TriggerPipeline\TriggerPipelineHandler;
use App\Domain\Pipeline\Job;
use App\Domain\Pipeline\JobType;
use App\Domain\Pipeline\Pipeline;
use App\Domain\Pipeline\PipelineId;
use App\Domain\PipelineRun\PipelineRunId;
use App\Domain\PipelineRun\PipelineRunStatus;
use App\Domain\Shared\Environment;
use App\Infrastructure\Persistence\InMemory\InMemoryPipelineRunRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\MessageBus;

class TriggerPipelineHandlerTest extends TestCase
{
    private InMemoryPipelineRunRepository $runRepo;
    private MessageBus $bus;

    protected function setUp(): void
    {
        $this->runRepo = new InMemoryPipelineRunRepository();
        $this->bus = new MessageBus([]);
    }

    private function makePipeline(array $jobs): Pipeline
    {
        return new Pipeline(new PipelineId('p1'), 'P1', $jobs);
    }

    public function test_it_creates_pipeline_run_and_dispatches_ready_jobs(): void
    {
        $pipeline = $this->makePipeline([
            new Job('build', JobType::SCRIPT, 'php:8.4-cli', 'composer install', [], null),
            new Job('test', JobType::SCRIPT, 'php:8.4-cli', 'phpunit', ['build'], null),
        ]);

        $dispatchedMessages = [];
        $bus = new MessageBus([
            new \Symfony\Component\Messenger\Middleware\HandleMessageMiddleware(
                new \Symfony\Component\Messenger\Handler\HandlersLocator([])
            ),
        ]);

        $pipelineRepo = $this->createConfiguredMock(\App\Application\Port\PipelineRepositoryPort::class, [
            'findById' => $pipeline,
        ]);

        $runId = PipelineRunId::generate();
        $handler = new TriggerPipelineHandler($pipelineRepo, $this->runRepo, $bus);
        $handler(new TriggerPipelineCommand($runId->value, 'p1', 'staging'));

        $run = $this->runRepo->findById($runId);
        $this->assertSame(PipelineRunStatus::RUNNING, $run->status());
    }
}
```

- [ ] **Step 2: Create TriggerPipelineCommand**

Create `src/Application/Command/TriggerPipeline/TriggerPipelineCommand.php`:
```php
<?php

namespace App\Application\Command\TriggerPipeline;

final readonly class TriggerPipelineCommand
{
    public function __construct(
        public readonly string $pipelineRunId,
        public readonly string $pipelineId,
        public readonly string $environment,
    ) {}
}
```

- [ ] **Step 3: Create TriggerPipelineHandler**

Create `src/Application/Command/TriggerPipeline/TriggerPipelineHandler.php`:
```php
<?php

namespace App\Application\Command\TriggerPipeline;

use App\Application\Command\ExecuteJob\ExecuteJobCommand;
use App\Application\Port\PipelineRepositoryPort;
use App\Application\Port\PipelineRunRepositoryPort;
use App\Domain\Pipeline\PipelineId;
use App\Domain\PipelineRun\PipelineRun;
use App\Domain\PipelineRun\PipelineRunId;
use App\Domain\Shared\Environment;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class TriggerPipelineHandler
{
    public function __construct(
        private readonly PipelineRepositoryPort $pipelineRepo,
        private readonly PipelineRunRepositoryPort $runRepo,
        private readonly MessageBusInterface $bus,
    ) {}

    public function __invoke(TriggerPipelineCommand $command): void
    {
        $pipeline = $this->pipelineRepo->findById(new PipelineId($command->pipelineId));
        $environment = Environment::fromString($command->environment);

        $run = new PipelineRun(new PipelineRunId($command->pipelineRunId), $pipeline, $environment);
        $run->markAsRunning();

        $this->runRepo->save($run);

        foreach ($run->readyJobs($pipeline) as $jobRun) {
            $jobRun->markAsRunning();
            $this->bus->dispatch(new ExecuteJobCommand($command->pipelineRunId, $jobRun->jobId()));
        }

        $this->runRepo->save($run);
    }
}
```

- [ ] **Step 4: Create ExecuteJobCommand (needed by handler)**

Create `src/Application/Command/ExecuteJob/ExecuteJobCommand.php`:
```php
<?php

namespace App\Application\Command\ExecuteJob;

final readonly class ExecuteJobCommand
{
    public function __construct(
        public readonly string $pipelineRunId,
        public readonly string $jobId,
    ) {}
}
```

- [ ] **Step 5: Run tests**

```bash
./vendor/bin/phpunit tests/Application/TriggerPipelineHandlerTest.php
```

Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add src/Application/Command/TriggerPipeline/ src/Application/Command/ExecuteJob/ExecuteJobCommand.php tests/Application/TriggerPipelineHandlerTest.php
git commit -m "feat: add TriggerPipeline command and handler"
```

---

### Task 13: ExecuteJob handler

**Files:**
- Create: `src/Application/Command/ExecuteJob/ExecuteJobHandler.php`
- Create: `tests/Application/ExecuteJobHandlerTest.php`

- [ ] **Step 1: Write failing tests**

Create `tests/Application/ExecuteJobHandlerTest.php`:
```php
<?php

namespace App\Tests\Application;

use App\Application\Command\ExecuteJob\ExecuteJobCommand;
use App\Application\Command\ExecuteJob\ExecuteJobHandler;
use App\Application\Port\PipelineRepositoryPort;
use App\Domain\Pipeline\Job;
use App\Domain\Pipeline\JobType;
use App\Domain\Pipeline\Pipeline;
use App\Domain\Pipeline\PipelineId;
use App\Domain\PipelineRun\PipelineRun;
use App\Domain\PipelineRun\PipelineRunId;
use App\Domain\PipelineRun\PipelineRunStatus;
use App\Domain\Shared\Environment;
use App\Infrastructure\Executor\FakeExecutorAdapter;
use App\Infrastructure\Persistence\InMemory\InMemoryPipelineRunRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\MessageBus;

class ExecuteJobHandlerTest extends TestCase
{
    private InMemoryPipelineRunRepository $runRepo;
    private FakeExecutorAdapter $executor;
    private MessageBus $bus;

    protected function setUp(): void
    {
        $this->runRepo = new InMemoryPipelineRunRepository();
        $this->executor = new FakeExecutorAdapter();
        $this->bus = new MessageBus([]);
    }

    private function createRun(array $jobs, Environment $env = Environment::STAGING): array
    {
        $pipeline = new Pipeline(new PipelineId('p1'), 'P1', $jobs);
        $run = new PipelineRun(PipelineRunId::generate(), $pipeline, $env);
        $run->markAsRunning();
        $this->runRepo->save($run);
        return [$pipeline, $run];
    }

    public function test_successful_job_marks_run_as_success_when_complete(): void
    {
        [$pipeline, $run] = $this->createRun([
            new Job('build', JobType::SCRIPT, 'php:8.4-cli', 'composer install', [], null),
        ]);

        $jobRun = $run->jobRunByJobId('build');
        $jobRun->markAsRunning();
        $this->runRepo->save($run);

        $pipelineRepo = $this->createConfiguredMock(PipelineRepositoryPort::class, ['findById' => $pipeline]);

        $handler = new ExecuteJobHandler($pipelineRepo, $this->runRepo, $this->executor, $this->bus);
        $handler(new ExecuteJobCommand($run->id()->value, 'build'));

        $updated = $this->runRepo->findById($run->id());
        $this->assertSame(PipelineRunStatus::SUCCESS, $updated->status());
    }

    public function test_approval_job_marks_pipeline_as_awaiting_approval(): void
    {
        [$pipeline, $run] = $this->createRun([
            new Job('gate', JobType::APPROVAL, null, null, [], null),
        ]);

        $jobRun = $run->jobRunByJobId('gate');
        $jobRun->markAsRunning();
        $this->runRepo->save($run);

        $pipelineRepo = $this->createConfiguredMock(PipelineRepositoryPort::class, ['findById' => $pipeline]);

        $handler = new ExecuteJobHandler($pipelineRepo, $this->runRepo, $this->executor, $this->bus);
        $handler(new ExecuteJobCommand($run->id()->value, 'gate'));

        $updated = $this->runRepo->findById($run->id());
        $this->assertSame(PipelineRunStatus::AWAITING_APPROVAL, $updated->status());
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
./vendor/bin/phpunit tests/Application/ExecuteJobHandlerTest.php
```

Expected: FAIL — class not found

- [ ] **Step 3: Create ExecuteJobHandler**

Create `src/Application/Command/ExecuteJob/ExecuteJobHandler.php`:
```php
<?php

namespace App\Application\Command\ExecuteJob;

use App\Application\Port\ExecutorPort;
use App\Application\Port\PipelineRepositoryPort;
use App\Application\Port\PipelineRunRepositoryPort;
use App\Domain\Pipeline\PipelineId;
use App\Domain\PipelineRun\PipelineRunId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class ExecuteJobHandler
{
    public function __construct(
        private readonly PipelineRepositoryPort $pipelineRepo,
        private readonly PipelineRunRepositoryPort $runRepo,
        private readonly ExecutorPort $executor,
        private readonly MessageBusInterface $bus,
    ) {}

    public function __invoke(ExecuteJobCommand $command): void
    {
        $run = $this->runRepo->findById(new PipelineRunId($command->pipelineRunId));
        $pipeline = $this->pipelineRepo->findById(new PipelineId($run->pipelineId()));

        $jobRun = $run->jobRunByJobId($command->jobId);
        $job = $pipeline->job($command->jobId);

        if ($job->isApproval()) {
            $jobRun->markAsAwaitingApproval();
            $run->markAsAwaitingApproval();
            $this->runRepo->save($run);
            return;
        }

        $result = $this->executor->run($job, $run->environment());

        if ($result->isSuccess()) {
            $jobRun->markAsSuccess();
        } else {
            $jobRun->markAsFailed();
            $run->markAsFailed();
            $this->runRepo->save($run);
            return;
        }

        if ($run->isComplete()) {
            $run->markAsSuccess();
            $this->runRepo->save($run);
            return;
        }

        foreach ($run->readyJobs($pipeline) as $nextJobRun) {
            $nextJobRun->markAsRunning();
            $this->bus->dispatch(new ExecuteJobCommand($command->pipelineRunId, $nextJobRun->jobId()));
        }

        $this->runRepo->save($run);
    }
}
```

- [ ] **Step 4: Run tests**

```bash
./vendor/bin/phpunit tests/Application/ExecuteJobHandlerTest.php
```

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Application/Command/ExecuteJob/ExecuteJobHandler.php tests/Application/ExecuteJobHandlerTest.php
git commit -m "feat: add ExecuteJob handler with DAG continuation and approval gate"
```

---

### Task 14: ApprovePipelineJob command and handler

**Files:**
- Create: `src/Application/Command/ApprovePipelineJob/ApprovePipelineJobCommand.php`
- Create: `src/Application/Command/ApprovePipelineJob/ApprovePipelineJobHandler.php`
- Create: `tests/Application/ApprovePipelineJobHandlerTest.php`

- [ ] **Step 1: Write failing test**

Create `tests/Application/ApprovePipelineJobHandlerTest.php`:
```php
<?php

namespace App\Tests\Application;

use App\Application\Command\ApprovePipelineJob\ApprovePipelineJobCommand;
use App\Application\Command\ApprovePipelineJob\ApprovePipelineJobHandler;
use App\Application\Port\PipelineRepositoryPort;
use App\Domain\Pipeline\Job;
use App\Domain\Pipeline\JobType;
use App\Domain\Pipeline\Pipeline;
use App\Domain\Pipeline\PipelineId;
use App\Domain\PipelineRun\PipelineRun;
use App\Domain\PipelineRun\PipelineRunId;
use App\Domain\PipelineRun\PipelineRunStatus;
use App\Domain\Shared\Environment;
use App\Infrastructure\Persistence\InMemory\InMemoryPipelineRunRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\MessageBus;

class ApprovePipelineJobHandlerTest extends TestCase
{
    public function test_approving_resumes_pipeline(): void
    {
        $runRepo = new InMemoryPipelineRunRepository();
        $pipeline = new Pipeline(new PipelineId('p1'), 'P1', [
            new Job('gate', JobType::APPROVAL, null, null, [], null),
            new Job('deploy', JobType::SCRIPT, 'alpine', 'echo deploy', ['gate'], null),
        ]);

        $run = new PipelineRun(PipelineRunId::generate(), $pipeline, Environment::STAGING);
        $run->markAsRunning();
        $gateRun = $run->jobRunByJobId('gate');
        $gateRun->markAsRunning();
        $gateRun->markAsAwaitingApproval();
        $run->markAsAwaitingApproval();
        $runRepo->save($run);

        $pipelineRepo = $this->createConfiguredMock(PipelineRepositoryPort::class, ['findById' => $pipeline]);
        $handler = new ApprovePipelineJobHandler($pipelineRepo, $runRepo, new MessageBus([]));
        $handler(new ApprovePipelineJobCommand($run->id()->value, 'gate', true));

        $updated = $runRepo->findById($run->id());
        $this->assertSame(PipelineRunStatus::RUNNING, $updated->status());
    }

    public function test_rejecting_marks_pipeline_failed(): void
    {
        $runRepo = new InMemoryPipelineRunRepository();
        $pipeline = new Pipeline(new PipelineId('p1'), 'P1', [
            new Job('gate', JobType::APPROVAL, null, null, [], null),
        ]);

        $run = new PipelineRun(PipelineRunId::generate(), $pipeline, Environment::STAGING);
        $run->markAsRunning();
        $gateRun = $run->jobRunByJobId('gate');
        $gateRun->markAsRunning();
        $gateRun->markAsAwaitingApproval();
        $run->markAsAwaitingApproval();
        $runRepo->save($run);

        $pipelineRepo = $this->createConfiguredMock(PipelineRepositoryPort::class, ['findById' => $pipeline]);
        $handler = new ApprovePipelineJobHandler($pipelineRepo, $runRepo, new MessageBus([]));
        $handler(new ApprovePipelineJobCommand($run->id()->value, 'gate', false));

        $updated = $runRepo->findById($run->id());
        $this->assertSame(PipelineRunStatus::FAILED, $updated->status());
    }
}
```

- [ ] **Step 2: Create ApprovePipelineJobCommand**

Create `src/Application/Command/ApprovePipelineJob/ApprovePipelineJobCommand.php`:
```php
<?php

namespace App\Application\Command\ApprovePipelineJob;

final readonly class ApprovePipelineJobCommand
{
    public function __construct(
        public readonly string $pipelineRunId,
        public readonly string $jobId,
        public readonly bool $approved,
    ) {}
}
```

- [ ] **Step 3: Create ApprovePipelineJobHandler**

Create `src/Application/Command/ApprovePipelineJob/ApprovePipelineJobHandler.php`:
```php
<?php

namespace App\Application\Command\ApprovePipelineJob;

use App\Application\Command\ExecuteJob\ExecuteJobCommand;
use App\Application\Port\PipelineRepositoryPort;
use App\Application\Port\PipelineRunRepositoryPort;
use App\Domain\Pipeline\PipelineId;
use App\Domain\PipelineRun\PipelineRunId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class ApprovePipelineJobHandler
{
    public function __construct(
        private readonly PipelineRepositoryPort $pipelineRepo,
        private readonly PipelineRunRepositoryPort $runRepo,
        private readonly MessageBusInterface $bus,
    ) {}

    public function __invoke(ApprovePipelineJobCommand $command): void
    {
        $run = $this->runRepo->findById(new PipelineRunId($command->pipelineRunId));
        $pipeline = $this->pipelineRepo->findById(new PipelineId($run->pipelineId()));

        $jobRun = $run->jobRunByJobId($command->jobId);

        if (!$command->approved) {
            $jobRun->markAsFailed();
            $run->markAsFailed();
            $this->runRepo->save($run);
            return;
        }

        $jobRun->markAsSuccess();
        $run->markAsRunning();

        foreach ($run->readyJobs($pipeline) as $nextJobRun) {
            $nextJobRun->markAsRunning();
            $this->bus->dispatch(new ExecuteJobCommand($command->pipelineRunId, $nextJobRun->jobId()));
        }

        $this->runRepo->save($run);
    }
}
```

- [ ] **Step 4: Run tests**

```bash
./vendor/bin/phpunit tests/Application/ApprovePipelineJobHandlerTest.php
```

Expected: PASS

- [ ] **Step 5: Run all application tests**

```bash
./vendor/bin/phpunit --testsuite integration
```

Expected: all PASS

- [ ] **Step 6: Commit**

```bash
git add src/Application/Command/ApprovePipelineJob/ tests/Application/ApprovePipelineJobHandlerTest.php
git commit -m "feat: add ApprovePipelineJob command and handler"
```

---

### Task 15: GetPipelineRunStatus query and handler

**Files:**
- Create: `src/Application/Query/GetPipelineRunStatus/GetPipelineRunStatusQuery.php`
- Create: `src/Application/Query/GetPipelineRunStatus/PipelineRunStatusView.php`
- Create: `src/Application/Query/GetPipelineRunStatus/GetPipelineRunStatusHandler.php`

- [ ] **Step 1: Create GetPipelineRunStatusQuery**

Create `src/Application/Query/GetPipelineRunStatus/GetPipelineRunStatusQuery.php`:
```php
<?php

namespace App\Application\Query\GetPipelineRunStatus;

final readonly class GetPipelineRunStatusQuery
{
    public function __construct(public readonly string $pipelineRunId) {}
}
```

- [ ] **Step 2: Create PipelineRunStatusView**

Create `src/Application/Query/GetPipelineRunStatus/PipelineRunStatusView.php`:
```php
<?php

namespace App\Application\Query\GetPipelineRunStatus;

final readonly class PipelineRunStatusView
{
    /** @param JobRunStatusView[] $jobs */
    public function __construct(
        public readonly string $id,
        public readonly string $pipelineId,
        public readonly string $status,
        public readonly string $environment,
        public readonly array $jobs,
    ) {}
}
```

Create `src/Application/Query/GetPipelineRunStatus/JobRunStatusView.php`:
```php
<?php

namespace App\Application\Query\GetPipelineRunStatus;

final readonly class JobRunStatusView
{
    public function __construct(
        public readonly string $id,
        public readonly string $jobId,
        public readonly string $status,
    ) {}
}
```

- [ ] **Step 3: Create GetPipelineRunStatusHandler**

Create `src/Application/Query/GetPipelineRunStatus/GetPipelineRunStatusHandler.php`:
```php
<?php

namespace App\Application\Query\GetPipelineRunStatus;

use App\Application\Port\PipelineRunRepositoryPort;
use App\Domain\PipelineRun\PipelineRunId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class GetPipelineRunStatusHandler
{
    public function __construct(private readonly PipelineRunRepositoryPort $runRepo) {}

    public function __invoke(GetPipelineRunStatusQuery $query): PipelineRunStatusView
    {
        $run = $this->runRepo->findById(new PipelineRunId($query->pipelineRunId));

        $jobs = array_map(
            fn($jr) => new JobRunStatusView($jr->id(), $jr->jobId(), $jr->status()->value),
            $run->jobRuns()
        );

        return new PipelineRunStatusView(
            $run->id()->value,
            $run->pipelineId(),
            $run->status()->value,
            $run->environment()->value,
            $jobs,
        );
    }
}
```

- [ ] **Step 4: Commit**

```bash
git add src/Application/Query/
git commit -m "feat: add GetPipelineRunStatus query and handler"
```

---

### Task 16: Doctrine repositories

**Files:**
- Create: `src/Infrastructure/Persistence/Doctrine/DoctrinePipelineRunRepository.php`
- Modify: `config/packages/doctrine.yaml` (schema create command)

- [ ] **Step 1: Create DoctrinePipelineRunRepository**

Create `src/Infrastructure/Persistence/Doctrine/DoctrinePipelineRunRepository.php`:
```php
<?php

namespace App\Infrastructure\Persistence\Doctrine;

use App\Application\Port\PipelineRunRepositoryPort;
use App\Domain\PipelineRun\PipelineRun;
use App\Domain\PipelineRun\PipelineRunId;
use App\Domain\PipelineRun\PipelineRunNotFoundException;
use Doctrine\ORM\EntityManagerInterface;

class DoctrinePipelineRunRepository implements PipelineRunRepositoryPort
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function save(PipelineRun $run): void
    {
        $this->em->persist($run);
        $this->em->flush();
    }

    public function findById(PipelineRunId $id): PipelineRun
    {
        $run = $this->em->find(PipelineRun::class, $id->value);

        if ($run === null) {
            throw PipelineRunNotFoundException::forId($id->value);
        }

        return $run;
    }
}
```

- [ ] **Step 2: Create database schema**

```bash
docker compose exec app php bin/console doctrine:schema:create
```

Expected: schema created with `pipeline_runs` and `job_runs` tables

- [ ] **Step 3: Verify schema**

```bash
docker compose exec app php bin/console doctrine:schema:validate
```

Expected: `[OK] The mapping files are correct.` and `[OK] The database schema is in sync with the mapping files.`

- [ ] **Step 4: Commit**

```bash
git add src/Infrastructure/Persistence/Doctrine/
git commit -m "feat: add DoctrinePipelineRunRepository"
```

---

### Task 17: Wire services and configure routes

**Files:**
- Modify: `config/services.yaml`
- Create: `config/routes.yaml`

- [ ] **Step 1: Wire port bindings in services.yaml**

Replace `config/services.yaml`:
```yaml
parameters:
    pipelines_dir: '%kernel.project_dir%/pipelines'

services:
    _defaults:
        autowire: true
        autoconfigure: true

    App\:
        resource: '../src/'
        exclude:
            - '../src/Domain/'
            - '../src/Kernel.php'

    # Port bindings
    App\Application\Port\PipelineRepositoryPort:
        class: App\Infrastructure\Persistence\Yaml\YamlFilePipelineRepository
        arguments:
            $pipelinesDir: '%pipelines_dir%'

    App\Application\Port\PipelineRunRepositoryPort:
        class: App\Infrastructure\Persistence\Doctrine\DoctrinePipelineRunRepository

    App\Application\Port\ExecutorPort:
        class: App\Infrastructure\Executor\FakeExecutorAdapter
```

- [ ] **Step 2: Commit**

```bash
git add config/services.yaml
git commit -m "feat: wire port bindings in services.yaml"
```

---

### Task 18: HTTP Controllers

**Files:**
- Create: `src/Infrastructure/Http/Controller/TriggerPipelineController.php`
- Create: `src/Infrastructure/Http/Controller/GetPipelineRunController.php`
- Create: `src/Infrastructure/Http/Controller/ApprovePipelineJobController.php`
- Modify: `config/routes.yaml`

- [ ] **Step 1: Create TriggerPipelineController**

Create `src/Infrastructure/Http/Controller/TriggerPipelineController.php`:
```php
<?php

namespace App\Infrastructure\Http\Controller;

use App\Application\Command\TriggerPipeline\TriggerPipelineCommand;
use App\Domain\PipelineRun\PipelineRunId;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

class TriggerPipelineController
{
    public function __construct(private readonly MessageBusInterface $bus) {}

    #[Route('/pipelines/{id}/run', methods: ['POST'])]
    public function __invoke(string $id, \Symfony\Component\HttpFoundation\Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true) ?? [];
        $environment = $body['environment'] ?? 'staging';

        $runId = PipelineRunId::generate();
        $this->bus->dispatch(new TriggerPipelineCommand($runId->value, $id, $environment));

        return new JsonResponse(['pipeline_run_id' => $runId->value], Response::HTTP_ACCEPTED);
    }
}
```

- [ ] **Step 2: Create GetPipelineRunController**

Create `src/Infrastructure/Http/Controller/GetPipelineRunController.php`:
```php
<?php

namespace App\Infrastructure\Http\Controller;

use App\Application\Query\GetPipelineRunStatus\GetPipelineRunStatusQuery;
use App\Domain\PipelineRun\PipelineRunNotFoundException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

class GetPipelineRunController
{
    public function __construct(private readonly MessageBusInterface $bus) {}

    #[Route('/pipeline-runs/{runId}', methods: ['GET'])]
    public function __invoke(string $runId): JsonResponse
    {
        try {
            $envelope = $this->bus->dispatch(new GetPipelineRunStatusQuery($runId));
            $view = $envelope->last(HandledStamp::class)->getResult();

            return new JsonResponse([
                'id' => $view->id,
                'pipeline_id' => $view->pipelineId,
                'status' => $view->status,
                'environment' => $view->environment,
                'jobs' => array_map(fn($j) => [
                    'id' => $j->id,
                    'job_id' => $j->jobId,
                    'status' => $j->status,
                ], $view->jobs),
            ]);
        } catch (PipelineRunNotFoundException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_NOT_FOUND);
        }
    }
}
```

- [ ] **Step 3: Create ApprovePipelineJobController**

Create `src/Infrastructure/Http/Controller/ApprovePipelineJobController.php`:
```php
<?php

namespace App\Infrastructure\Http\Controller;

use App\Application\Command\ApprovePipelineJob\ApprovePipelineJobCommand;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

class ApprovePipelineJobController
{
    public function __construct(private readonly MessageBusInterface $bus) {}

    #[Route('/pipeline-runs/{runId}/jobs/{jobId}/approve', methods: ['POST'])]
    public function approve(string $runId, string $jobId): JsonResponse
    {
        $this->bus->dispatch(new ApprovePipelineJobCommand($runId, $jobId, true));
        return new JsonResponse(['status' => 'approved'], Response::HTTP_OK);
    }

    #[Route('/pipeline-runs/{runId}/jobs/{jobId}/reject', methods: ['POST'])]
    public function reject(string $runId, string $jobId): JsonResponse
    {
        $this->bus->dispatch(new ApprovePipelineJobCommand($runId, $jobId, false));
        return new JsonResponse(['status' => 'rejected'], Response::HTTP_OK);
    }
}
```

- [ ] **Step 4: Update config/routes.yaml to scan Infrastructure controllers**

Replace `config/routes.yaml`:
```yaml
controllers:
    resource:
        path: ../src/Infrastructure/Http/Controller/
        namespace: App\Infrastructure\Http\Controller
    type: attribute
```

- [ ] **Step 5: Commit**

```bash
git add src/Infrastructure/Http/ config/routes.yaml
git commit -m "feat: add HTTP controllers for pipeline API"
```

---

### Task 19: Console Commands

**Files:**
- Create: `src/Infrastructure/Console/TriggerPipelineConsoleCommand.php`
- Create: `src/Infrastructure/Console/GetPipelineRunConsoleCommand.php`
- Create: `src/Infrastructure/Console/ApprovePipelineJobConsoleCommand.php`

- [ ] **Step 1: Create TriggerPipelineConsoleCommand**

Create `src/Infrastructure/Console/TriggerPipelineConsoleCommand.php`:
```php
<?php

namespace App\Infrastructure\Console;

use App\Application\Command\TriggerPipeline\TriggerPipelineCommand;
use App\Domain\PipelineRun\PipelineRunId;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(name: 'pipeline:run', description: 'Trigger a pipeline run')]
class TriggerPipelineConsoleCommand extends Command
{
    public function __construct(private readonly MessageBusInterface $bus)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('pipeline-id', InputArgument::REQUIRED, 'Pipeline ID (YAML filename without .yaml)')
            ->addOption('env', null, InputOption::VALUE_REQUIRED, 'Environment', 'staging');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $runId = PipelineRunId::generate();
        $this->bus->dispatch(new TriggerPipelineCommand(
            $runId->value,
            $input->getArgument('pipeline-id'),
            $input->getOption('env'),
        ));

        $output->writeln("Pipeline run triggered: <info>{$runId->value}</info>");
        return Command::SUCCESS;
    }
}
```

- [ ] **Step 2: Create GetPipelineRunConsoleCommand**

Create `src/Infrastructure/Console/GetPipelineRunConsoleCommand.php`:
```php
<?php

namespace App\Infrastructure\Console;

use App\Application\Query\GetPipelineRunStatus\GetPipelineRunStatusQuery;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[AsCommand(name: 'pipeline:status', description: 'Get pipeline run status')]
class GetPipelineRunConsoleCommand extends Command
{
    public function __construct(private readonly MessageBusInterface $bus)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('run-id', InputArgument::REQUIRED, 'Pipeline run ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $envelope = $this->bus->dispatch(new GetPipelineRunStatusQuery($input->getArgument('run-id')));
        $view = $envelope->last(HandledStamp::class)->getResult();

        $output->writeln("Run: <info>{$view->id}</info> | Status: <comment>{$view->status}</comment> | Env: {$view->environment}");
        foreach ($view->jobs as $job) {
            $output->writeln("  [{$job->status}] {$job->jobId}");
        }

        return Command::SUCCESS;
    }
}
```

- [ ] **Step 3: Create ApprovePipelineJobConsoleCommand**

Create `src/Infrastructure/Console/ApprovePipelineJobConsoleCommand.php`:
```php
<?php

namespace App\Infrastructure\Console;

use App\Application\Command\ApprovePipelineJob\ApprovePipelineJobCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(name: 'pipeline:approve', description: 'Approve or reject an approval gate')]
class ApprovePipelineJobConsoleCommand extends Command
{
    public function __construct(private readonly MessageBusInterface $bus)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('run-id', InputArgument::REQUIRED, 'Pipeline run ID')
            ->addArgument('job-id', InputArgument::REQUIRED, 'Job ID of the approval gate')
            ->addArgument('decision', InputArgument::OPTIONAL, 'approve or reject', 'approve');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $approved = $input->getArgument('decision') === 'approve';
        $this->bus->dispatch(new ApprovePipelineJobCommand(
            $input->getArgument('run-id'),
            $input->getArgument('job-id'),
            $approved,
        ));

        $label = $approved ? 'approved' : 'rejected';
        $output->writeln("Job <info>{$input->getArgument('job-id')}</info> {$label}.");
        return Command::SUCCESS;
    }
}
```

- [ ] **Step 4: Commit**

```bash
git add src/Infrastructure/Console/
git commit -m "feat: add Console commands for pipeline:run, pipeline:status, pipeline:approve"
```

---

### Task 20: Smoke test end-to-end

- [ ] **Step 1: Start containers**

```bash
docker compose up -d
```

- [ ] **Step 2: Create schema**

```bash
docker compose exec app php bin/console doctrine:schema:create
```

- [ ] **Step 3: Trigger pipeline via API**

```bash
curl -s -X POST http://localhost:8080/pipelines/example/run \
  -H "Content-Type: application/json" \
  -d '{"environment":"staging"}' | jq .
```

Expected:
```json
{"pipeline_run_id": "some-uuid"}
```

- [ ] **Step 4: Check status**

```bash
curl -s http://localhost:8080/pipeline-runs/<run-id> | jq .
```

Expected: status `running` or `success` (depending on whether worker processed it)

- [ ] **Step 5: Trigger via Console**

```bash
docker compose exec app php bin/console pipeline:run example --env=staging
```

Expected: `Pipeline run triggered: <uuid>`

- [ ] **Step 6: Check RabbitMQ management UI**

Open http://localhost:15672 (app/app). Verify messages are being consumed from `messages` queue.

- [ ] **Step 7: Run full test suite**

```bash
./vendor/bin/phpunit
```

Expected: all unit and integration tests PASS

- [ ] **Step 8: Final commit**

```bash
git add .
git commit -m "feat: complete iteration 1 - pipeline orchestrator with FakeExecutorAdapter"
```
