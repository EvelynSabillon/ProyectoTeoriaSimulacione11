<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use HasFactory;

    protected $table = 'activity_log';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'accion',
        'modelo_afectado',
        'modelo_id',
        'descripcion',
        'datos_anteriores',
        'datos_nuevos',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'datos_anteriores' => 'array',
        'datos_nuevos' => 'array',
        'created_at' => 'datetime',
    ];

    // Relaciones
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopePorUsuario($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopePorAccion($query, $accion)
    {
        return $query->where('accion', $accion);
    }

    public function scopeRecientes($query, $dias = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($dias));
    }

    // Método estático para registrar actividad
    public static function registrar($accion, $modeloAfectado, $modeloId = null, $descripcion = null, $datosAnteriores = null, $datosNuevos = null)
    {
        return self::create([
            'user_id' => auth()->id(),
            'accion' => $accion,
            'modelo_afectado' => $modeloAfectado,
            'modelo_id' => $modeloId,
            'descripcion' => $descripcion,
            'datos_anteriores' => $datosAnteriores,
            'datos_nuevos' => $datosNuevos,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}