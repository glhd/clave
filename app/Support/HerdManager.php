<?php

namespace App\Support;

use Illuminate\Support\Facades\Process;

class HerdManager
{
	public function proxy(string $domain, string $target, bool $secure = true): mixed
	{
		$args = ['herd', 'proxy', $domain, $target];

		if ($secure) {
			$args[] = '--secure';
		}

		return Process::run($args)->throw();
	}

	public function unproxy(string $domain): mixed
	{
		return Process::run(['herd', 'unproxy', $domain]);
	}
}
