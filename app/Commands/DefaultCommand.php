<?php

namespace App\Commands;

use App\Agents\ClaudeCode;
use App\Data\OnExit;
use App\Data\SessionContext;
use App\Exceptions\AbortedPipelineException;
use App\Pipelines\SessionSetup;
use App\Support\SessionTeardown;
use Illuminate\Support\Str;
use function Laravel\Prompts\clear;
use function Laravel\Prompts\error;
use function Laravel\Prompts\note;
use LaravelZero\Framework\Commands\Command;

class DefaultCommand extends Command
{
	protected $signature = 'default
		{--on-exit= : Action on exit: keep, merge, discard}
		{--isolate : Fork the repo into an isolated clone for this session}';
	
	protected $description = 'Start a Clave session in the current project';
	
	protected $hidden = true;
	
	public function handle(
		ClaudeCode $agent,
		SessionSetup $setup,
		SessionTeardown $teardown,
	): int {
		try {
			clear();
			
			$this->newLine();
			
			$this->callSilently('migrate', ['--force' => true]);
			
			$version = config('app.version');
			$context = $this->newContext();
			
			note("Clave {$version} session <info>{$context->session_id}</info> in project <info>{$context->project_name}</info>");
			
			$this->trap([SIGINT, SIGTERM], static fn() => $teardown($context));
			
			try {
				// First set everything up
				$setup($context);
				
				// Then clear screen and start our agent in the VM
				clear();
				$agent($context);
			} finally {
				// Finally, clear screen again and run teardown
				clear();
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
			isolate: (bool) $this->option('isolate'),
			on_exit: OnExit::tryFrom($this->option('on-exit') ?? ''),
			command: $this,
		);
	}
}
