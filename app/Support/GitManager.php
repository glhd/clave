<?php

namespace App\Support;

use Illuminate\Support\Facades\Process;
use InvalidArgumentException;

class GitManager
{
	public function isRepo(string $path): bool
	{
		return Process::path($path)
			->run(['git', 'rev-parse', '--is-inside-work-tree'])
			->successful();
	}

	public function currentBranch(string $path): string
	{
		return trim(
			Process::path($path)
				->run(['git', 'rev-parse', '--abbrev-ref', 'HEAD'])
				->throw()
				->output()
		);
	}

	public function cloneLocal(string $repo_path, string $clone_path, string $base_branch, string $clone_branch): mixed
	{
		Process::path($repo_path)
			->run(['git', 'clone', '--local', '--branch', $base_branch, '.', $clone_path])
			->throw();

		return Process::path($clone_path)
			->run(['git', 'checkout', '-b', $clone_branch])
			->throw();
	}

	public function removeClone(string $clone_path): true
	{
		$real_path = realpath($clone_path);
		$repos_dir = rtrim($_SERVER['HOME'], '/').'/.clave/repos';

		if ($real_path === false || ! str_starts_with($real_path, $repos_dir.'/')) {
			throw new InvalidArgumentException("The path '{$clone_path}' is outside of '{$repos_dir}'");
		}

		Process::run(['rm', '-rf', $real_path]);
		
		return true;
	}

	public function hasChanges(string $clone_path, string $base_branch): bool
	{
		$escaped_base = escapeshellarg($base_branch);

		$has_uncommitted = trim(
			Process::path($clone_path)->run('git status --porcelain')->output()
		) !== '';

		$has_commits = trim(
			Process::path($clone_path)->run("git log {$escaped_base}..HEAD --oneline")->output()
		) !== '';

		return $has_uncommitted || $has_commits;
	}

	public function commitAllChanges(string $clone_path, string $message): bool
	{
		Process::path($clone_path)->run(['git', 'add', '-A']);

		$status = Process::path($clone_path)->run(['git', 'status', '--porcelain']);

		if (trim($status->output()) === '') {
			return false;
		}

		Process::path($clone_path)
			->run(['git', 'commit', '-m', $message])
			->throw();

		return true;
	}

	public function mergeAndCleanClone(string $repo_path, string $clone_path, string $clone_branch, string $base_branch): true
	{
		$this->commitAllChanges($clone_path, 'WIP');

		Process::path($repo_path)
			->run(['git', 'fetch', $clone_path, $clone_branch])
			->throw();

		Process::path($repo_path)
			->run(['git', 'checkout', $base_branch])
			->throw();

		Process::path($repo_path)
			->run(['git', 'merge', 'FETCH_HEAD'])
			->throw();

		$this->removeClone($clone_path);
		
		return true;
	}
}
