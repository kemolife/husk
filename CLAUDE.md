# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Deployment Pipeline Orchestrator — Symfony 8.0 backend with hexagonal architecture. Manages CI/CD pipelines, executes jobs via SSH/Docker/Kubernetes adapters, streams logs to a React frontend.

## Commands

```bash
# Install dependencies
composer install

# Clear cache
php bin/console cache:clear

# Run dev server
symfony server:start

# Run tests (once phpunit is added)
php bin/phpunit
php bin/phpunit --filter TestName   # single test

# Lint
php bin/console lint:yaml config/
php bin/console debug:container      # verify service wiring
```

## Architecture

Strict hexagonal (ports & adapters). No Symfony/Doctrine imports inside `Domain/` or `Application/`.

```
src/
├── Domain/               # Pure PHP — no framework deps
│   ├── Pipeline/         # Pipeline, Stage, Job aggregates
│   ├── Deployment/       # Deployment aggregate, state machine
│   └── Shared/           # Value objects, base interfaces
│
├── Application/          # Use cases, command/query handlers
│   ├── Command/          # Write side (TriggerDeployment, RollbackDeployment)
│   ├── Query/            # Read side (GetDeploymentStatus, ListPipelines)
│   └── Port/             # Interfaces the domain depends on
│       ├── DeploymentExecutorPort.php
│       ├── LogStreamPort.php
│       └── NotificationPort.php
│
├── Infrastructure/       # Adapters — implements Application\Port interfaces
│   ├── Persistence/      # Doctrine repositories
│   ├── Executor/         # SSHExecutorAdapter, KubernetesAdapter, DockerApiAdapter
│   ├── Notification/     # SlackAdapter, EmailAdapter
│   └── Http/             # External API clients
│
└── Controller/           # Symfony HTTP layer — thin, calls Application commands/queries
```

## Key Conventions

**Ports live in `Application/Port/`** — interfaces only, no implementations.

**Adapters live in `Infrastructure/`** — implement ports, may use Symfony/Doctrine/HTTP clients.

**Domain has zero knowledge of adapters** — all external dependencies flow inward via ports.

**Symfony Messenger** handles async job execution — controllers dispatch commands to queue, workers consume and call executor adapters.

**State transitions** (`PENDING → RUNNING → SUCCESS/FAILED`) live purely in domain aggregates, not in infrastructure.

## Environment

- PHP 8.4+
- Symfony 8.0
- `APP_ENV=dev` default, `APP_SECRET` set in `.env.dev`
