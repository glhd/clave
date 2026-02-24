<?php

namespace App\Support;

class UpdateChecker
{
	protected const GITHUB_REPO = 'glhd/clave';

	protected const CACHE_TTL = 86400;

	public static function cachePath(): string
	{
		return ($_SERVER['HOME'] ?? getenv('HOME')).'/.config/clave/update-check.json';
	}

	public function check(string $current_version): ?string
	{
		if ($current_version === 'unreleased') {
			return null;
		}

		$latest = $this->getLatestVersion();

		if ($latest === null) {
			return null;
		}

		if (version_compare($this->normalize($latest), $this->normalize($current_version), '>')) {
			return $latest;
		}

		return null;
	}

	public function getLatestVersion(): ?string
	{
		$cached = $this->getCached();

		if ($cached !== null) {
			return $cached;
		}

		return $this->fetchLatestVersion();
	}

	protected function fetchLatestVersion(): ?string
	{
		$url = 'https://api.github.com/repos/'.static::GITHUB_REPO.'/releases/latest';

		$context = stream_context_create([
			'http' => [
				'method' => 'GET',
				'header' => "User-Agent: clave-cli\r\nAccept: application/vnd.github+json\r\n",
				'timeout' => 3,
			],
		]);

		try {
			$response = @file_get_contents($url, false, $context);
		} catch (\Throwable) {
			return null;
		}

		if ($response === false) {
			return null;
		}

		$data = json_decode($response, true);

		if (! is_array($data) || ! isset($data['tag_name'])) {
			return null;
		}

		$version = $data['tag_name'];

		$this->writeCache($version);

		return $version;
	}

	protected function getCached(): ?string
	{
		$cache_path = static::cachePath();

		if (! file_exists($cache_path)) {
			return null;
		}

		$data = json_decode(file_get_contents($cache_path), true);

		if (! is_array($data) || ! isset($data['version'], $data['checked_at'])) {
			return null;
		}

		if (time() - $data['checked_at'] > static::CACHE_TTL) {
			return null;
		}

		return $data['version'];
	}

	protected function writeCache(string $version): void
	{
		$cache_path = static::cachePath();
		$dir = dirname($cache_path);

		if (! is_dir($dir)) {
			mkdir($dir, 0755, true);
		}

		file_put_contents($cache_path, json_encode([
			'version' => $version,
			'checked_at' => time(),
		]));
	}

	protected function normalize(string $version): string
	{
		return ltrim($version, 'vV');
	}
}
