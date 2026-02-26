<?php

namespace App\Data;

use Illuminate\Filesystem\Filesystem;

class ProjectConfig
{
	public static function fromProjectDir(string $project_dir, Filesystem $fs): static
	{
		$path = $project_dir.'/.clave.json';

		if (! $fs->exists($path)) {
			return new static();
		}

		$data = json_decode($fs->get($path), true);

		if (! is_array($data)) {
			return new static();
		}

		return new static(
			base_image: $data['base_image'] ?? null,
			provision: $data['provision'] ?? [],
		);
	}

	public function __construct(
		public readonly ?string $base_image = null,
		public readonly array $provision = [],
	) {
	}

	public function hasCustomizations(): bool
	{
		return $this->base_image !== null || $this->provision !== [];
	}

	public function baseVmName(): string
	{
		if (! $this->hasCustomizations()) {
			return config('clave.base_vm');
		}

		$hash = substr(md5(json_encode([
			'base_image' => $this->base_image,
			'provision' => $this->provision,
		])), 0, 8);

		return config('clave.base_vm')."-{$hash}";
	}
}
