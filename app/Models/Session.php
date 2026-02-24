<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Session extends Model
{
	protected $fillable = [
		'session_id',
		'project_dir',
		'project_name',
		'branch',
		'vm_name',
		'port',
		'proxy_name',
		'pid',
		'started_at',
	];

	protected function casts(): array
	{
		return [
			'started_at' => 'datetime',
		];
	}
}
