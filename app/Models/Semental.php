<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Semental extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sementales';

    protected $fillable = [
        'nombre',
        'raza',
        'codigo_pajilla',
        'calidad_seminal',
        'concentracion_espermatica',
        'morfologia_espermatica',
        'tasa_historica_prenez',
        'total_servicios',
        'total_preneces',
        'total_muertes_embrionarias',
        'proveedor',
        'fecha_adquisicion',
        'precio_pajilla',
        'activo',
        'observaciones',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'calidad_seminal' => 'decimal:2',
        'concentracion_espermatica' => 'decimal:2',
        'morfologia_espermatica' => 'decimal:2',
        'tasa_historica_prenez' => 'decimal:2',
        'total_servicios' => 'integer',
        'total_preneces' => 'integer',
        'total_muertes_embrionarias' => 'integer',
        'precio_pajilla' => 'decimal:2',
        'fecha_adquisicion' => 'date',
    ];

    // Relaciones
    public function iatfRecords()
    {
        return $this->hasMany(IatfRecord::class);
    }

    // Scopes
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    // Métodos para actualizar estadísticas
    public function actualizarEstadisticas()
    {
        $this->total_servicios = $this->iatfRecords()->count();
        $this->total_preneces = $this->iatfRecords()->where('resultado_iatf', 'confirmada')->count();
        $this->total_muertes_embrionarias = $this->iatfRecords()->where('resultado_iatf', 'muerte_embrionaria')->count();
        
        if ($this->total_servicios > 0) {
            $this->tasa_historica_prenez = ($this->total_preneces / $this->total_servicios) * 100;
        }
        
        $this->save();
    }
}
