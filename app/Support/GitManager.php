<?php

namespace App\Support;

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
		$escaped_branch = escapeshellarg($branch);
		$escaped_path = escapeshellarg($worktree_path);

		return Process::path($repo_path)
			->run("git worktree add -b {$escaped_branch} {$escaped_path}")
			->throw();
	}

	public function removeWorktree(string $repo_path, string $worktree_path): mixed
	{
		$escaped_path = escapeshellarg($worktree_path);

		return Process::path($repo_path)
			->run("git worktree remove --force {$escaped_path}");
	}

	public function mergeAndCleanWorktree(string $repo_path, string $worktree_path, string $branch, string $base_branch): void
	{
		$escaped_branch = escapeshellarg($branch);
		$escaped_base = escapeshellarg($base_branch);

		Process::path($repo_path)
			->run("git checkout {$escaped_base}")
			->throw();

		Process::path($repo_path)
			->run("git merge {$escaped_branch}")
			->throw();

		$this->removeWorktree($repo_path, $worktree_path);

		Process::path($repo_path)
			->run("git branch -d {$escaped_branch}");
	}

	public function ensureIgnored(string $repo_path, string $pattern): void
	{
		$gitignore_path = $repo_path.'/.gitignore';
		$contents = file_exists($gitignore_path) ? file_get_contents($gitignore_path) : '';
		
		if (str_contains($contents, $pattern)) {
			return;
		}

		file_put_contents($gitignore_path, rtrim($contents)."\n{$pattern}\n");
	}
}
