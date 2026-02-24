<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;

class HerdManager
{
	public function proxy(string $domain, string $target, bool $secure = true): mixed
	{
		$cmd = "herd proxy {$domain} {$target}";

		if ($secure) {
			$cmd .= ' --secure';
		}

		return Process::run($cmd)->throw();
	}

	public function unproxy(string $domain): mixed
	{
		return Process::run("herd unproxy {$domain}");
	}
}
