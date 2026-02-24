# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Clave is a Laravel Zero CLI that spins up ephemeral Ubuntu VMs via [Tart](https://tart.run/) for isolated Claude Code sessions against Laravel projects. It integrates with Herd Pro on the macOS host for MySQL, Redis, and `.test` domain proxying.

The binary is `clave` (project root). Run from within a Laravel project's git repo: `clave` handles worktree creation, VM lifecycle, networking, and teardown automatically.

## Commands

```bash
# Run tests
./vendor/bin/pest

# Run a single test file
./vendor/bin/pest tests/Feature/SomeTest.php

# Run a single test by name
./vendor/bin/pest --filter="test name"

# Format code
./vendor/bin/pint

# Lint code
php clave lint

# Build PHAR
php clave app:build
```

## Architecture

**Laravel Zero 12** app using the Laravel Pipeline pattern for session lifecycle.

### Session Pipeline

The core flow is a `SessionContext` DTO passed through pipeline stages in sequence:

`CreateWorktree → CloneVm → BootVm → DiscoverGateway → CreateSshTunnel → ConfigureHerdProxy → BootstrapLaravel → RunClaudeCode`

Each stage populates fields on `SessionContext` and calls `$next()`. Teardown runs in a `finally` block via `SessionTeardown`, which reverses each stage (remove proxy, kill tunnel, stop/delete VM, handle worktree).

### Planned Directory Layout

```
app/
├── Commands/          # CLI commands (DefaultCommand, ProvisionCommand, etc.)
├── Dto/               # SessionContext, ServiceConfig
├── Models/            # Session (SQLite tracking)
├── Pipeline/          # One class per pipeline stage
├── Services/          # TartManager, GitManager, HerdManager, SshExecutor, SessionTeardown
└── Providers/
```

### Key Service Classes

- **TartManager** — wraps the `tart` CLI for VM clone/run/stop/delete/ip operations
- **GitManager** — worktree create/remove/merge, .gitignore management
- **SshExecutor** — SSH command execution, tunnels, interactive TTY sessions
- **HerdManager** — Herd Pro proxy/unproxy
- **ProvisioningPipeline** — idempotent base VM image setup steps (PHP, nginx, Claude Code, etc.)

### External Dependencies

- **Tart** (`tart` CLI) — Apple Silicon VM management via Virtualization.framework
- **Herd Pro** — host MySQL/Redis, `.test` domain proxying
- **Claude Code** — installed inside the VM, run via SSH TTY

## Conventions

- Uses **Pest** for testing (not PHPUnit directly)
- Uses **LaraLint** for linting (`glhd/laralint`)
- Uses **PHP CS Fixer** for formatting
- Commands auto-discovered from `app/Commands/`
- Config in `config/` directory (Laravel Zero standard)

## Guidelines

### LaraLint

This project uses `laralint` to lint files. Always run `php clave laralint:lint {files}` on new or changed PHP code.

### PHP CS Fixer

This project uses `php-cs-fixer` to automatically apply code style. Alwasy run `./vendor/bin/php-cs-fixer {files}` on new or changed code.
