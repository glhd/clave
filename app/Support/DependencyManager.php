<?php

namespace App\Support;

use Illuminate\Support\Facades\Process;

class DependencyManager
{
	public function isTartInstalled(): bool
	{
		return Process::run('which tart')->successful();
	}
	
	public function isPkgxInstalled(): bool
	{
		return Process::run('which pkgx')->successful();
	}
	
	public function isHomebrewInstalled(): bool
	{
		return Process::run('which brew')->successful();
	}
	
	public function installTartViaPkgx(): bool
	{
		return Process::timeout(300)->run('pkgx install tart')->successful();
	}
	
	public function installTartViaHomebrew(): bool
	{
		return Process::timeout(300)->run('brew install cirruslabs/cli/tart')->successful();
	}
}
