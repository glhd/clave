<?php

namespace App\Support;

use Illuminate\Support\Facades\Process;

class SshExecutor
{
	protected string $user;

	protected int $port;

	protected array $options;

	protected ?string $host = null;

	protected ?string $password = null;

	protected ?string $askpass_path = null;

	protected ?string $last_error = null;

	public function __construct()
	{
		$this->user = config('clave.ssh.user');
		$this->port = config('clave.ssh.port');
		$this->options = config('clave.ssh.options', []);
	}

	public function __destruct()
	{
		if ($this->askpass_path && file_exists($this->askpass_path)) {
			unlink($this->askpass_path);
		}
	}

	public function setHost(string $host): self
	{
		$this->host = $host;

		return $this;
	}

	public function usePassword(string $password): self
	{
		$this->password = $password;
		$this->askpass_path = tempnam(sys_get_temp_dir(), 'clave-askpass-');
		file_put_contents($this->askpass_path, "#!/bin/sh\necho ".escapeshellarg($password)."\n");
		chmod($this->askpass_path, 0700);

		return $this;
	}

	public function run(string $command, int $timeout = 60): mixed
	{
		return Process::env($this->sshEnv())
			->timeout($timeout)
			->run($this->buildCommandArgs($command))
			->throw();
	}

	public function interactive(string $command): int
	{
		$result = Process::env($this->sshEnv())
			->tty()
			->run($this->buildCommandArgs($command, tty: true));

		return $result->exitCode();
	}

	public function tunnel(int $local_port, string $remote_host, int $remote_port): mixed
	{
		return Process::env($this->sshEnv())
			->start([
				...$this->buildSshArgs(),
				'-N', '-L', "{$local_port}:{$remote_host}:{$remote_port}",
				"{$this->user}@{$this->host}",
			]);
	}

	public function test(): bool
	{
		try {
			$result = Process::env($this->sshEnv())
				->timeout(5)
				->run($this->buildCommandArgs('echo ok'));

			if ($result->successful() && str_contains($result->output(), 'ok')) {
				$this->last_error = null;

				return true;
			}

			$this->last_error = trim($result->errorOutput()) ?: trim($result->output());

			return false;
		} catch (\Throwable $e) {
			$this->last_error = $e->getMessage();

			return false;
		}
	}

	public function lastError(): ?string
	{
		return $this->last_error;
	}

	protected function buildCommandArgs(string $remote_command, bool $tty = false): array
	{
		$args = $this->buildSshArgs();

		if ($tty) {
			$args[] = '-tt';
		}

		$args[] = "{$this->user}@{$this->host}";
		$args[] = $remote_command;

		return $args;
	}

	protected function buildSshArgs(): array
	{
		$args = ['ssh', '-p', (string) $this->port];

		foreach ($this->options as $key => $value) {
			$args[] = '-o';
			$args[] = "{$key}={$value}";
		}

		return $args;
	}

	protected function sshEnv(): array
	{
		if ($this->password === null) {
			return [];
		}

		return [
			'SSH_ASKPASS' => $this->askpass_path,
			'SSH_ASKPASS_REQUIRE' => 'force',
		];
	}
}
