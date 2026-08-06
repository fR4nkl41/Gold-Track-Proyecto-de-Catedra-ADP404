<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'usuarios';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = ['nombre', 'correo', 'contrasena', 'rol', 'activo'];

    protected $hidden = ['contrasena'];

    protected $casts = [
        'created_at' => 'datetime',   // fix: evita "format() on string"
        'activo'     => 'boolean',
    ];
}
