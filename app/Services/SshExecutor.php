<?php

namespace App\Services;

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
		return Process::timeout($timeout)
			->run($this->buildCommand($command))
			->throw();
	}

	public function interactive(string $command): int
	{
		$exit_code = 0;
		passthru($this->buildCommand($command), $exit_code);

		return $exit_code;
	}

	public function tunnel(int $local_port, string $remote_host, int $remote_port): mixed
	{
		$cmd = $this->buildSshPrefix();
		$cmd .= " -N -L {$local_port}:{$remote_host}:{$remote_port}";
		$cmd .= " {$this->user}@{$this->host}";

		return Process::start($cmd);
	}

	public function test(): bool
	{
		try {
			$result = Process::timeout(5)
				->run($this->buildCommand('echo ok'));

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

	protected function buildCommand(string $remote_command): string
	{
		$cmd = $this->buildSshPrefix();
		$cmd .= " {$this->user}@{$this->host}";
		$cmd .= ' '.escapeshellarg($remote_command);

		return $cmd;
	}

	protected function buildSshPrefix(): string
	{
		$parts = [];

		if ($this->password !== null) {
			$parts[] = 'SSH_ASKPASS='.escapeshellarg($this->askpass_path);
			$parts[] = 'SSH_ASKPASS_REQUIRE=force';
		}

		$parts[] = 'ssh';
		$parts[] = "-p {$this->port}";

		foreach ($this->options as $key => $value) {
			$parts[] = "-o {$key}={$value}";
		}

		return implode(' ', $parts);
	}
}
