<?php

namespace App\Pipelines\Steps;

use App\Facades\Progress;

trait ProvidesProgressHints
{
	protected function hint(string $message): void
	{
		Progress::hint($message);
	}
}
