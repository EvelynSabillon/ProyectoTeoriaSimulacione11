<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'apellido',
        'email',
        'password',
        'rol',
        'activo',
        'telefono',
        'ultimo_acceso',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'activo' => 'boolean',
        'ultimo_acceso' => 'datetime',
    ];

    // Relaciones
    public function predictions()
    {
        return $this->hasMany(\App\Models\Prediction::class);
    }

    public function reports()
    {
        return $this->hasMany(\App\Models\Report::class);
    }

    public function activityLogs()
    {
        return $this->hasMany(\App\Models\ActivityLog::class);
    }

    // Scopes
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopePorRol($query, $rol)
    {
        return $query->where('rol', $rol);
    }

    // Métodos de Verificación de Rol
    public function esAdmin()
    {
        return $this->rol === 'admin';
    }

    public function esVeterinario()
    {
        return $this->rol === 'veterinario';
    }

    public function esAsistente()
    {
        return $this->rol === 'asistente';
    }

    public function puedeEditar()
    {
        return in_array($this->rol, ['admin', 'veterinario']);
    }

    public function puedeEliminar()
    {
        return $this->rol === 'admin';
    }

    // Accessors
    public function getNombreCompletoAttribute()
    {
        return $this->name . ' ' . ($this->apellido ?? '');
    }

    // Actualizar último acceso
    public function actualizarUltimoAcceso()
    {
        $this->ultimo_acceso = now();
        $this->save();
    }
}
