# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Clave is a Laravel Zero CLI that spins up ephemeral Ubuntu VMs via [Tart](https://tart.run/) for isolated Claude Code sessions against Laravel projects. Run `clave` from within a Laravel project's git repo — it handles worktree creation, VM lifecycle, networking, and teardown automatically. Multiple simultaneous sessions work naturally with unique worktrees, VMs, and ports.

## Commands

```bash
# Run tests
./vendor/bin/pest

# Run a single test file
./vendor/bin/pest tests/Feature/SomeTest.php

# Run a single test by name
./vendor/bin/pest --filter="test name"

# Format code (one file at a time)
./vendor/bin/php-cs-fixer fix path/to/File.php

# Lint code
php clave laralint:lint path/to/File.php [path/to/Other.php ...]

# Build PHAR
php clave app:build
```

## Architecture

**Laravel Zero 12** app (PHP 8.3+) using the Pipeline pattern for session lifecycle.

### Session Pipeline

A `SessionContext` DTO flows through pipeline stages in `DefaultCommand`:

`CreateWorktree → CloneVm → BootVm → RunClaudeCode`

Each stage populates fields on `SessionContext` and calls `$next()`. Teardown runs via `SessionTeardown` in both a `finally` block and SIGINT/SIGTERM signal handler.

Future stages (not yet implemented): `DiscoverGateway → CreateSshTunnel → ConfigureHerdProxy → BootstrapLaravel` (to be inserted between `BootVm` and `RunClaudeCode`).

### Key Services (all registered as singletons)

- **TartManager** — wraps the `tart` CLI for VM clone/run/stop/delete/ip
- **GitManager** — worktree create/remove/merge, .gitignore management
- **SshExecutor** — SSH command execution, tunnels, interactive TTY sessions
- **HerdManager** — Herd Pro proxy/unproxy (future use)
- **SessionTeardown** — reverses each pipeline stage on exit
- **ProvisioningPipeline** — generates bash provisioning script for base VM setup

### DTOs

- **SessionContext** — mutable pipeline state (session_id, vm_name, vm_ip, worktree_path, etc.)
- **ServiceConfig** — readonly database/Redis config
- **OnExit** — enum (Keep, Merge, Discard) for worktree handling on session end

## Conventions

- **User Input**: Use Laravel prompts rather than Command input helpers (eg. `use function Laravel\Prompts\select`)
- **Testing**: Pest (not PHPUnit). Tests using `Process::fake()` must be Feature tests (need app context).
- **Formatting**: `php-cs-fixer` (NOT Pint). Run `./vendor/bin/php-cs-fixer fix <file>` — one file per invocation.
- **Linting**: LaraLint (`glhd/laralint`). Run `php clave laralint:lint <files>` on new/changed PHP code.
- **Variables**: snake_case for local variables (enforced by LaraLint).
- **Class ordering**: LaraLint requires static methods before constructors.
- **Return types**: Service methods returning process results use `mixed` return type to support `Process::fake()` in tests (since `FakeProcessResult` doesn't extend `ProcessResult`).
