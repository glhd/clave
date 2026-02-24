<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;

class GitManager
{
	public function isRepo(string $path): bool
	{
		return Process::path($path)
			->run('git rev-parse --is-inside-work-tree')
			->successful();
	}

	public function currentBranch(string $path): string
	{
		return trim(
			Process::path($path)
				->run('git rev-parse --abbrev-ref HEAD')
				->throw()
				->output()
		);
	}

	public function createWorktree(string $repo_path, string $worktree_path, string $branch): mixed
	{
		return Process::path($repo_path)
			->run("git worktree add -b {$branch} {$worktree_path}")
			->throw();
	}

	public function removeWorktree(string $repo_path, string $worktree_path): mixed
	{
		return Process::path($repo_path)
			->run("git worktree remove --force {$worktree_path}");
	}

	public function mergeAndCleanWorktree(string $repo_path, string $worktree_path, string $branch, string $base_branch): void
	{
		Process::path($repo_path)
			->run("git checkout {$base_branch}")
			->throw();

		Process::path($repo_path)
			->run("git merge {$branch}")
			->throw();

		$this->removeWorktree($repo_path, $worktree_path);

		Process::path($repo_path)
			->run("git branch -d {$branch}");
	}

	public function ensureIgnored(string $repo_path, string $pattern): void
	{
		$gitignore_path = $repo_path.'/.gitignore';

		if (file_exists($gitignore_path)) {
			$contents = file_get_contents($gitignore_path);
			if (str_contains($contents, $pattern)) {
				return;
			}
		}

		file_put_contents(
			$gitignore_path,
			rtrim(file_get_contents($gitignore_path) ?? '')."\n{$pattern}\n"
		);
	}
}
