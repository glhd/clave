<?php

namespace App;

use App\Prompts\Header;

function header(string $header)
{
	(new Header($header))->display();
}
