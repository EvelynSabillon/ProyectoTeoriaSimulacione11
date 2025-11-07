<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tipo_reporte',
        'fecha_inicio',
        'fecha_fin',
        'grupo_lote',
        'filtros_aplicados',
        'data_resultados',
        'total_animales',
        'total_iatf',
        'tasa_prenez',
        'tasa_muerte_embrionaria',
        'archivo_pdf',
        'archivo_excel',
        'observaciones',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_fin' => 'date',
        'filtros_aplicados' => 'array',
        'data_resultados' => 'array',
        'total_animales' => 'integer',
        'total_iatf' => 'integer',
        'tasa_prenez' => 'decimal:2',
        'tasa_muerte_embrionaria' => 'decimal:2',
    ];

    // Relaciones
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopePorTipo($query, $tipo)
    {
        return $query->where('tipo_reporte', $tipo);
    }

    public function scopeRecientes($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    // Accessors
    public function getTasaPrenezFormateadaAttribute()
    {
        return $this->tasa_prenez . '%';
    }

    public function getPeriodoAttribute()
    {
        if (!$this->fecha_inicio || !$this->fecha_fin) {
            return 'Sin período definido';
        }
        return $this->fecha_inicio->format('d/m/Y') . ' - ' . $this->fecha_fin->format('d/m/Y');
    }
}
