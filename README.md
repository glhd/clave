# clave

## Prerequisites

Clave requires the following:

- **macOS 13.0 or later** with **Apple Silicon** (M1/M2/M3)
- **Tart** — virtualization tool for creating ephemeral Ubuntu VMs

### Installing Tart

**Option 1: Via Homebrew (recommended)**
```shell
brew install cirruslabs/cli/tart
```

**Option 2: Via direct download**
```shell
curl -LO https://github.com/cirruslabs/tart/releases/latest/download/tart.tar.gz
tar -xzf tart.tar.gz
sudo mv tart /usr/local/bin/
sudo chmod +x /usr/local/bin/tart
```

For more information, visit the [Tart GitHub repository](https://github.com/cirruslabs/tart).

## Installation

Install Clave with:

```shell
curl -fsSL https://clave.run | sh
```
