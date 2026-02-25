<?php

namespace App\Pipelines;

use App\Pipelines\Steps\BootVm;
use App\Pipelines\Steps\CheckClaudeAuthentication;
use App\Pipelines\Steps\CloneVm;
use App\Pipelines\Steps\CloneRepo;
use App\Pipelines\Steps\DetectRecipe;
use App\Pipelines\Steps\EnsureVmExists;
use App\Pipelines\Steps\GetGitBranch;
use App\Pipelines\Steps\RunClaudeCode;
use App\Pipelines\Steps\SaveSession;

class SessionSetup extends SessionPipeline
{
	protected function label(): string
	{
		return 'Starting session...';
	}

	protected function steps(): array
	{
		return [
			DetectRecipe::class,
			GetGitBranch::class,
			EnsureVmExists::class,
			CheckClaudeAuthentication::class,
			SaveSession::class,
			CloneRepo::class,
			CloneVm::class,
			BootVm::class,
			RunClaudeCode::class,
		];
	}
}
