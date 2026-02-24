<?php

namespace App\Data;

use App\Support\EnumHelpers;

enum Recipe: string
{
	use EnumHelpers;

	case Laravel = 'laravel';
	case Unknown = 'unknown';
}
