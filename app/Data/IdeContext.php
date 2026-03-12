<?php

namespace App\Data;

class IdeContext
{
	public function __construct(
		public readonly int $port,
		public readonly string $ide_name = 'JetBrains',
		public readonly string $transport = 'ws',
		public readonly ?string $auth_token = null,
	) {
	}
}
