<?php

namespace App\Pipelines\Steps;

use function Laravel\Prompts\note;

trait ProvidesProgressHints
{
	protected function hint(string $message): void
	{
		note($message);
	}
}
