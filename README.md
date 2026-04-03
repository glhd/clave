# clave

Spin up ephemeral VMs via [Tart](https://tart.run/) for isolated [Claude Code](https://claude.ai/code) sessions. Run `clave` from within a project's
git repo, and it handles repo cloning, VM lifecycle, networking, and teardown automatically.

## Installation

```shell
curl -fsSL https://clave.run | sh
```

## Requirements

- macOS with [Tart](https://tart.run/) installed
- A [Claude Code](https://claude.ai/code) account

## Usage

### Working Directory Mode

Run `clave` from within a project's git repository:

```shell
cd /path/to/your/project
clave
```

Clave will spin up a fresh VM, mount your current working directory into the VM, and launch an interactive Claude Code session
inside it. When you exit, the VM and clone are torn down automatically. This lets you work on the local project similar to running
`claude`, but without having to worry about the agent accidentally deleting your home directory or getting tricked into shipping
your SSH private key somewhere.

### Isolate Mode

Run `clave --isolate` from within a project's git repository:

```shell
cd /path/to/your/project
clave --isolate
```

Before spinning up a fresh VM, clave will create a local git clone of your working directory. (This is similarly efficient as a
worktree, but has a copy of your full git history.) Clave will then mount that clone into the VM, keeping any changes made inside
the VM completely isolated from your project until you quit. Once you quit, you will be prompted to decide what to do with the
changes before the clone is cleaned up.

## Configuration

Add a `.clave.json` file to your project root to customize VM setup for your project.

### `.clave.json`

```json
{
  "base_image": "ghcr.io/cirruslabs/ubuntu:latest",
  "cpus": 8,
  "memory": 16384,
  "provision": [
    "sudo apt-get install -y redis-server",
    "sudo systemctl enable redis-server"
  ],
  "env": [
    "STRIPE_SECRET_KEY",
    "CUSTOM_API_TOKEN"
  ]
}
```

### Options

| Key          | Type     | Description                                                                    |
|--------------|----------|--------------------------------------------------------------------------------|
| `base_image` | string   | Custom Tart VM base image. Defaults to `ghcr.io/cirruslabs/ubuntu:latest`      |
| `cpus`       | integer  | CPUs allocated to the VM. Defaults to `CLAVE_VM_CPUS` (4)                      |
| `memory`     | integer  | Memory (MB) allocated to the VM. Defaults to `CLAVE_VM_MEMORY` (8192)          |
| `provision`  | string[] | Bash commands to run during VM provisioning                                    |
| `env`        | string[] | Environment variable names to pass through from your host into the VM          |
| `shims`      | string[] | Host commands to proxy into the VM (see [Host Proxy Shims](#host-proxy-shims)) |

See the [`tart` documentation](https://tart.run/quick-start/#vm-images) for a list of available base images.

### Host Proxy Shims

Shims let Claude Code running inside the VM transparently execute specific commands on your Mac host. This is useful for tools that
only exist on macOS or GUI-based CLIs like `playwright-cli`.

When a shim is invoked inside the VM, it sends the command over a Unix socket tunnel to a proxy daemon running on the host, which
executes it and returns the output. From Claude's perspective, it's a normal command.

**Shims are opt in and disabled by default.**

Enable shims in your `.clave.json`:

```json
{
  ...
  "shims": [
    "playwright-cli"
  ]
}
```

> **Note:** Adding shims for the first time requires reprovisioning the base VM. The shim symlinks themselves 
> are created fresh each session, so changes to the `shims` list take effect next run without reprovisioning.

### Environment Variables Passed Through Automatically

Clave always forwards these environment variables into the VM when present on the host:

- `COLORTERM`, `FORCE_COLOR`, `NO_COLOR`
- `GIT_AUTHOR_EMAIL`, `GIT_AUTHOR_NAME`, `GIT_COMMITTER_EMAIL`, `GIT_COMMITTER_NAME`
- `LANG`, `LC_ALL`, `LC_CTYPE`
- `TZ`, `VISUAL`

Any additional variables listed in `.clave.json`'s `env` array are forwarded as well.

## Global Configuration

Clave reads the following environment variables for global defaults:

| Variable                  | Default                            | Description                                      |
|---------------------------|------------------------------------|--------------------------------------------------|
| `ANTHROPIC_API_KEY`       | —                                  | Anthropic API key for Claude Code                |
| `CLAUDE_CODE_OAUTH_TOKEN` | —                                  | Claude Code OAuth token (alternative to API key) |
| `CLAVE_BASE_IMAGE`        | `ghcr.io/cirruslabs/ubuntu:latest` | Default VM base image                            |
| `CLAVE_BASE_VM`           | `clave-base`                       | Name of the base Tart VM                         |
| `CLAVE_VM_CPUS`           | `4`                                | CPUs allocated to each VM                        |
| `CLAVE_VM_MEMORY`         | `8192`                             | Memory (MB) allocated to each VM                 |
