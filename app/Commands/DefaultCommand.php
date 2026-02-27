<?php

namespace App\Commands;

use App\Agents\ClaudeCode;
use Laravel\Prompts\Concerns\Colors;
use function App\checklist;
use function App\clear_screen;
use App\Data\OnExit;
use App\Data\SessionContext;
use App\Exceptions\AbortedPipelineException;
use function App\header;
use App\Pipelines\SessionSetup;
use App\Support\SessionTeardown;
use Illuminate\Support\Str;
use function Laravel\Prompts\error;
use function Laravel\Prompts\note;
use LaravelZero\Framework\Commands\Command;

class DefaultCommand extends Command
{
	use Colors;
	
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
			clear_screen();

			$this->newLine();

			$version = config('app.version');
			$context = $this->newContext();
			
			header("Clave {$version} session {$this->cyan($context->session_id)} in project {$this->cyan($context->project_name)}");

			$this->trap([SIGINT, SIGTERM], static fn() => $teardown($context));

			try {
				$setup($context);
				clear_screen();
				$agent($context);
			} finally {
				clear_screen();
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
		
		return new SessionContext(
			session_id: Str::random(8),
			project_name: basename($project_dir),
			project_dir: $project_dir,
			isolate: (bool) $this->option('isolate'),
			on_exit: OnExit::tryFrom($this->option('on-exit') ?? ''),
			command: $this,
		);
	}
}
