<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['actor_subject', 'action', 'target_type', 'target_id', 'payload'])]
class AuditLog extends Model
{
    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }
}
