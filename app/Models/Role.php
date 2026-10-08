<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    public const KARYAWAN = 'Karyawan';
    public const LEADER = 'Leader/Head';
    public const SUPERVISOR = 'Supervisor';

    protected $table = 'roles';
    public $timestamps = false;
    protected $fillable = ['name'];

    public function users()
    {
        return $this->hasMany(User::class, 'id_role');
    }
}