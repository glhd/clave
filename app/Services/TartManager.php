<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use RuntimeException;

class TartManager
{
	public function clone(string $source, string $name): mixed
	{
		return Process::run("tart clone {$source} {$name}")->throw();
	}

	public function runBackground(string $name, array $dirs = [], bool $no_graphics = true): mixed
	{
		$cmd = "tart run {$name}";

		if ($no_graphics) {
			$cmd .= ' --no-graphics';
		}

		foreach ($dirs as $label => $path) {
			$cmd .= " --dir={$label}:{$path}";
		}

		return Process::start($cmd);
	}

	public function stop(string $name): mixed
	{
		return Process::timeout(30)->run("tart stop {$name}");
	}

	public function delete(string $name): mixed
	{
		return Process::run("tart delete {$name}");
	}

	public function ip(string $name, int $timeout = 30): string
	{
		$start = time();

		while (time() - $start < $timeout) {
			$result = Process::run("tart ip {$name}");
			$ip = trim($result->output());

			if ($result->successful() && filter_var($ip, FILTER_VALIDATE_IP)) {
				return $ip;
			}

			sleep(1);
		}

		throw new RuntimeException("Timed out waiting for IP address of VM '{$name}'");
	}

	public function exists(string $name): bool
	{
		return Process::run("tart get {$name}")->successful();
	}

	public function list(): array
	{
		$result = Process::run('tart list --format json')->throw();

		return json_decode($result->output(), true) ?? [];
	}

	public function randomizeMac(string $name): mixed
	{
		return Process::run("tart set {$name} --random-mac")->throw();
	}

	public function rename(string $old_name, string $new_name): mixed
	{
		return Process::run("tart rename {$old_name} {$new_name}")->throw();
	}

	public function set(string $name, ?int $cpus = null, ?int $memory = null, ?string $display = null): mixed
	{
		$cmd = "tart set {$name}";

		if ($cpus !== null) {
			$cmd .= " --cpu {$cpus}";
		}

		if ($memory !== null) {
			$cmd .= " --memory {$memory}";
		}

		if ($display !== null) {
			$cmd .= " --display {$display}";
		}

		return Process::run($cmd)->throw();
	}

	public function waitForReady(string $name, SshExecutor $ssh, int $timeout = 90): void
	{
		$ip = $this->ip($name, $timeout);
		$ssh->setHost($ip);

		$start = time();
		while (time() - $start < $timeout) {
			if ($ssh->test()) {
				return;
			}
			sleep(2);
		}

		throw new RuntimeException("Timed out waiting for SSH to be ready on VM '{$name}'");
	}
}
