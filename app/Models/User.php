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
}
