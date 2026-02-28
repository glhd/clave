<?php

namespace App\Support;

use Illuminate\Support\Facades\Process;
use RuntimeException;
use function App\checklist;

class TartManager
{
	public function clone(string $source, string $name): mixed
	{
		return $this->tart('clone', 0, $source, $name)->throw();
	}

	public function runBackground(string $name, array $dirs = [], bool $no_graphics = true): mixed
	{
		$args = ['tart', 'run', $name];

		if ($no_graphics) {
			$args[] = '--no-graphics';
		}

		foreach ($dirs as $path) {
			$args[] = '--dir';
			$args[] = $path;
		}

		return Process::start($args);
	}

	public function stop(string $name): mixed
	{
		return Process::timeout(30)->run($this->tartCmd('stop', $name));
	}

	public function delete(string $name): mixed
	{
		return Process::run($this->tartCmd('delete', $name));
	}

	public function ip(string $name, int $timeout = 30): string
	{
		$cmd = $this->tartCmd('ip', $name);
		$start = time();

		while (time() - $start < $timeout) {
			$result = Process::run($cmd);
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
		return $this->tart('get', 60, $name)->successful();
	}

	public function list(): array
	{
		$result = Process::run(['tart', 'list', '--format', 'json'])->throw();

		return json_decode($result->output(), true) ?? [];
	}

	public function randomizeMac(string $name): mixed
	{
		return Process::run([...$this->tartCmd('set', $name), '--random-mac'])->throw();
	}

	public function rename(string $old_name, string $new_name): mixed
	{
		return $this->tart('rename', 60, $old_name, $new_name)->throw();
	}

	public function set(string $name, ?int $cpus = null, ?int $memory = null, ?string $display = null): mixed
	{
		$args = ['tart', 'set', $name];

		if ($cpus !== null) {
			$args[] = '--cpu';
			$args[] = (string) $cpus;
		}

		if ($memory !== null) {
			$args[] = '--memory';
			$args[] = (string) $memory;
		}

		if ($display !== null) {
			$args[] = '--display';
			$args[] = $display;
		}

		return Process::run($args)->throw();
	}

	protected function tartCmd(string $subcommand, string ...$args): array
	{
		return ['tart', $subcommand, ...$args];
	}

	protected function tart(string $subcommand, int $timeout, string ...$args): mixed
	{
		return Process::timeout($timeout)->run($this->tartCmd($subcommand, ...$args));
	}

	public function waitForReady(string $name, SshExecutor $ssh, int $timeout = 90): void
	{
		$ssh->setHost($this->ip($name, $timeout));

		$start = time();
		while (time() - $start < $timeout) {
			if ($ssh->test()) {
				return;
			}
			sleep(2);
		}

		throw new RuntimeException("Timed out waiting for SSH to be ready on VM '{$name}': {$ssh->lastError()}");
	}
}
