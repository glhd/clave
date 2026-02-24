<?php

namespace App\Commands;

use App\Dto\OnExit;
use App\Dto\SessionContext;
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
		SessionTeardown $teardown,
	): int {
		try {
			clear();
			
			$this->newLine();
			
			$this->callSilently('migrate', ['--force' => true]);
			
			$context = $this->newContext();
			
			app(PreflightPipeline::class)->handle($context);
			
			$this->info("Starting Clave session: {$context->session_id}");
			$this->info(" - Project: {$context->project_name} ({$context->base_branch})");
			
			$this->trap([SIGINT, SIGTERM], function() use ($context, $teardown) {
				$this->newLine();
				$this->info('Shutting down...');
				$teardown($context, $this);
			});
			
			try {
				app(ClaudeCodePipeline::class)->handle($context);
			} finally {
				$this->newLine();
				$this->info('Cleaning up...');
				$teardown($context, $this);
				$this->newLine();
			}
			
			return self::SUCCESS;
		} catch (AbortedPipelineException $exception) {
			error($exception->getMessage());
			$this->newLine();
			
			return self::FAILURE;
		}
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
