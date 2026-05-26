<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'registration_enabled',
    'approval_mode',
    'default_new_user_status',
    'notes',
])]
class RegistrationPolicy extends Model
{
    protected function casts(): array
    {
        return [
            'registration_enabled' => 'boolean',
        ];
    }
}
