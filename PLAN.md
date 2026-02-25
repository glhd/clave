# Clave — Implementation Plan

A Laravel Zero CLI that spins up ephemeral Ubuntu VMs via Tart for isolated Claude Code sessions against Laravel projects, with Herd Pro integration on the host.

---

## Progress

### Sprint 1 — Boot Loop
- [x] Scaffold Laravel Zero project, install database/dotenv components
- [x] `SessionContext` and `ServiceConfig` DTOs
- [x] `OnExit` enum (keep/merge/discard) with `EnumHelpers` trait
- [x] `TartManager` — clone, run, stop, delete, ip, exists, list, set, randomizeMac, rename
- [x] `SshExecutor` — run, interactive, tunnel, test (password auth via SSH_ASKPASS)
- [x] `ProvisionCommand` + `ProvisioningPipeline` — build base image (PHP 8.4, nginx, Node 22, Claude Code)
- [x] `AuthManager` + `AuthCommand` — API key + OAuth token support with local storage
- [x] Preflight pipeline: `ValidateProject → GetGitBranch → EnsureVmExists → CheckClaudeAuthentication → SaveSession`
- [x] Session pipeline: `CloneRepo → CloneVm → BootVm → RunClaudeCode`
- [x] `SessionTeardown` — VM stop/delete, worktree prompt, Herd unproxy, tunnel kill, session record cleanup
- [x] Signal handling via `$this->trap()` (SIGINT/SIGTERM)
- [x] Sessions SQLite table + `Session` model
- [x] `SessionPipeline` base class with progress tracking via Laravel Prompts
- [x] `Step` interface + `ProgressAware` interface + `AcceptsProgress` trait
- [x] VirtioFS mount with retry logic and diagnostics

### Sprint 2 — Networking + Herd
- [ ] `DiscoverGateway` step — find NAT gateway IP inside VM
- [ ] `BootstrapLaravel` step — symlink, .env patching, composer install, migrate
- [ ] `CreateSshTunnel` step — port forwarding VM:80 → localhost:port
- [ ] `ConfigureHerdProxy` step — `herd proxy` setup/teardown
- [ ] Port auto-assignment (scan 8081–8199)
- [ ] Host service connectivity check (warn if MySQL/Redis not reachable from VM)

### Sprint 3 — Polish
- [ ] `SessionsCommand` — list active sessions
- [ ] `CleanupCommand` — remove orphaned VMs
- [ ] `ConfigCommand` — manage per-user settings
- [ ] `GitManager` merge flow verification (merge worktree back to base branch)
- [ ] `--resume` flag for reconnecting to a running session
- [ ] Error handling: composer failures, SSH timeouts, missing host services

### Sprint 4 — Distribution + Future
- [ ] Build PHAR: `php clave app:build`
- [ ] Per-project `.clave.json` config
- [ ] `clave provision --update`
- [ ] In-VM services mode (`services.mode: "vm"`)
- [ ] `clave exec` and `clave ssh` for running sessions

---

## Core UX

```bash
# From a Laravel project directory (must be a git repo):
clave

# That's it. Clave will:
# 1. Ensure a provisioned base VM image exists (first run only)
# 2. Create a git worktree for this session
# 3. Clone the base image to an ephemeral VM
# 4. Boot the VM with the worktree mounted via VirtioFS
# 5. Set up port forwarding so the VM's nginx is reachable from the host
# 6. Configure Herd Pro proxy (project.test → VM)
# 7. Bootstrap the Laravel app inside the VM (pointing at host MySQL/Redis)
# 8. Drop you into an interactive Claude Code session inside the VM
# 9. On exit: tear down proxy, stop VM, delete clone, prompt about worktree
```

Multiple simultaneous sessions work naturally — each `clave` invocation gets its own worktree, its own VM clone, and its own port. Run three terminals, run `clave` three times, get three isolated Claude Code agents working in parallel on different branches.

---

## Architecture

```
Terminal 1                    Terminal 2                    Terminal 3
clave                         clave                         clave
  │                             │                             │
  ▼                             ▼                             ▼
worktree: .clave/wt/s-a1b2    .clave/wt/s-c3d4             .clave/wt/s-e5f6
vm:       clave-a1b2           clave-c3d4                   clave-e5f6
port:     8081                  8082                         8083
proxy:    my-app-a1b2.test     my-app-c3d4.test             my-app-e5f6.test
  │                             │                             │
  ▼                             ▼                             ▼
┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐
│ Ubuntu VM       │  │ Ubuntu VM       │  │ Ubuntu VM       │
│ nginx + php-fpm │  │ nginx + php-fpm │  │ nginx + php-fpm │
│ Claude Code     │  │ Claude Code     │  │ Claude Code     │
│ /srv/project    │  │ /srv/project    │  │ /srv/project    │
│       │         │  │       │         │  │       │         │
│       ▼         │  │       ▼         │  │       ▼         │
│ DB_HOST=gateway │  │ DB_HOST=gateway │  │ DB_HOST=gateway │
└───────┬─────────┘  └───────┬─────────┘  └───────┬─────────┘
        │ NAT gateway                │                │
        └────────────┬───────────────┘                │
                     ▼                                │
┌──────────────────────────────────────────────────────┐
│ Host (macOS)                                         │
│ Herd Pro: MySQL (3306) · Redis (6379) · PHP · nginx  │
│ Parked sites: ~/Herd/*                               │
└──────────────────────────────────────────────────────┘
```

### Service Architecture (v0)

For v0, **MySQL and Redis run on the host via Herd Pro.** Each VM connects to host services through the NAT gateway IP (typically `192.168.64.1`). This means all sessions share the same database and Redis instance — which is fine for most development workflows and avoids duplicating heavy services in every ephemeral VM.

The VM runs only nginx and PHP-FPM — just enough to serve the Laravel app and provide Claude Code a realistic execution environment for running artisan commands, tests, etc.

> **Future: in-VM services.** The architecture is designed so that a future version can optionally provision MySQL and Redis inside the VM for full session isolation. This is controlled by the `ServiceConfig` DTO — swapping from `ServiceConfig::hostServices()` to `ServiceConfig::localServices()` is the only change needed in the pipeline. The provisioning pipeline would gain additional steps for MySQL/Redis, gated by a config flag.

### Host Service Requirements

Herd Pro's MySQL and Redis must be reachable from the VM's network. By default:

- **MySQL**: Herd Pro binds to `127.0.0.1:3306`. This needs to be changed to `0.0.0.0:3306` (or the gateway IP) for VMs to connect. Clave should detect this and warn on first run.
- **Redis**: Same situation — Herd Pro's Redis binds to `127.0.0.1:6379` and needs to accept connections from the VM subnet.

The NAT gateway IP is discovered by running `ip route | grep default` inside the VM.

---

## Git Worktree Strategy

A git repo is **required**. If the current directory is not inside a git repository, `clave` exits with an error.

When `clave` starts, it creates a worktree so each session has an isolated copy of the codebase. Claude Code in session A can be refactoring the auth system while session B rewrites the billing module — no conflicts.

```
my-app/                          # main working copy (untouched)
my-app/.clave/wt/s-a1b2c3d4/    # session A's worktree
my-app/.clave/wt/s-e5f6g7h8/    # session B's worktree
```

**Worktree lifecycle:**

1. Generate a random 8-character session ID via `Str::random(8)`
2. Create a new branch: `clave/s-{id}` from current HEAD
3. `git worktree add .clave/wt/s-{id} clave/s-{id}`
4. Mount `.clave/wt/s-{id}` into the VM via VirtioFS at `/srv/project`
5. On session exit, prompt (via Laravel Prompts `select()`):
    - **Keep** (default) — leave the worktree and branch for manual review
    - **Merge** — merge the session branch back to the original branch, remove worktree
    - **Discard** — remove worktree and delete branch

`.clave/` is automatically added to `.gitignore` on first use.

---

## Pipeline Architecture

The session lifecycle is split into two pipelines, both extending a `SessionPipeline` base class that provides progress tracking via Laravel Prompts. A `SessionContext` DTO flows through each stage, accumulating state. Each stage implements the `Step` interface and is responsible for one concern.

### SessionPipeline Base Class

```php
abstract class SessionPipeline extends Pipeline
{
    abstract public function label(): string;
    abstract public function steps(): array;

    public function run(SessionContext $context): SessionContext
    {
        // Creates a progress bar, sends context through steps,
        // injects ProgressAware trait for steps that need it,
        // handles exceptions with progress bar cleanup
    }
}
```

### Step Interface + Progress Awareness

```php
interface Step
{
    public function handle(SessionContext $context, Closure $next): mixed;
}

interface ProgressAware
{
    public function setProgress(Progress $progress): void;
}

trait AcceptsProgress
{
    protected ?Progress $progress = null;

    public function setProgress(Progress $progress): void { ... }
    protected function hint(string $message): void { ... }
}
```

### SessionContext DTO

```php
class SessionContext
{
    public function __construct(
        // Determined at creation
        public readonly string $session_id,
        public readonly string $project_name,
        public readonly string $project_dir,
        public readonly ?OnExit $on_exit = null,
        public readonly ?Command $command = null,

        // Populated by pipeline stages
        public ?string $base_branch = null,
        public ?string $vm_name = null,
        public ?string $vm_ip = null,
        public ?string $clone_path = null,
        public ?string $clone_branch = null,
        public ?string $proxy_name = null,
        public ?int $tunnel_port = null,
        public ?InvokedProcess $tunnel_process = null,
        public ?ServiceConfig $services = null,
        public ?Session $session = null,
    ) {}

    public function info(string $message): void { ... }
    public function warn(string $message): void { ... }
    public function error(string $message): void { ... }
    public function abort(string $message): never { ... }
}
```

### OnExit Enum

```php
enum OnExit: string
{
    use EnumHelpers;

    case Keep = 'keep';
    case Merge = 'merge';
    case Discard = 'discard';
}
```

### ServiceConfig DTO

```php
class ServiceConfig
{
    public function __construct(
        public readonly string $mysql_host,
        public readonly int $mysql_port,
        public readonly string $redis_host,
        public readonly int $redis_port,
    ) {}

    public static function hostServices(string $gateway_ip): static { ... }
    public static function localServices(): static { ... }
}
```

### Pipeline Orchestration

```php
// In DefaultCommand::handle():

$context = $this->newContext();

// Phase 1: Validate project, check auth, ensure VM exists
$preflight->run($context);

// Register cleanup on interrupt
$this->trap([SIGINT, SIGTERM], function () use ($context, $teardown) {
    $teardown->run($context);
});

// Phase 2: Create worktree, boot VM, run Claude
try {
    $claude->run($context);
} finally {
    $teardown->run($context);
}
```

### Preflight Pipeline

Label: "Setting up project..."

```
ValidateProject → GetGitBranch → EnsureVmExists → CheckClaudeAuthentication → SaveSession
```

1. **ValidateProject** — checks for `artisan` file, aborts if not a Laravel project
2. **GetGitBranch** — verifies git repo, sets `context->base_branch`
3. **EnsureVmExists** — checks for base VM, calls `ProvisionCommand` if missing
4. **CheckClaudeAuthentication** — verifies auth via `AuthManager`, attempts setup if missing
5. **SaveSession** — creates `Session` model record in SQLite

### Claude Code Pipeline

Label: "Starting session..."

```
CloneRepo → CloneVm → BootVm → RunClaudeCode
```

Future stages to be inserted between `BootVm` and `RunClaudeCode`:
```
DiscoverGateway → CreateSshTunnel → ConfigureHerdProxy → BootstrapLaravel
```

#### CloneRepo

```php
class CloneRepo implements Step
{
    use AcceptsProgress;

    public function handle(SessionContext $context, Closure $next): mixed
    {
        $branch = "clave/s-{$context->session_id}";
        $clone_path = "{$context->project_dir}/.clave/wt/s-{$context->session_id}";

        $this->git->ensureIgnored($context->project_dir, '.clave/');
        $this->git->cloneLocal($context->project_dir, $clone_path, $branch);

        $context->clone_path = $clone_path;
        $context->clone_branch = $branch;

        return $next($context);
    }
}
```

#### CloneVm

```php
class CloneVm implements Step
{
    use AcceptsProgress;

    public function handle(SessionContext $context, Closure $next): mixed
    {
        $vm_name = "clave-{$context->session_id}";

        $this->tart->clone(config('clave.base_vm'), $vm_name);
        $this->tart->randomizeMac($vm_name);

        // Apply per-session overrides from config
        $this->tart->set($vm_name,
            cpus: config('clave.vm.cpus'),
            memory: config('clave.vm.memory'),
            display: config('clave.vm.display'),
        );

        $context->vm_name = $vm_name;

        return $next($context);
    }
}
```

#### BootVm

```php
class BootVm implements Step
{
    use AcceptsProgress;

    public function handle(SessionContext $context, Closure $next): mixed
    {
        $mount_path = $context->clone_path ?? $context->project_dir;

        $this->tart->runBackground($context->vm_name, [
            'project' => $mount_path,
        ]);

        // Wait for IP (timeout: 90s)
        $context->vm_ip = $this->tart->ip($context->vm_name, timeout: 90);
        $this->ssh->setHost($context->vm_ip);

        // Wait for SSH (timeout: 90s)
        $this->ssh->usePassword(config('clave.ssh.password'));
        // Polls ssh->test() with retry

        // Mount VirtioFS with retry logic (timeout: 30s)
        // Falls back to detailed diagnostics on failure
        $this->ssh->run('sudo mount -t virtiofs com.apple.virtio-fs.automount /srv/project');

        return $next($context);
    }
}
```

#### RunClaudeCode

```php
class RunClaudeCode implements Step
{
    use AcceptsProgress;

    public function handle(SessionContext $context, Closure $next): mixed
    {
        // Resolve auth (API key or OAuth token) via AuthManager
        // If OAuth, write credentials to ~/.claude/.credentials.json on VM
        // Write settings to ~/.claude.json and ~/.claude/settings.json

        // Finish progress bar before interactive session
        $this->progress->finish();

        $this->ssh->interactive(
            'cd /srv/project && [ENV] claude --dangerously-skip-permissions'
        );

        return $next($context);
    }
}
```

#### Future Steps (Sprint 2)

```php
class DiscoverGateway implements Step
{
    public function handle(SessionContext $context, Closure $next): mixed
    {
        $result = $this->ssh->run("ip route | grep default | awk '{print \$3}'");
        $context->gateway_ip = trim($result->output());
        $context->services = ServiceConfig::hostServices($context->gateway_ip);

        return $next($context);
    }
}
```

```php
class CreateSshTunnel implements Step
{
    public function handle(SessionContext $context, Closure $next): mixed
    {
        $host_port = $this->findAvailablePort(8081, 8199);
        $context->tunnel_port = $host_port;
        $context->tunnel_process = $this->ssh->tunnel($host_port, $context->vm_ip, 80);

        return $next($context);
    }
}
```

```php
class ConfigureHerdProxy implements Step
{
    public function handle(SessionContext $context, Closure $next): mixed
    {
        $proxy_name = "{$context->project_name}-{$context->session_id}";
        $context->proxy_name = $proxy_name;
        $this->herd->proxy($proxy_name, "http://127.0.0.1:{$context->tunnel_port}", secure: true);

        return $next($context);
    }
}
```

```php
class BootstrapLaravel implements Step
{
    public function handle(SessionContext $context, Closure $next): mixed
    {
        // Symlink VirtioFS mount to where nginx expects it
        // Configure .env with host service connections
        // composer install
        // php artisan migrate --force
        // Restart php-fpm + nginx

        return $next($context);
    }
}
```

---

## Session Teardown

Cleanup runs after the pipeline completes (or on interrupt via signal handler). Each step is guarded by null checks and wrapped in `rescue()` so a failure in one doesn't prevent the others. A `$completed` flag prevents double execution.

```php
class SessionTeardown
{
    protected bool $completed = false;

    public function run(SessionContext $context): void
    {
        if ($this->completed) {
            return;
        }

        $this->completed = true;

        $this->unproxy($context);       // Remove Herd proxy if set
        $this->killTunnel($context);     // Stop SSH tunnel if running
        $this->stopVm($context);         // tart stop
        $this->deleteVm($context);       // tart delete
        $this->handleClone($context); // Prompt: keep/merge/discard
        $this->deleteSession($context);  // Remove Session record
    }

    protected function handleClone(SessionContext $context): void
    {
        $action = $context->on_exit;

        // If no pre-selected action, prompt user via Laravel Prompts
        $action ??= OnExit::coerce(select(
            label: 'What would you like to do with the worktree?',
            options: OnExit::toSelectArray(),
            default: OnExit::Keep->value,
        ));

        match ($action) {
            OnExit::Merge => $this->git->mergeAndCleanClone(...),
            OnExit::Discard => $this->git->removeClone(...),
            default => null, // keep
        };
    }
}
```

---

## CLI Commands

### `clave` (default command)

```php
class DefaultCommand extends Command
{
    protected $signature = 'default {--on-exit= : Action on exit: keep, merge, discard}';

    public function handle(
        PreflightPipeline $preflight,
        ClaudeCodePipeline $claude,
        SessionTeardown $teardown,
    ): int {
        clear();
        $this->callSilently('migrate', ['--force' => true]);

        $context = new SessionContext(
            session_id: Str::random(8),
            project_name: basename(getcwd()),
            project_dir: getcwd(),
            on_exit: OnExit::tryFrom($this->option('on-exit') ?? ''),
            command: $this,
        );

        $preflight->run($context);

        $this->trap([SIGINT, SIGTERM], function () use ($context, $teardown) {
            $teardown->run($context);
        });

        try {
            $claude->run($context);
        } finally {
            $teardown->run($context);
        }

        return self::SUCCESS;
    }
}
```

### `clave provision`

Builds or rebuilds the base VM image. Generates a provisioning bash script via `ProvisioningPipeline::toScript()`, mounts it into the VM via VirtioFS, and executes it.

```php
class ProvisionCommand extends Command
{
    protected $signature = 'provision
        {--force : Re-provision}
        {--image= : OCI image to pull}';

    public function handle(TartManager $tart, SshExecutor $ssh): int
    {
        // Clone OCI image to temp VM name
        // Configure VM (CPUs, memory, display)
        // Write provisioning script to temp dir
        // Mount script dir via VirtioFS
        // Boot VM, wait for SSH
        // Execute provisioning script
        // Stop VM, rename to base VM name
    }
}
```

### `clave auth`

Manages Claude Code authentication (API key or OAuth token).

```php
class AuthCommand extends Command
{
    protected $signature = 'auth
        {--status : Show auth method}
        {--clear : Remove stored token}';

    public function handle(AuthManager $auth): int
    {
        // --status: Show current auth method and source
        // --clear: Remove stored token
        // default: Run `claude setup-token` and store OAuth token
    }
}
```

### `clave sessions` (not yet implemented)

```php
class SessionsCommand extends Command
{
    protected $signature = 'sessions';

    public function handle(): int
    {
        $sessions = Session::all();

        $this->table(
            ['ID', 'Project', 'Branch', 'VM', 'Started'],
            $sessions->map(fn ($s) => [
                $s->session_id,
                $s->project_name,
                $s->branch ?? '-',
                $s->vm_name ?? '-',
                $s->started_at?->diffForHumans() ?? '-',
            ]),
        );
    }
}
```

### `clave cleanup` (not yet implemented)

```php
class CleanupCommand extends Command
{
    protected $signature = 'cleanup {--dry-run : Show what would be cleaned up}';

    public function handle(TartManager $tart): int
    {
        // Find VMs named clave-* (excluding clave-base)
        // Check if they have a matching active session
        // Remove orphaned VMs and stale session records
    }
}
```

---

## Provisioning

The base image provisioning installs everything needed so per-session boot is fast. The VM runs **nginx and PHP-FPM only** — no MySQL or Redis (v0 uses host services).

`ProvisioningPipeline` generates a self-contained bash script via `toScript()` which is mounted into the VM and executed. This is faster than step-by-step SSH execution.

**Provisioned software:**
- Base system packages (git, curl, wget, unzip)
- PHP 8.4 + extensions (curl, mbstring, xml, mysql, redis, sqlite3, bcmath, gd, intl)
- PHP-FPM pool configuration
- Composer
- Nginx with Laravel site config
- Node.js 22 (via NodeSource)
- Claude Code CLI (`@anthropic-ai/claude-code`)
- Laravel directories (`/srv/project`)
- VirtioFS fstab entry (`com.apple.virtio-fs.automount /srv/project virtiofs`)

---

## Service Classes

### TartManager

```php
class TartManager
{
    public function clone(string $source, string $name): void;
    public function runBackground(string $name, array $dirs, bool $no_graphics = true): mixed;
    public function stop(string $name): void;
    public function delete(string $name): void;
    public function ip(string $name, int $timeout = 0): ?string;
    public function exists(string $name): bool;
    public function list(): Collection;
    public function set(string $name, ?int $cpus, ?int $memory, ?string $display): void;
    public function randomizeMac(string $name): void;
    public function rename(string $old_name, string $new_name): void;
    public function waitForReady(string $name, SshExecutor $ssh, int $timeout): string;
}
```

### GitManager

```php
class GitManager
{
    public function isRepo(string $path): bool;
    public function currentBranch(string $path): string;
    public function cloneLocal(string $repo_path, string $clone_path, string $branch): void;
    public function removeClone(string $repo_path, string $clone_path): void;
    public function mergeAndCleanClone(string $repo_path, string $clone_path, string $branch, string $target): void;
    public function ensureIgnored(string $repo_path, string $pattern): void;
}
```

### SshExecutor

Uses password auth via `SSH_ASKPASS` for all VM connections. VMs are ephemeral and local, so key management adds complexity with no security benefit.

```php
class SshExecutor
{
    public function setHost(string $host): self;
    public function usePassword(string $password): self;
    public function run(string $command, int $timeout = 60): mixed;
    public function interactive(string $command): int;
    public function tunnel(int $local_port, string $remote_host, int $remote_port): mixed;
    public function test(): bool;
    public function lastError(): ?string;
}
```

### AuthManager

Resolves Claude authentication from multiple sources with priority chain: `ANTHROPIC_API_KEY` env → `CLAUDE_CODE_OAUTH_TOKEN` env → stored token file.

```php
class AuthManager
{
    public function resolve(): ?array;
    public function hasAuth(): bool;
    public function setupToken(): bool;
    public function clearToken(): void;
    public function statusInfo(): array;
}
```

### HerdManager

```php
class HerdManager
{
    public function proxy(string $domain, string $target, bool $secure = true): void;
    public function unproxy(string $domain): void;
}
```

---

## Configuration

### config/clave.php

```php
return [
    'base_image' => env('CLAVE_BASE_IMAGE', 'ghcr.io/cirruslabs/ubuntu:latest'),
    'base_vm' => env('CLAVE_BASE_VM', 'clave-base'),

    'vm' => [
        'cpus' => env('CLAVE_VM_CPUS', 4),
        'memory' => env('CLAVE_VM_MEMORY', 8192),
        'display' => env('CLAVE_VM_DISPLAY', 'none'),
    ],

    'ssh' => [
        'user' => env('CLAVE_SSH_USER', 'admin'),
        'port' => env('CLAVE_SSH_PORT', 22),
        'password' => env('CLAVE_SSH_PASSWORD', 'admin'),
        'options' => [
            'StrictHostKeyChecking' => 'no',
            'UserKnownHostsFile' => '/dev/null',
            'LogLevel' => 'ERROR',
            'ConnectTimeout' => '5',
        ],
    ],

    'anthropic_api_key' => env('ANTHROPIC_API_KEY'),
    'oauth_token' => env('CLAUDE_CODE_OAUTH_TOKEN'),
    'auth_file' => env('CLAVE_AUTH_FILE', '~/.config/clave/auth.json'),
];
```

### Environment variables

```bash
export ANTHROPIC_API_KEY=sk-ant-...        # API key auth
export CLAUDE_CODE_OAUTH_TOKEN=...          # OAuth token auth (alternative)
export CLAVE_VM_CPUS=4                      # Optional override
export CLAVE_VM_MEMORY=8192                 # Optional override
```

---

## Directory Structure

```
clave/
├── app/
│   ├── Commands/
│   │   ├── DefaultCommand.php
│   │   ├── ProvisionCommand.php
│   │   ├── AuthCommand.php
│   │   └── LintCommand.php
│   ├── Dto/
│   │   ├── SessionContext.php
│   │   ├── ServiceConfig.php
│   │   └── OnExit.php
│   ├── Exceptions/
│   │   └── AbortedPipelineException.php
│   ├── Models/
│   │   └── Session.php
│   ├── Pipelines/
│   │   ├── Steps/
│   │   │   ├── Step.php                    (interface)
│   │   │   ├── ProgressAware.php           (interface)
│   │   │   ├── AcceptsProgress.php         (trait)
│   │   │   ├── ValidateProject.php
│   │   │   ├── GetGitBranch.php
│   │   │   ├── EnsureVmExists.php
│   │   │   ├── CheckClaudeAuthentication.php
│   │   │   ├── SaveSession.php
│   │   │   ├── CloneRepo.php
│   │   │   ├── CloneVm.php
│   │   │   ├── BootVm.php
│   │   │   └── RunClaudeCode.php
│   │   ├── SessionPipeline.php             (abstract base)
│   │   ├── PreflightPipeline.php
│   │   └── ClaudeCodePipeline.php
│   ├── Support/
│   │   ├── TartManager.php
│   │   ├── GitManager.php
│   │   ├── SshExecutor.php
│   │   ├── AuthManager.php
│   │   ├── HerdManager.php
│   │   ├── SessionTeardown.php
│   │   ├── ProvisioningPipeline.php
│   │   └── EnumHelpers.php
│   └── Providers/
│       └── AppServiceProvider.php
├── config/
│   └── clave.php
├── database/
│   └── migrations/
│       └── 2026_02_23_000000_create_sessions_table.php
├── tests/
│   ├── Feature/
│   │   ├── Commands/
│   │   │   └── AuthCommandTest.php
│   │   └── Services/
│   │       ├── AuthManagerTest.php
│   │       └── TartManagerTest.php
│   └── Unit/
│       └── Dto/
│           ├── SessionContextTest.php
│           └── ServiceConfigTest.php
└── clave
```

---

## Implementation Order

### Sprint 1 — Boot Loop (COMPLETE)

Proves the core lifecycle: boot a VM, get a Claude Code session, tear it down.

1. Scaffold Laravel Zero project, install database/dotenv components
2. `SessionContext`, `ServiceConfig`, and `OnExit` DTOs
3. `TartManager` — clone, run, stop, delete, ip, exists, randomizeMac, rename, set
4. `SshExecutor` — run, interactive, tunnel, test (password auth via SSH_ASKPASS)
5. `AuthManager` + `AuthCommand` — API key + OAuth support
6. `ProvisionCommand` + `ProvisioningPipeline` — build base image (PHP 8.4/nginx/Node 22/Claude Code)
7. `SessionPipeline` base class with progress tracking
8. Preflight pipeline: `ValidateProject → GetGitBranch → EnsureVmExists → CheckClaudeAuthentication → SaveSession`
9. Session pipeline: `CloneRepo → CloneVm → BootVm → RunClaudeCode`
10. `SessionTeardown` — cleanup VM, worktree prompt, session record
11. Signal handling via `$this->trap()` (SIGINT/SIGTERM)
12. Sessions SQLite table + model

**Milestone:** `clave` boots a VM from a Laravel project dir, you get a Claude Code prompt in a worktree, exiting shuts it all down.

### Sprint 2 — Networking + Herd

Connect the VM to host services and expose the app.

1. `DiscoverGateway` step — find NAT gateway IP
2. `BootstrapLaravel` step — symlink, .env patching with host service IPs, composer install, migrate
3. `CreateSshTunnel` step — port forwarding VM:80 → localhost:port
4. `ConfigureHerdProxy` step — `herd proxy` setup/teardown
5. Port auto-assignment (scan 8081–8199)
6. Host service connectivity check (warn if MySQL/Redis not reachable from VM)

**Milestone:** App accessible at `https://project-a1b2.test`, database works against host MySQL.

### Sprint 3 — Polish

1. `SessionsCommand` and `CleanupCommand`
2. `ConfigCommand`
3. `GitManager` — verify merge and discard worktree flows end-to-end
4. `--resume` flag
5. Error handling: composer failures, SSH timeouts, missing host services

**Milestone:** Handles crashes, parallel sessions, cleans up after itself.

### Sprint 4 — Distribution + Future

1. Build PHAR: `php clave app:build`
2. Per-project `.clave.json` config
3. `clave provision --update`
4. In-VM services mode (`services.mode: "vm"`)
5. `clave exec` and `clave ssh` for running sessions

---

## Open Questions

1. **Host MySQL/Redis bind address.** Herd Pro defaults to `127.0.0.1`. VMs need services on `0.0.0.0` or the gateway interface. Clave should check reachability during `DiscoverGateway` and print actionable instructions if services aren't accessible. This is a one-time user configuration step.

2. **`tart run` process management.** Need to verify `Process::start()` keeps the VM alive while the parent blocks on TTY. May need `nohup` with PID file as fallback.

3. **Composer install on VirtioFS.** v0 runs directly on the mount. If too slow, install on VM local disk and symlink `vendor/` back.
