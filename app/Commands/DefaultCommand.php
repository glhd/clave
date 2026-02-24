<?php

namespace App\Commands;

use App\Data\OnExit;
use App\Data\SessionContext;
use App\Exceptions\AbortedPipelineException;
use App\Pipelines\ClaudeCodePipeline;
use App\Pipelines\PreflightPipeline;
use App\Support\SessionTeardown;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;
use function Laravel\Prompts\clear;
use function Laravel\Prompts\error;

class DefaultCommand extends Command
{
	protected $signature = 'default {--on-exit= : Action on exit: keep, merge, discard}';
	
	protected $description = 'Start a Clave session in the current Laravel project';
	
	protected $hidden = true;
	
	public function handle(
		PreflightPipeline $preflight,
		ClaudeCodePipeline $claude,
		SessionTeardown $teardown,
	): int {
		try {
			clear();
			
			$this->newLine();
			
			$this->callSilently('migrate', ['--force' => true]);
			
			$context = $this->newContext();
			
			$this->line("Clave session <info>{$context->session_id}</info> in project <info>{$context->project_name}</info>");
			
			$preflight->run($context);
			
			$this->trap([SIGINT, SIGTERM], static fn() => $teardown->run($context));
			
			try {
				$claude->run($context);
			} finally {
				$teardown->run($context);
			}
			
			return self::SUCCESS;
		} catch (AbortedPipelineException $exception) {
			error($exception->getMessage());
		} finally {
			$this->newLine();
		}
		
		return self::FAILURE;
	}
	
	protected function newContext(): SessionContext
	{
		$project_dir = getcwd();
		$project_name = basename($project_dir);
		
		return new SessionContext(
			session_id: Str::random(8),
			project_name: $project_name,
			project_dir: $project_dir,
			on_exit: OnExit::tryFrom($this->option('on-exit') ?? ''),
			command: $this,
		);
	}
}
