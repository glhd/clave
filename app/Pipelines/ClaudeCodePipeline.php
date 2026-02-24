<?php

namespace App\Pipelines;

use App\Dto\SessionContext;
use App\Pipelines\Steps\BootVm;
use App\Pipelines\Steps\CloneVm;
use App\Pipelines\Steps\CreateWorktree;
use App\Pipelines\Steps\RunClaudeCode;
use Illuminate\Pipeline\Pipeline;

class ClaudeCodePipeline implements HandlesSession
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
				CreateWorktree::class,
				CloneVm::class,
				BootVm::class,
				RunClaudeCode::class,
			])
			->thenReturn();
	}
}
