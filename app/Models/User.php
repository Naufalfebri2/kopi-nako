<?php

namespace App\Models;

use App\Models\Employee;
use App\Models\Role;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $table = 'users';
    public $timestamps = false;

    protected $fillable = ['name', 'email', 'password', 'id_role'];
    protected $hidden = ['password'];
    protected $casts = ['password' => 'hashed'];

    public function role()
    {
        return $this->belongsTo(Role::class, 'id_role');
    }

    public function employee()
    {
        return $this->hasOne(Employee::class, 'id_user');
    }

    // Inisial nama untuk avatar (contoh: "Supervisor" jadi "S", "Rizky Ramadhan" jadi "RR")
    public function getInitialsAttribute(): string
    {
        return collect(explode(' ', trim($this->name)))
            ->filter()
            ->take(2)
            ->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))
            ->implode('');
    }
}