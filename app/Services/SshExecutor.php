<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;

class SshExecutor
{
	protected string $user;

	protected int $port;

	protected string $key;

	protected array $options;

	protected ?string $host = null;

	protected ?string $password = null;

	public function __construct()
	{
		$this->user = config('clave.ssh.user');
		$this->port = config('clave.ssh.port');
		$this->key = config('clave.ssh.key');
		$this->options = config('clave.ssh.options', []);
	}

	public function setHost(string $host): self
	{
		$this->host = $host;

		return $this;
	}

	public function usePassword(string $password): self
	{
		$this->password = $password;

		return $this;
	}

	public function run(string $command, int $timeout = 60): mixed
	{
		return Process::timeout($timeout)
			->run($this->buildCommand($command))
			->throw();
	}

	public function interactive(string $command): mixed
	{
		return Process::forever()
			->tty()
			->run($this->buildCommand($command));
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

			return $result->successful() && str_contains($result->output(), 'ok');
		} catch (\Throwable) {
			return false;
		}
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
			$parts[] = 'sshpass';
			$parts[] = '-p';
			$parts[] = escapeshellarg($this->password);
		}

		$parts[] = 'ssh';
		$parts[] = "-p {$this->port}";

		if ($this->password === null) {
			$parts[] = '-i '.escapeshellarg($this->key);
		}

		foreach ($this->options as $key => $value) {
			$parts[] = "-o {$key}={$value}";
		}

		return implode(' ', $parts);
	}
}
