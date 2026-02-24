<?php

namespace App\Pipelines;

use App\Dto\SessionContext;
use App\Pipelines\Steps\CheckClaudeAuthentication;
use App\Pipelines\Steps\EnsureVmExists;
use App\Pipelines\Steps\GetGitBranch;
use App\Pipelines\Steps\SaveSession;
use App\Pipelines\Steps\ValidateProject;
use Illuminate\Pipeline\Pipeline;

class PreflightPipeline implements HandlesSession
{
	public function __construct(
		protected Pipeline $pipeline,
	) {
	}
	
	public function handle(SessionContext $context): SessionContext
	{
		return $this->pipeline
			->send($context)
			->through([
				ValidateProject::class,
				GetGitBranch::class,
				EnsureVmExists::class,
				CheckClaudeAuthentication::class,
				SaveSession::class,
			])
			->thenReturn();
	}
}
