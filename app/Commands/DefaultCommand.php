<?php

namespace App\Commands;

use App\Data\OnExit;
use App\Data\SessionContext;
use App\Exceptions\AbortedPipelineException;
use App\Pipelines\SessionSetup;
use App\Support\SessionTeardown;
use Illuminate\Support\Str;
use function Laravel\Prompts\clear;
use function Laravel\Prompts\error;
use LaravelZero\Framework\Commands\Command;
use function Laravel\Prompts\note;

class DefaultCommand extends Command
{
	protected $signature = 'default {--on-exit= : Action on exit: keep, merge, discard}';
	
	protected $description = 'Start a Clave session in the current project';
	
	protected $hidden = true;
	
	public function handle(
		SessionSetup $setup,
		SessionTeardown $teardown,
	): int {
		try {
			clear();
			
			$this->newLine();
			
			$this->callSilently('migrate', ['--force' => true]);
			
			$context = $this->newContext();
			
			note("Clave session <info>{$context->session_id}</info> in project <info>{$context->project_name}</info>");
			
			$this->trap([SIGINT, SIGTERM], static fn() => $teardown($context));
			
			try {
				$setup($context);
			} finally {
				$teardown($context);
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
