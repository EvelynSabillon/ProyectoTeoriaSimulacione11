<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Animal extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'arete',
        'grupo_id',
        'grupo_lote',
        'edad_meses',
        'peso_kg',
        'condicion_corporal',
        'numero_partos',
        'dias_posparto',
        'dias_abiertos',
        'historial_abortos',
        'numero_abortos',
        'enfermedades_reproductivas',
        'descripcion_enfermedades',
        'estado_reproductivo',
        'fecha_ultimo_tratamiento',
        'activo',
        'observaciones',
    ];

    protected $casts = [
        'historial_abortos' => 'boolean',
        'enfermedades_reproductivas' => 'boolean',
        'activo' => 'boolean',
        'edad_meses' => 'integer',
        'peso_kg' => 'decimal:2',
        'condicion_corporal' => 'decimal:1',
        'numero_partos' => 'integer',
        'dias_posparto' => 'integer',
        'dias_abiertos' => 'integer',
        'numero_abortos' => 'integer',
        'fecha_ultimo_tratamiento' => 'datetime',
    ];

    // Relaciones
    public function grupo()
    {
        return $this->belongsTo(Grupo::class);
    }

    public function iatfRecords()
    {
        return $this->hasMany(IatfRecord::class);
    }

    public function ultimoIatf()
    {
        return $this->hasOne(IatfRecord::class)->latestOfMany();
    }

    // Scopes
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopePorGrupo($query, $grupoId)
    {
        return $query->where('grupo_id', $grupoId);
    }

    public function scopePorEstado($query, $estado)
    {
        return $query->where('estado_reproductivo', $estado);
    }

    // Accessors
    public function getEdadAniosAttribute()
    {
        return $this->edad_meses ? round($this->edad_meses / 12, 1) : null;
    }

    public function getEstadoReproductivoLabelAttribute()
    {
        $labels = [
            'activa' => 'Activa',
            'prenada' => 'Preñada',
            'seca' => 'Seca',
            'descarte' => 'Descarte',
        ];
        return $labels[$this->estado_reproductivo] ?? $this->estado_reproductivo;
    }
}
