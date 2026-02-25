<?php

namespace App\Support;

use Illuminate\Support\Facades\Process;

class InstallationManager
{
	public function isTartInstalled(): bool
	{
		$result = Process::run('which tart');

		return $result->successful();
	}

	public function isHomebrewInstalled(): bool
	{
		$result = Process::run('which brew');

		return $result->successful();
	}

	public function installTartViaHomebrew(): mixed
	{
		return Process::timeout(300)->run('brew install cirruslabs/cli/tart');
	}

	public function getManualInstructions(): string
	{
		return <<<'INSTRUCTIONS'
Tart Installation Instructions
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Requirements:
  • macOS 13.0 or later
  • Apple Silicon (M1/M2/M3)

Option 1: Install via Homebrew (recommended)
  brew install cirruslabs/cli/tart

Option 2: Install via direct download
  curl -LO https://github.com/cirruslabs/tart/releases/latest/download/tart.tar.gz
  tar -xzf tart.tar.gz
  sudo mv tart /usr/local/bin/
  sudo chmod +x /usr/local/bin/tart

For more information, visit: https://github.com/cirruslabs/tart
INSTRUCTIONS;
	}
}
