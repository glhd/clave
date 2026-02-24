# Clave — Implementation Plan

A Laravel Zero CLI that spins up ephemeral Ubuntu VMs via Tart for isolated Claude Code sessions against Laravel projects, with Herd Pro integration on the host.

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

Multiple simultaneous sessions work naturally — each `clave` invocation gets its own worktree, its own VM clone, and its own port. Run three terminals, run `clave` three times, get three isolated
Claude Code agents working in parallel on different branches.

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
│ /project (vfs)  │  │ /project (vfs)  │  │ /project (vfs)  │
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

For v0, **MySQL and Redis run on the host via Herd Pro.** Each VM connects to host services through the NAT gateway IP (typically `192.168.64.1`). This means all sessions share the same database and
Redis instance — which is fine for most development workflows and avoids duplicating heavy services in every ephemeral VM.

The VM runs only nginx and PHP-FPM — just enough to serve the Laravel app and provide Claude Code a realistic execution environment for running artisan commands, tests, etc.

> **Future: in-VM services.** The architecture is designed so that a future version can optionally provision MySQL and Redis inside the VM for full session isolation. This is controlled by the
`ServiceConfig` DTO — swapping from `ServiceConfig::hostServices()` to `ServiceConfig::localServices()` is the only change needed in the pipeline. The provisioning pipeline would gain additional steps
for MySQL/Redis, gated by a config flag.

### Host Service Requirements

Herd Pro's MySQL and Redis must be reachable from the VM's network. By default:

- **MySQL**: Herd Pro binds to `127.0.0.1:3306`. This needs to be changed to `0.0.0.0:3306` (or the gateway IP) for VMs to connect. Clave should detect this and warn on first run.
- **Redis**: Same situation — Herd Pro's Redis binds to `127.0.0.1:6379` and needs to accept connections from the VM subnet.

The NAT gateway IP is discovered by running `ip route | grep default` inside the VM.

---

## Git Worktree Strategy

A git repo is **required**. If the current directory is not inside a git repository, `clave` exits with an error.

When `clave` starts, it creates a worktree so each session has an isolated copy of the codebase. Claude Code in session A can be refactoring the auth system while session B rewrites the billing
module — no conflicts.

```
my-app/                          # main working copy (untouched)
my-app/.clave/wt/s-a1b2/        # session A's worktree
my-app/.clave/wt/s-c3d4/        # session B's worktree
```

**Worktree lifecycle:**

1. Generate a short session ID (first 4 chars of a UUID)
2. Create a new branch: `clave/s-{id}` from current HEAD (or `--branch`)
3. `git worktree add .clave/wt/s-{id} clave/s-{id}`
4. Mount `.clave/wt/s-{id}` into the VM via VirtioFS
5. On session exit, prompt:
    - **Keep** (default) — leave the worktree and branch for manual review
    - **Merge** — merge the session branch back to the original branch, remove worktree
    - **Discard** — remove worktree and delete branch

`.clave/` is automatically added to `.gitignore` on first use.

---

## Session Pipeline

The session lifecycle is implemented as a Laravel pipeline. A `SessionContext` DTO is created at the start and flows through each stage, accumulating state. Each stage is responsible for one concern.

### SessionContext DTO

```php
class SessionContext
{
    public function __construct(
        // Determined at creation
        public readonly string $session_id,
        public readonly string $project_name,
        public readonly string $project_dir,
        public readonly string $base_branch,

        // Populated by pipeline stages
        public ?string $worktree_path = null,
        public ?string $session_branch = null,
        public ?string $vm_name = null,
        public ?int $vm_pid = null,
        public ?string $vm_ip = null,
        public ?string $gateway_ip = null,
        public ?int $host_port = null,
        public ?string $proxy_name = null,
        public ?object $tunnel_process = null,
        public ?int $claude_exit_code = null,

        // Options passed through from CLI
        public ?string $prompt = null,
        public ?string $branch_from = null,
        public ?int $cpus = null,
        public ?int $memory_mb = null,
        public ?int $port = null,
        public bool $skip_proxy = false,
        public ?string $on_exit = null, // 'keep', 'merge', 'discard', or null (prompt)

        // Service connection details (abstracted for future in-VM support)
        public ?ServiceConfig $services = null,
    ) {}
}
```

### ServiceConfig DTO

```php
class ServiceConfig
{
    public function __construct(
        public readonly string $db_host,
        public readonly int $db_port,
        public readonly string $db_database,
        public readonly string $db_username,
        public readonly string $db_password,
        public readonly string $redis_host,
        public readonly int $redis_port,
    ) {}

    /**
     * v0: connect to host services via NAT gateway.
     */
    public static function hostServices(string $gateway_ip): static
    {
        return new static(
            db_host: $gateway_ip,
            db_port: 3306,
            db_database: 'laravel',
            db_username: 'root',
            db_password: '',
            redis_host: $gateway_ip,
            redis_port: 6379,
        );
    }

    /**
     * Future: services running inside the VM.
     */
    public static function localServices(): static
    {
        return new static(
            db_host: '127.0.0.1',
            db_port: 3306,
            db_database: 'laravel',
            db_username: 'clave',
            db_password: 'clave',
            redis_host: '127.0.0.1',
            redis_port: 6379,
        );
    }
}
```

### Pipeline Orchestration

```php
// In DefaultCommand::handle():

$context = new SessionContext(
    session_id: substr(Str::uuid()->toString(), 0, 4),
    project_name: basename(getcwd()),
    project_dir: getcwd(),
    base_branch: $git->currentBranch(),
    prompt: $this->argument('prompt'),
    branch_from: $this->option('branch'),
    cpus: $this->option('cpus') ? (int) $this->option('cpus') : null,
    memory_mb: $this->option('memory') ? (int) $this->option('memory') : null,
    port: $this->option('port') ? (int) $this->option('port') : null,
    skip_proxy: (bool) $this->option('no-proxy'),
    on_exit: match (true) {
        (bool) $this->option('keep') => 'keep',
        (bool) $this->option('discard') => 'discard',
        default => null,
    },
);

try {
    app(Pipeline::class)
        ->send($context)
        ->through([
            CreateWorktree::class,
            CloneVm::class,
            BootVm::class,
            DiscoverGateway::class,
            CreateSshTunnel::class,
            ConfigureHerdProxy::class,
            BootstrapLaravel::class,
            RunClaudeCode::class,
        ])
        ->then(fn (SessionContext $ctx) => $ctx);
} finally {
    $teardown->teardown($context);
}
```

### Pipeline Stages

Each stage implements Laravel's pipeline contract:

```php
class CreateWorktree
{
    public function __construct(
        protected GitManager $git,
    ) {}

    public function handle(SessionContext $context, Closure $next): mixed
    {
        $branch = "clave/s-{$context->session_id}";
        $worktree_path = "{$context->project_dir}/.clave/wt/s-{$context->session_id}";
        $base = $context->branch_from ?? $context->base_branch;

        $this->git->ensureIgnored($context->project_dir, '.clave/');
        $this->git->createWorktree($worktree_path, $branch, $base);

        $context->worktree_path = $worktree_path;
        $context->session_branch = $branch;

        return $next($context);
    }
}
```

```php
class CloneVm
{
    public function __construct(
        protected TartManager $tart,
    ) {}

    public function handle(SessionContext $context, Closure $next): mixed
    {
        $vm_name = "clave-{$context->session_id}";

        $this->tart->clone('clave-base', $vm_name);

        if ($context->cpus) {
            $this->tart->set($vm_name, cpus: $context->cpus);
        }
        if ($context->memory_mb) {
            $this->tart->set($vm_name, memory_mb: $context->memory_mb);
        }

        $context->vm_name = $vm_name;

        return $next($context);
    }
}
```

```php
class BootVm
{
    public function __construct(
        protected TartManager $tart,
    ) {}

    public function handle(SessionContext $context, Closure $next): mixed
    {
        $mount_path = $context->worktree_path ?? $context->project_dir;

        $vm_process = $this->tart->runBackground($context->vm_name, [
            'project' => $mount_path,
        ]);

        $context->vm_pid = $vm_process->id();
        $context->vm_ip = $this->tart->waitForReady($context->vm_name, timeout_seconds: 90);

        return $next($context);
    }
}
```

```php
class DiscoverGateway
{
    public function __construct(
        protected SshExecutor $ssh,
    ) {}

    public function handle(SessionContext $context, Closure $next): mixed
    {
        $this->ssh->setHost($context->vm_ip);

        $result = $this->ssh->run("ip route | grep default | awk '{print \$3}'");
        $gateway_ip = trim($result->output());

        if (empty($gateway_ip)) {
            throw new RuntimeException('Could not discover NAT gateway IP');
        }

        $context->gateway_ip = $gateway_ip;
        $context->services = ServiceConfig::hostServices($gateway_ip);

        return $next($context);
    }
}
```

```php
class CreateSshTunnel
{
    public function __construct(
        protected SshExecutor $ssh,
    ) {}

    public function handle(SessionContext $context, Closure $next): mixed
    {
        $host_port = $context->port ?? $this->findAvailablePort(8081, 8199);
        $context->host_port = $host_port;

        $context->tunnel_process = $this->ssh->tunnel(
            host: $context->vm_ip,
            local_port: $host_port,
            remote_port: 80,
        );

        return $next($context);
    }

    protected function findAvailablePort(int $start, int $end): int
    {
        for ($port = $start; $port <= $end; $port++) {
            $conn = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
            if (! $conn) {
                return $port;
            }
            fclose($conn);
        }

        throw new RuntimeException("No available ports in range {$start}-{$end}");
    }
}
```

```php
class ConfigureHerdProxy
{
    public function __construct(
        protected HerdManager $herd,
    ) {}

    public function handle(SessionContext $context, Closure $next): mixed
    {
        if ($context->skip_proxy) {
            return $next($context);
        }

        $proxy_name = "{$context->project_name}-{$context->session_id}";
        $context->proxy_name = $proxy_name;

        $this->herd->proxy(
            $proxy_name,
            "http://127.0.0.1:{$context->host_port}",
            secure: true,
        );

        return $next($context);
    }
}
```

```php
class BootstrapLaravel
{
    public function __construct(
        protected SshExecutor $ssh,
    ) {}

    public function handle(SessionContext $context, Closure $next): mixed
    {
        $this->ssh->setHost($context->vm_ip);
        $guest_project = '/mnt/project';

        // Symlink VirtioFS mount to where nginx expects it
        $this->ssh->run("sudo ln -sfn {$guest_project} /var/www/current");

        // Set up .env with host service connections
        $this->configureEnv($context, $guest_project);

        // Install dependencies
        $this->ssh->run(
            "cd /var/www/current && sudo -u www-data composer install --no-interaction 2>&1",
            timeout: 300,
        );

        // Laravel bootstrap
        $this->ssh->run(
            "cd /var/www/current && sudo -u www-data php artisan migrate --force 2>&1",
            timeout: 120,
        );

        // Restart services
        $this->ssh->run("sudo systemctl restart php8.3-fpm nginx");

        return $next($context);
    }

    protected function configureEnv(SessionContext $context, string $guest_project): void
    {
        $services = $context->services;

        // Copy .env.example if no .env exists
        $this->ssh->run(
            "test -f {$guest_project}/.env || cp {$guest_project}/.env.example {$guest_project}/.env"
        );

        // Generate app key if placeholder
        $this->ssh->run(
            "cd {$guest_project} && grep -q '^APP_KEY=$' .env && sudo -u www-data php artisan key:generate"
        );

        // Patch service connections to point at host
        $env_overrides = [
            'DB_HOST' => $services->db_host,
            'DB_PORT' => $services->db_port,
            'DB_DATABASE' => $services->db_database,
            'DB_USERNAME' => $services->db_username,
            'DB_PASSWORD' => $services->db_password,
            'REDIS_HOST' => $services->redis_host,
            'REDIS_PORT' => $services->redis_port,
            'CACHE_STORE' => 'redis',
            'SESSION_DRIVER' => 'redis',
            'QUEUE_CONNECTION' => 'sync',
            'MAIL_MAILER' => 'log',
        ];

        foreach ($env_overrides as $key => $value) {
            $this->ssh->run(
                "cd {$guest_project} && " .
                "grep -q '^{$key}=' .env " .
                "&& sed -i 's/^{$key}=.*/{$key}={$value}/' .env " .
                "|| echo '{$key}={$value}' >> .env"
            );
        }
    }
}
```

```php
class RunClaudeCode
{
    public function __construct(
        protected SshExecutor $ssh,
    ) {}

    public function handle(SessionContext $context, Closure $next): mixed
    {
        $this->ssh->setHost($context->vm_ip);
        $api_key = config('clave.anthropic_api_key') ?? env('ANTHROPIC_API_KEY');

        // Print access info before starting session
        $url = $context->proxy_name
            ? "https://{$context->proxy_name}.test"
            : "http://127.0.0.1:{$context->host_port}";

        app('writer')->writeln('');
        app('writer')->writeln("  ✓ App running at {$url}");
        app('writer')->writeln("  ✓ VM: {$context->vm_ip} | Port: {$context->host_port}");
        app('writer')->writeln("  ✓ Session: {$context->session_id} | Branch: {$context->session_branch}");
        app('writer')->writeln('');

        $claude_cmd = implode(' ', array_filter([
            'cd /var/www/current',
            '&&',
            "ANTHROPIC_API_KEY={$api_key}",
            'claude',
            $context->prompt ? '-p ' . escapeshellarg($context->prompt) : null,
            '--dangerously-skip-permissions',
        ]));

        $context->claude_exit_code = $this->ssh->interactive($claude_cmd);

        return $next($context);
    }
}
```

---

## Session Teardown

Cleanup runs after the pipeline completes (or on failure/interrupt via signal handler). Each step is wrapped in `rescue()` so a failure in one doesn't prevent the others.

```php
class SessionTeardown
{
    public function __construct(
        protected TartManager $tart,
        protected HerdManager $herd,
        protected GitManager $git,
    ) {}

    public function teardown(SessionContext $context, ?Command $command = null): void
    {
        // Remove Herd proxy
        if ($context->proxy_name) {
            rescue(fn () => $this->herd->unproxy($context->proxy_name));
        }

        // Kill SSH tunnel
        if ($context->tunnel_process) {
            rescue(fn () => Process::run("kill {$context->tunnel_process->id()}"));
        }

        // Stop and delete VM
        if ($context->vm_name) {
            rescue(fn () => $this->tart->stop($context->vm_name));
            rescue(fn () => $this->tart->delete($context->vm_name));
        }

        // Handle worktree
        if ($context->worktree_path && $context->session_branch) {
            $this->handleWorktree($context, $command);
        }

        // Remove session record
        SessionModel::where('session_id', $context->session_id)->delete();
    }

    protected function handleWorktree(SessionContext $context, ?Command $command): void
    {
        if ($context->on_exit) {
            $action = $context->on_exit;
        } elseif ($command) {
            $action = $command->choice(
                "Session branch {$context->session_branch}",
                ['keep', 'merge', 'discard'],
                'keep',
            );
        } else {
            $action = 'keep'; // fallback if no interactive prompt available
        }

        match ($action) {
            'merge' => $this->git->mergeAndCleanWorktree(
                $context->worktree_path,
                $context->session_branch,
                $context->base_branch,
            ),
            'discard' => $this->git->removeWorktree(
                $context->worktree_path,
                $context->session_branch,
                force: true,
            ),
            'keep' => null, // leave it alone
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
    protected $signature = 'clave
        {prompt? : One-shot prompt for Claude Code}
        {--branch= : Base the worktree on a specific branch}
        {--port= : Specify host port (default: auto-assign)}
        {--cpus= : Override CPU count for this session}
        {--memory= : Override memory in MB for this session}
        {--no-proxy : Skip Herd proxy setup}
        {--keep : Keep worktree on exit without prompting}
        {--discard : Discard worktree on exit without prompting}';

    public function handle(
        TartManager $tart,
        GitManager $git,
        SessionTeardown $teardown,
    ): int {
        // Preflight checks
        if (! file_exists(getcwd() . '/artisan')) {
            $this->error('Not a Laravel project (no artisan file found).');
            return 1;
        }

        if (! $git->isRepo(getcwd())) {
            $this->error('Not a git repository. Clave requires git for worktree isolation.');
            return 1;
        }

        if (! $tart->exists('clave-base')) {
            $this->info('No base image found. Running initial provisioning...');
            $this->call('provision');
        }

        // Build context DTO
        $context = new SessionContext(
            session_id: substr(Str::uuid()->toString(), 0, 4),
            project_name: basename(getcwd()),
            project_dir: getcwd(),
            base_branch: $git->currentBranch(),
            prompt: $this->argument('prompt'),
            branch_from: $this->option('branch'),
            cpus: $this->option('cpus') ? (int) $this->option('cpus') : null,
            memory_mb: $this->option('memory') ? (int) $this->option('memory') : null,
            port: $this->option('port') ? (int) $this->option('port') : null,
            skip_proxy: (bool) $this->option('no-proxy'),
            on_exit: match (true) {
                (bool) $this->option('keep') => 'keep',
                (bool) $this->option('discard') => 'discard',
                default => null,
            },
        );

        // Register cleanup on interrupt
        $this->registerSignalHandlers($context, $teardown);

        // Track session
        SessionModel::create([
            'session_id' => $context->session_id,
            'project_dir' => $context->project_dir,
            'started_at' => now(),
        ]);

        try {
            app(Pipeline::class)
                ->send($context)
                ->through([
                    CreateWorktree::class,
                    CloneVm::class,
                    BootVm::class,
                    DiscoverGateway::class,
                    CreateSshTunnel::class,
                    ConfigureHerdProxy::class,
                    BootstrapLaravel::class,
                    RunClaudeCode::class,
                ])
                ->then(fn (SessionContext $ctx) => $ctx);
        } finally {
            $teardown->teardown($context, $this);
        }

        return $context->claude_exit_code ?? 0;
    }

    protected function registerSignalHandlers(SessionContext $context, SessionTeardown $teardown): void
    {
        $handler = function () use ($context, $teardown) {
            $this->newLine();
            $this->info('Interrupted — cleaning up...');
            $teardown->teardown($context);
            exit(130);
        };

        pcntl_signal(SIGINT, $handler);
        pcntl_signal(SIGTERM, $handler);
    }
}
```

### `clave provision`

Builds or rebuilds the base VM image.

```php
class ProvisionCommand extends Command
{
    protected $signature = 'provision
        {--force : Rebuild even if base image exists}
        {--image= : Override base OCI image}';

    public function handle(TartManager $tart, SshExecutor $ssh): int
    {
        $base_image = $this->option('image') ?? config('clave.vm.base_image');

        if ($tart->exists('clave-base') && ! $this->option('force')) {
            $this->info('Base image already exists. Use --force to rebuild.');
            return 0;
        }

        if ($tart->exists('clave-base')) {
            $tart->delete('clave-base');
        }

        $this->info("Pulling {$base_image}...");
        $tart->clone($base_image, 'clave-base');
        $tart->set('clave-base',
            cpus: config('clave.vm.cpus', 4),
            memory_mb: config('clave.vm.memory_mb', 8192),
            disk_gb: config('clave.vm.disk_gb', 32),
        );

        // Boot for provisioning (no directory mounts needed)
        $vm_process = $tart->runBackground('clave-base', []);
        $vm_ip = $tart->waitForReady('clave-base', timeout_seconds: 120);

        $ssh->setHost($vm_ip);

        foreach (ProvisioningPipeline::steps() as $step) {
            $this->info("  → {$step['name']}...");

            if ($ssh->test($step['test'])) {
                $this->comment("    (skipped)");
                continue;
            }

            foreach ($step['commands'] as $command) {
                $result = $ssh->run($command, timeout: 300);
                if (! $result->successful()) {
                    $this->error("    Failed: {$result->errorOutput()}");
                    $tart->stop('clave-base');
                    return 1;
                }
            }

            $this->info("    ✓");
        }

        $this->injectSshKey($ssh);

        $tart->stop('clave-base');
        $this->info('Base image provisioned successfully.');

        return 0;
    }

    protected function injectSshKey(SshExecutor $ssh): void
    {
        $key_dir = $_SERVER['HOME'] . '/.config/clave/ssh';

        if (! file_exists("{$key_dir}/id_ed25519")) {
            @mkdir($key_dir, 0700, true);
            Process::run("ssh-keygen -t ed25519 -f {$key_dir}/id_ed25519 -N ''")->throw();
        }

        $pub_key = trim(file_get_contents("{$key_dir}/id_ed25519.pub"));
        $ssh->run("echo '{$pub_key}' >> ~/.ssh/authorized_keys");
    }
}
```

### `clave sessions`

```php
class SessionsCommand extends Command
{
    protected $signature = 'sessions';

    public function handle(): int
    {
        $sessions = SessionModel::all();

        if ($sessions->isEmpty()) {
            $this->info('No active sessions.');
            return 0;
        }

        $this->table(
            ['ID', 'Project', 'Branch', 'Port', 'URL', 'Started'],
            $sessions->map(fn ($s) => [
                $s->session_id,
                basename($s->project_dir),
                $s->branch ?? '-',
                $s->port ?? '-',
                $s->proxy_name ? "https://{$s->proxy_name}.test" : '-',
                $s->started_at?->diffForHumans() ?? '-',
            ]),
        );

        return 0;
    }
}
```

### `clave cleanup`

```php
class CleanupCommand extends Command
{
    protected $signature = 'cleanup {--dry-run : Show what would be cleaned up}';

    public function handle(TartManager $tart): int
    {
        $dry_run = $this->option('dry-run');
        $cleaned = 0;

        $vms = $tart->list()->filter(
            fn ($vm) => str_starts_with($vm['Name'], 'clave-') && $vm['Name'] !== 'clave-base'
        );

        foreach ($vms as $vm) {
            $session_id = Str::after($vm['Name'], 'clave-');
            $session = SessionModel::where('session_id', $session_id)->first();

            $is_orphan = ! $session || ! $this->isProcessRunning($session->pid);

            if ($is_orphan) {
                $label = $dry_run ? 'Would remove' : 'Removing';
                $this->line("{$label} VM: {$vm['Name']}");

                if (! $dry_run) {
                    rescue(fn () => $tart->stop($vm['Name']));
                    rescue(fn () => $tart->delete($vm['Name']));
                    $session?->delete();
                }
                $cleaned++;
            }
        }

        $verb = $dry_run ? 'would be cleaned' : 'cleaned';
        $this->info("{$cleaned} resources {$verb}.");

        return 0;
    }

    protected function isProcessRunning(?int $pid): bool
    {
        if (! $pid) {
            return false;
        }
        return Process::run("kill -0 {$pid} 2>/dev/null")->successful();
    }
}
```

---

## Provisioning

The base image provisioning installs everything needed so per-session boot is fast. The VM runs **nginx and PHP-FPM only** — no MySQL or Redis (v0 uses host services).

```php
class ProvisioningPipeline
{
    public static function steps(): array
    {
        return [
            static::baseSystem(),
            static::php(),
            static::nginx(),
            static::node(),
            static::claudeCode(),
            static::laravelDirectories(),
            static::virtiofsMounts(),
            static::sshKeys(),
        ];
    }

    protected static function baseSystem(): array
    {
        return [
            'name' => 'Base system packages',
            'test' => 'dpkg -l git curl wget unzip 2>/dev/null | grep -c "^ii" | grep -q 4',
            'commands' => [
                'sudo DEBIAN_FRONTEND=noninteractive apt-get update',
                'sudo DEBIAN_FRONTEND=noninteractive apt-get upgrade -y',
                'sudo DEBIAN_FRONTEND=noninteractive apt-get install -y git curl wget unzip software-properties-common',
                'sudo timedatectl set-timezone UTC',
            ],
        ];
    }

    protected static function php(): array
    {
        return [
            'name' => 'PHP 8.3 + extensions',
            'test' => 'php -v 2>/dev/null | grep -q "8.3"',
            'commands' => [
                'sudo add-apt-repository -y ppa:ondrej/php',
                'sudo apt-get update',
                'sudo DEBIAN_FRONTEND=noninteractive apt-get install -y ' .
                    'php8.3 php8.3-fpm php8.3-opcache php8.3-mysql php8.3-mbstring ' .
                    'php8.3-bcmath php8.3-xml php8.3-gd php8.3-intl php8.3-zip ' .
                    'php8.3-imagick php8.3-redis php8.3-curl php8.3-gmp',
                'sudo tee /etc/php/8.3/fpm/pool.d/www.conf > /dev/null << \'POOL\'
[www]
user = www-data
group = www-data
listen = /run/php/php8.3-fpm.sock
listen.owner = www-data
listen.group = www-data
pm = dynamic
pm.max_children = 10
pm.start_servers = 3
pm.min_spare_servers = 2
pm.max_spare_servers = 5
pm.max_requests = 500
POOL',
                'sudo sed -i "s/^upload_max_filesize.*/upload_max_filesize = 20M/" /etc/php/8.3/fpm/php.ini',
                'sudo sed -i "s/^post_max_size.*/post_max_size = 20M/" /etc/php/8.3/fpm/php.ini',
                'sudo systemctl enable php8.3-fpm',
                'curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer',
            ],
        ];
    }

    protected static function nginx(): array
    {
        return [
            'name' => 'Nginx',
            'test' => 'nginx -v 2>&1 | grep -q nginx',
            'commands' => [
                'sudo DEBIAN_FRONTEND=noninteractive apt-get install -y nginx',
                'echo "upstream php-fpm { server unix:/run/php/php8.3-fpm.sock; }" | sudo tee /etc/nginx/conf.d/php-fpm.conf',
                'sudo tee /etc/nginx/conf.d/gzip.conf > /dev/null << \'GZIP\'
gzip_comp_level 5;
gzip_min_length 256;
gzip_proxied any;
gzip_vary on;
gzip_types application/javascript application/json text/css text/plain image/svg+xml;
GZIP',
                'echo "server_tokens off;" | sudo tee /etc/nginx/conf.d/security.conf',
                'sudo tee /etc/nginx/sites-available/laravel > /dev/null << \'SITE\'
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name _;
    index index.php;
    root /var/www/current/public;
    charset utf-8;
    client_max_body_size 20M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    location ~ \\.php$ {
        fastcgi_intercept_errors off;
        fastcgi_split_path_info ^(.+\\.php)(/.+)$;
        fastcgi_pass php-fpm;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param PATH_INFO $fastcgi_path_info;
        include fastcgi_params;
    }

    location ~ /\\.(?!well-known).* { deny all; }
}
SITE',
                'sudo rm -f /etc/nginx/sites-enabled/default',
                'sudo ln -sf /etc/nginx/sites-available/laravel /etc/nginx/sites-enabled/laravel',
                'sudo systemctl enable nginx',
            ],
        ];
    }

    protected static function node(): array
    {
        return [
            'name' => 'Node.js + npm',
            'test' => 'node --version 2>/dev/null | grep -q v',
            'commands' => [
                'sudo DEBIAN_FRONTEND=noninteractive apt-get install -y nodejs npm',
            ],
        ];
    }

    protected static function claudeCode(): array
    {
        return [
            'name' => 'Claude Code',
            'test' => 'claude --version 2>/dev/null',
            'commands' => [
                'sudo npm install -g @anthropic-ai/claude-code',
            ],
        ];
    }

    protected static function laravelDirectories(): array
    {
        return [
            'name' => 'Laravel directory structure',
            'test' => 'test -d /var/www/storage',
            'commands' => [
                'sudo mkdir -p /var/www/storage/{app/public,framework/{cache/data,sessions,views},logs}',
                'sudo touch /var/www/storage/logs/laravel.log',
                'sudo chown -R www-data:www-data /var/www',
                'sudo chmod -R 0775 /var/www/storage',
            ],
        ];
    }

    protected static function virtiofsMounts(): array
    {
        return [
            'name' => 'VirtioFS mount point',
            'test' => 'test -d /mnt/project',
            'commands' => [
                'sudo mkdir -p /mnt/project',
                'grep -q "project" /etc/fstab || echo "project /mnt/project virtiofs rw,nofail 0 0" | sudo tee -a /etc/fstab',
            ],
        ];
    }

    protected static function sshKeys(): array
    {
        return [
            'name' => 'SSH directory',
            'test' => 'test -d ~/.ssh',
            'commands' => [
                'mkdir -p ~/.ssh',
                'chmod 700 ~/.ssh',
                'touch ~/.ssh/authorized_keys',
                'chmod 600 ~/.ssh/authorized_keys',
            ],
        ];
    }
}
```

---

## Service Classes

### TartManager

```php
class TartManager
{
    public function clone(string $source, string $target): void
    {
        Process::run("tart clone {$source} {$target}")->throw();
    }

    public function runBackground(string $name, array $dirs): InvokedProcess
    {
        $dir_flags = collect($dirs)
            ->map(fn ($path, $label) => "--dir={$label}:{$path}")
            ->implode(' ');

        return Process::start("tart run {$name} --no-graphics {$dir_flags}");
    }

    public function stop(string $name): void
    {
        Process::run("tart stop {$name}");
    }

    public function delete(string $name): void
    {
        Process::run("tart delete {$name}");
    }

    public function ip(string $name): ?string
    {
        $result = Process::run("tart ip {$name}");
        return $result->successful() ? trim($result->output()) : null;
    }

    public function exists(string $name): bool
    {
        $result = Process::run('tart list --format json');
        if (! $result->successful()) {
            return false;
        }
        $vms = json_decode($result->output(), true) ?? [];
        return collect($vms)->contains(fn ($vm) => $vm['Name'] === $name);
    }

    public function list(): Collection
    {
        $result = Process::run('tart list --format json');
        return collect(json_decode($result->output(), true) ?? []);
    }

    public function set(string $name, ?int $cpus = null, ?int $memory_mb = null, ?int $disk_gb = null): void
    {
        $flags = collect([
            $cpus ? "--cpu {$cpus}" : null,
            $memory_mb ? "--memory {$memory_mb}" : null,
            $disk_gb ? "--disk-size {$disk_gb}" : null,
        ])->filter()->implode(' ');

        if ($flags) {
            Process::run("tart set {$name} {$flags}")->throw();
        }
    }

    public function waitForReady(string $name, int $timeout_seconds = 120): string
    {
        $start = time();

        while (time() - $start < $timeout_seconds) {
            $ip = $this->ip($name);
            if ($ip && $this->isSshReady($ip)) {
                return $ip;
            }
            sleep(2);
        }

        throw new RuntimeException("VM {$name} did not become ready within {$timeout_seconds}s");
    }

    protected function isSshReady(string $ip): bool
    {
        $conn = @fsockopen($ip, 22, $errno, $errstr, 2);
        if ($conn) {
            fclose($conn);
            return true;
        }
        return false;
    }
}
```

### GitManager

```php
class GitManager
{
    public function isRepo(string $path): bool
    {
        return Process::path($path)->run('git rev-parse --git-dir')->successful();
    }

    public function currentBranch(): string
    {
        return trim(Process::run('git branch --show-current')->output());
    }

    public function createWorktree(string $path, string $branch, string $base_branch): void
    {
        Process::run("git worktree add -b {$branch} {$path} {$base_branch}")->throw();
    }

    public function removeWorktree(string $path, string $branch, bool $force = false): void
    {
        $force_flag = $force ? '--force' : '';
        Process::run("git worktree remove {$force_flag} {$path}");
        Process::run("git branch -D {$branch}");
    }

    public function mergeAndCleanWorktree(string $path, string $branch, string $target_branch): void
    {
        Process::run("git merge {$branch} --no-edit")->throw();
        $this->removeWorktree($path, $branch, force: true);
    }

    public function ensureIgnored(string $project_dir, string $pattern): void
    {
        $gitignore_path = "{$project_dir}/.gitignore";
        $contents = file_exists($gitignore_path) ? file_get_contents($gitignore_path) : '';

        if (! str_contains($contents, $pattern)) {
            file_put_contents($gitignore_path, rtrim($contents) . "\n{$pattern}\n");
        }
    }
}
```

### HerdManager

```php
class HerdManager
{
    public function proxy(string $name, string $target, bool $secure = true): void
    {
        $flags = $secure ? '--secure' : '';
        Process::run("herd proxy {$name} {$target} {$flags}")->throw();
    }

    public function unproxy(string $name): void
    {
        Process::run("herd unproxy {$name}");
    }
}
```

### SshExecutor

```php
class SshExecutor
{
    protected string $host = '';
    protected string $user;
    protected string $key_path;

    public function __construct()
    {
        $this->user = config('clave.ssh.user', 'admin');
        $this->key_path = config('clave.ssh.key_path',
            $_SERVER['HOME'] . '/.config/clave/ssh/id_ed25519'
        );
    }

    public function setHost(string $host): void
    {
        $this->host = $host;
    }

    protected function sshFlags(): string
    {
        return "-o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -i {$this->key_path}";
    }

    public function run(string $command, int $timeout = 60): ProcessResult
    {
        return Process::timeout($timeout)->run(
            "ssh {$this->sshFlags()} {$this->user}@{$this->host} " . escapeshellarg($command)
        );
    }

    public function interactive(string $command): int
    {
        return Process::forever()->tty()->run(
            "ssh -t {$this->sshFlags()} {$this->user}@{$this->host} " . escapeshellarg($command)
        )->exitCode();
    }

    public function tunnel(string $host, int $local_port, int $remote_port): InvokedProcess
    {
        return Process::start(
            "ssh -N {$this->sshFlags()} -L {$local_port}:localhost:{$remote_port} {$this->user}@{$host}"
        );
    }

    public function test(string $command): bool
    {
        return $this->run($command)->successful();
    }
}
```

---

## Configuration

### `~/.config/clave/config.json`

```json
{
    "vm": {
        "base_image": "ghcr.io/cirruslabs/ubuntu:noble",
        "cpus": 4,
        "memory_mb": 8192,
        "disk_gb": 32
    },
    "ssh": {
        "user": "admin"
    },
    "claude": {
        "model": null,
        "extra_flags": []
    },
    "herd": {
        "auto_proxy": true,
        "secure": true
    },
    "services": {
        "mode": "host"
    }
}
```

`services.mode` is `"host"` for v0. A future version adds `"vm"` to run MySQL/Redis inside each session's VM.

### Environment variables

```bash
export ANTHROPIC_API_KEY=sk-ant-...     # Required
export CLAVE_CPUS=4                      # Optional override
export CLAVE_MEMORY=8192                 # Optional override
```

---

## Directory Structure

```
clave/
├── app/
│   ├── Commands/
│   │   ├── DefaultCommand.php
│   │   ├── ProvisionCommand.php
│   │   ├── SessionsCommand.php
│   │   ├── CleanupCommand.php
│   │   └── ConfigCommand.php
│   ├── Dto/
│   │   ├── SessionContext.php
│   │   └── ServiceConfig.php
│   ├── Models/
│   │   └── Session.php
│   ├── Pipeline/
│   │   ├── CreateWorktree.php
│   │   ├── CloneVm.php
│   │   ├── BootVm.php
│   │   ├── DiscoverGateway.php
│   │   ├── CreateSshTunnel.php
│   │   ├── ConfigureHerdProxy.php
│   │   ├── BootstrapLaravel.php
│   │   └── RunClaudeCode.php
│   ├── Services/
│   │   ├── TartManager.php
│   │   ├── GitManager.php
│   │   ├── HerdManager.php
│   │   ├── SshExecutor.php
│   │   ├── SessionTeardown.php
│   │   └── ProvisioningPipeline.php
│   └── Providers/
│       └── AppServiceProvider.php
├── config/
│   └── clave.php
├── .env.example
└── clave
```

---

## Implementation Order

### Sprint 1 — Boot Loop (days 1–2)

Prove the core lifecycle: boot a VM, get a shell, tear it down.

1. Scaffold Laravel Zero project, install database/dotenv components
2. `SessionContext` and `ServiceConfig` DTOs
3. `TartManager` — all methods
4. `SshExecutor` — all methods
5. SSH keypair generation (store in `~/.config/clave/ssh/`)
6. `ProvisionCommand` + `ProvisioningPipeline` — build base image with PHP/nginx/Claude Code
7. Pipeline stages: CreateWorktree → CloneVm → BootVm → RunClaudeCode (minimal)
8. `SessionTeardown` — cleanup VM, worktree prompt
9. Signal handling (SIGINT/SIGTERM)
10. Sessions SQLite table

**Milestone:** `clave` boots a VM from a Laravel project dir, you get a Claude Code prompt in a worktree, exiting shuts it all down.

### Sprint 2 — Networking + Herd (days 3–4)

Connect the VM to host services and expose the app.

1. `DiscoverGateway` stage — find NAT gateway IP
2. `BootstrapLaravel` stage — symlink, .env patching with host service IPs, composer install, migrate
3. `CreateSshTunnel` stage — port forwarding VM:80 → localhost:port
4. `ConfigureHerdProxy` stage — `herd proxy` setup/teardown
5. Port auto-assignment (scan 8081–8199)
6. `HerdManager` service
7. Host service connectivity check (warn if MySQL/Redis not reachable from VM)

**Milestone:** App accessible at `https://project-a1b2.test`, database works against host MySQL.

### Sprint 3 — Polish (days 5–6)

1. `SessionsCommand` and `CleanupCommand`
2. `ConfigCommand`
3. `GitManager` — merge and discard worktree flows
4. `--resume` flag
5. Progress indicators (Termwind)
6. Error handling: composer failures, SSH timeouts, missing host services
7. Verify VirtioFS mount behavior on Ubuntu guest

**Milestone:** Handles crashes, parallel sessions, cleans up after itself.

### Sprint 4 — Distribution + Future (day 7+)

1. Build PHAR: `php clave app:build`
2. Per-project `.clave.json` config
3. `clave provision --update`
4. In-VM services mode (`services.mode: "vm"`)
5. `clave exec` and `clave ssh` for running sessions

---

## Open Questions

1. **VirtioFS mount path in Linux guests.** Tart's VirtioFS for Linux guests may require explicit `mount -t virtiofs` at boot. The provisioning step adds an fstab entry, but this needs verification in
   Sprint 1. If fstab doesn't work, a systemd mount unit is the fallback.

2. **Host MySQL/Redis bind address.** Herd Pro defaults to `127.0.0.1`. VMs need services on `0.0.0.0` or the gateway interface. Clave should check reachability during `DiscoverGateway` and print
   actionable instructions if services aren't accessible. This is a one-time user configuration step.

3. **`tart run` process management.** Need to verify `Process::start()` keeps the VM alive while the parent blocks on TTY. May need `nohup` with PID file as fallback.

4. **SSH key injection during first provisioning.** Base Ubuntu image uses `admin`/`admin`. Provisioning needs password-based SSH for the initial key injection. Options: `sshpass` (via pkgx),
   `tart exec` (guest agent), or `expect`. After provisioning, all sessions use key auth.

5. **Composer install on VirtioFS.** v0 runs directly on the mount. If too slow, install on VM local disk and symlink `vendor/` back.
