<?php

namespace App\Pipelines;

use App\Pipelines\Steps\CheckClaudeAuthentication;
use App\Pipelines\Steps\CheckForUpdates;
use App\Pipelines\Steps\EnsureVmExists;
use App\Pipelines\Steps\GetGitBranch;
use App\Pipelines\Steps\SaveSession;
use App\Pipelines\Steps\ValidateProject;

class PreflightPipeline extends SessionPipeline
{
	protected function label(): string
	{
		return 'Setting up project...';
	}
	
	protected function steps(): array
	{
		return [
			CheckForUpdates::class,
			ValidateProject::class,
			GetGitBranch::class,
			EnsureVmExists::class,
			CheckClaudeAuthentication::class,
			SaveSession::class,
		];
	}
}
