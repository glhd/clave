<?php

namespace App\Data;

class IdeContext
{
	public function __construct(
		public readonly int $port,
		public readonly string $ide_name = 'JetBrains',
	) {
	}
}
