<?php

namespace App\Facades;

use App\Support\ClaveProgress;
use Illuminate\Support\Facades\Facade;

class Progress extends Facade
{
	protected static function getFacadeAccessor()
	{
		return ClaveProgress::class;
	}
}
