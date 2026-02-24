<?php

namespace App\Pipelines;

use App\Pipelines\Steps\BootVm;
use App\Pipelines\Steps\CloneVm;
use App\Pipelines\Steps\CreateWorktree;
use App\Pipelines\Steps\RunClaudeCode;

class ClaudeCodePipeline extends SessionPipeline
{
	protected function label(): string
	{
		return 'Starting session...';
	}

	protected function steps(): array
	{
		return [
			CreateWorktree::class,
			CloneVm::class,
			BootVm::class,
			RunClaudeCode::class,
		];
	}
}
