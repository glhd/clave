<?php

namespace App\Commands;

use Amp\ByteStream\ReadableStream;
use Amp\Cancellation;
use Amp\CancelledException;
use Amp\DeferredCancellation;
use Amp\Process\Process;
use Amp\Socket;
use Amp\TimeoutCancellation;
use LaravelZero\Framework\Commands\Command;
use function Amp\async;
use function Amp\trapSignal;

class ProxyDaemonCommand extends Command
{
	protected $signature = 'proxy:daemon {--socket= : Path to the Unix domain socket} {--shim=* : Allowed command names}';
	
	protected $description = 'Run the host proxy daemon';
	
	public function handle(): int
	{
		$socket_path = $this->option('socket');
		
		if (! $socket_path) {
			$this->error('--socket option is required');
			
			return self::FAILURE;
		}
		
		$allowlist = $this->option('shim') ?: config('clave.proxy.shims', []);
		
		$server = Socket\listen('unix://'.$socket_path);
		
		async(function() use ($server, $allowlist) {
			while ($connection = $server->accept()) {
				async(function() use ($connection, $allowlist) {
					try {
						$buffer = '';
						
						while (null !== ($chunk = $connection->read())) {
							$buffer .= $chunk;
							if (str_contains($buffer, "\n")) {
								break;
							}
						}
						
						$request = json_decode(trim($buffer), true);
						$response = $this->handleRequest($request, $allowlist);
						
						$connection->write(json_encode($response)."\n");
					} catch (\Throwable) {
						// Connection closed before response could be written
					} finally {
						$connection->close();
					}
				});
			}
		});
		
		trapSignal([SIGTERM, SIGINT]);
		
		$server->close();
		
		if (file_exists($socket_path)) {
			unlink($socket_path);
		}
		
		return self::SUCCESS;
	}
	
	protected function handleRequest(?array $request, array $allowlist): array
	{
		if (! is_array($request) || ! isset($request['cmd'])) {
			return ['stdout' => '', 'stderr' => "Invalid request\n", 'exit_code' => 1];
		}
		
		$cmd = $request['cmd'];
		
		if (! in_array($cmd, $allowlist, true)) {
			return ['stdout' => '', 'stderr' => "Command not allowed\n", 'exit_code' => 1];
		}
		
		$args = $request['args'] ?? [];
		$cwd = $request['cwd'] ?? getcwd();
		
		try {
			$process = Process::start(array_merge([$cmd], $args), workingDirectory: $cwd);
			
			$deferred = new DeferredCancellation();
			$cancellation = $deferred->getCancellation();
			
			$stdout_future = async(fn() => $this->readStream($process->getStdout(), $cancellation));
			$stderr_future = async(fn() => $this->readStream($process->getStderr(), $cancellation));
			
			$exit_code = 0;
			try {
				$exit_code = $process->join(new TimeoutCancellation(30.0));
			} catch (CancelledException) {
				$deferred->cancel();
			}
			
			return [
				'stdout' => $stdout_future->await(),
				'stderr' => $stderr_future->await(),
				'exit_code' => $exit_code,
			];
		} catch (\Throwable $e) {
			return ['stdout' => '', 'stderr' => $e->getMessage()."\n", 'exit_code' => 1];
		}
	}
	
	private function readStream(ReadableStream $stream, Cancellation $cancellation): string
	{
		$data = '';
		try {
			while (null !== ($chunk = $stream->read($cancellation))) {
				$data .= $chunk;
			}
		} catch (CancelledException) {
			// Return whatever was read before the timeout
		}
		return $data;
	}
}
