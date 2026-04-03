# Mount ~/.claude/ Into VM + CLAUDE.md Override + Round-Trip ~/.claude.json Sync

## Context

Clave sessions lose Claude Code state (auto-memory, history, slash commands, skills, agents, rules) because `SetupClaudeCode` only copies a curated subset of config files. This makes features like
long-term memory, `--continue`/`--resume`, and personal commands unavailable in VM sessions. The fix is to mount the host's `~/.claude/` directory directly into the VM via VirtioFS, sync
`~/.claude.json` round-trip, and handle the macOS-vs-Ubuntu instruction mismatch with an injectable preamble.

**Why paths match:** Clave already bind-mounts the project at its original absolute macOS path inside the VM (e.g., `/Users/inxilpro/Development/laravel-zero/clave`). Claude Code's
`~/.claude/projects/` directories encode that path (e.g., `-Users-inxilpro-Development-laravel-zero-clave`), so project memory resolves correctly. The `~/.claude.json` projects map keys also match.

**What doesn't match:** Global `~/.claude/CLAUDE.md` may contain macOS-specific instructions (e.g., Herd PHP paths). Solution: inject a default preamble, with override support via `.clave.json`.

---

## Implementation

### 1. Mount `~/.claude/` as a second VirtioFS share

**`app/Support/TartManager.php`** — Update `runBackground()` to support named directory mounts:

Currently passes `--dir={path}` for each dir. Change to support `--dir=name:path` syntax. Pass two mounts:

- `--dir=project:{project_path}`
- `--dir=claude-home:{home}/.claude`

The `dirs` parameter should change from `array $dirs` (list of paths) to `array $dirs` (associative: name => path) so each mount gets a unique VirtioFS tag.

**`app/Pipelines/Steps/BootVm.php`** — Update `mountSharedDirectories()`:

After mounting the project share (existing), mount the claude config share:

```bash
sudo mkdir -p /home/admin/.claude
sudo mount -t virtiofs claude-home /home/admin/.claude
```

The `cleanupResumedVm()` method should also unmount `~/.claude` on resume before remounting.

Update the `--dir` call in `handle()` to pass both directories:

```php
$mount_path = $context->clone_path ?? $context->project_dir;
$dirs = [
    'project' => $mount_path,
    'claude-home' => home_path('.claude'),
];
$this->tart->runBackground($context->vm_name, $dirs);
```

### 2. Simplify `SetupClaudeCode` (most config is now mounted)

**`app/Pipelines/Steps/SetupClaudeCode.php`** — Remove manual writing of:

- `~/.claude/settings.json` (now mounted)
- `~/.claude/CLAUDE.md` (now mounted, with preamble — see step 4)

**Keep writing:**

- `~/.claude.json` — still a file (not a directory), can't be VirtioFS mounted. Continue writing curated version at session start.
- `~/.claude/.credentials.json` — needs VM-specific auth values. Write OVER the mounted file (VirtioFS is read-write).
- `~/.claude/ide/{port}.lock` — needs VM-specific IDE integration values.

### 3. Round-trip sync `~/.claude.json`

**At session start** (`SetupClaudeCode`): Write curated `~/.claude.json` into VM as today, but include more fields from the host version (the full `projects` map, `githubRepoPaths`, etc.) since we now
want transparency.

**At session end** (`SessionTeardown`): Read `~/.claude.json` from the VM via SSH, diff relevant fields, and merge changes back to the host file. Relevant fields to sync back:

- `projects.{current_project}.allowedTools` — user may have approved new tools
- `theme` — user may have changed theme
- Other user-facing preference changes

Implementation in `SessionTeardown`:

```php
// Read VM's ~/.claude.json
$vm_config = json_decode($this->ssh->run('cat ~/.claude.json')->output(), true);
// Read host's ~/.claude.json
$host_config = json_decode(file_get_contents(home_path('.claude.json')), true);
// Merge specific fields back
// Write host file
```

### 4. CLAUDE.md preamble injection

Since `~/.claude/` is mounted read-write via VirtioFS, we **cannot** modify `~/.claude/CLAUDE.md` without changing the host file. Solution: **bind-mount a shadow file** over it.

1. After mounting `~/.claude/`, read the mounted CLAUDE.md content
2. Write preamble + original content to `/tmp/claude-md-override`
3. `sudo mount --bind /tmp/claude-md-override /home/admin/.claude/CLAUDE.md`

This shadows the VirtioFS file without modifying the host. Automatically cleaned up when the VM stops.

**Default preamble:**

```
# Clave VM Environment
You are running inside an Ubuntu VM managed by Clave. Ignore any macOS-specific
instructions below (e.g., Herd PHP paths, ~/Library/ paths, Homebrew paths).
PHP is available as `php`. Node.js is available as `node`/`npm`.
```

**`.clave.json` config for custom preamble:**

```json
{
  "claude_md_preamble": "Use php83 instead of php. Redis is on port 6380."
}
```

If set, replaces the default preamble. If set to `false` or empty string, no preamble is injected.

### 5. Concurrent sessions

Since `~/.claude/` is mounted from the host into potentially multiple VMs, concurrent sessions share the same memory and history files. This is the same as running multiple Claude Code sessions
locally — Claude Code already handles this. No special handling needed.

---

## Files to modify

| File                                      | Changes                                                                                                                                                                                       |
|-------------------------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `app/Support/TartManager.php`             | `runBackground()`: change `$dirs` from indexed to associative, generate `--dir=name:path` flags                                                                                               |
| `app/Pipelines/Steps/BootVm.php`          | `handle()`: pass both project and claude-home dirs. `mountSharedDirectories()`: mount both VirtioFS shares + bind-mount CLAUDE.md override. `cleanupResumedVm()`: handle claude mount cleanup |
| `app/Pipelines/Steps/SetupClaudeCode.php` | Remove settings.json/CLAUDE.md writing. Keep ~/.claude.json and credentials writing. Add CLAUDE.md preamble logic (read mounted file, prepend preamble, write to temp, bind-mount)            |
| `app/Support/SessionTeardown.php`         | Add `~/.claude.json` sync-back logic                                                                                                                                                          |
| `app/Data/ProjectConfig.php`              | Add `claude_md_preamble` field                                                                                                                                                                |

---

## Verification

1. **Memory persistence**: Run `clave`, ask Claude to remember something, exit, run `clave` again — verify it recalls
2. **`--continue`/`--resume`**: Start a session, exit, run with `--continue` — verify it picks up
3. **Personal commands**: Create a command in `~/.claude/commands/` on host, verify available in VM
4. **CLAUDE.md preamble**: Verify preamble appears in VM's CLAUDE.md but host file is unmodified
5. **`.clave.json` preamble override**: Set `claude_md_preamble`, verify it replaces default
6. **`~/.claude.json` round-trip**: Approve a tool in VM, exit, verify it's in host's `~/.claude.json`
7. **Concurrent sessions**: Run two `clave` sessions simultaneously — verify no conflicts
8. **Existing tests**: `./vendor/bin/pest` — all pass

---

## Research notes

### Claude Code local file structure

- `~/.claude/projects/{encoded-path}/memory/MEMORY.md` — auto-memory (first 200 lines loaded at session start)
- `~/.claude/projects/{encoded-path}/{session-uuid}.jsonl` — session transcripts
- Path encoding: every `/` in the absolute project path becomes `-`
- Project path derived from git repo root
- `~/.claude.json` `projects` map keys are absolute paths (used as lookup identifiers)

### Tart VirtioFS capabilities

- Multiple `--dir` flags supported: `--dir=name1:path1 --dir=name2:path2`
- Each name becomes a unique VirtioFS mount tag
- Linux guests require manual `mount -t virtiofs` (macOS guests auto-mount at `/Volumes/My Shared Files/`)
- Cannot add/remove mounts to a running VM
- ~3x slower than native filesystem (acceptable for config files)

### Why not macOS VM

- Hard 2-VM limit enforced by Apple's Virtualization.Framework — kills multi-session support
- 15-30GB+ images vs ~2GB for Ubuntu
- Slower boot, more complex provisioning
- The path problem is already solved by bind-mounting at the original path

### Why not persistent VM (for now)

- Can't dynamically add VirtioFS mounts to a running VM
- Would require pre-mounting a broad directory (e.g., ~/Development)
- Loses clean-room isolation
- Linux VMs can't suspend (must stay running or cold-boot)
- Could be layered on later — Option A's changes carry forward
