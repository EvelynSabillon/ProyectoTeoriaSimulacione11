<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Prediction extends Model
{
    use HasFactory;

    protected $fillable = [
        'iatf_record_id',
        'user_id',
        'probabilidad_prenez',
        'prediccion_binaria',
        'nivel_confianza',
        'confianza_baja',
        'confianza_alta',
        'modelo_usado',
        'version_modelo',
        'accuracy',
        'precision',
        'recall',
        'f1_score',
        'roc_auc',
        'top_features',
        'recomendaciones',
        'resultado_real',
        'prediccion_correcta',
        'fecha_validacion',
    ];

    protected $casts = [
        'prediccion_binaria' => 'boolean',
        'resultado_real' => 'boolean',
        'prediccion_correcta' => 'boolean',
        'probabilidad_prenez' => 'decimal:4',
        'confianza_baja' => 'decimal:4',
        'confianza_alta' => 'decimal:4',
        'accuracy' => 'decimal:4',
        'precision' => 'decimal:4',
        'recall' => 'decimal:4',
        'f1_score' => 'decimal:4',
        'roc_auc' => 'decimal:4',
        'top_features' => 'array',
        'fecha_validacion' => 'date',
    ];

    // Relaciones
    public function iatfRecord()
    {
        return $this->belongsTo(IatfRecord::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeAltoRiesgo($query)
    {
        return $query->where('probabilidad_prenez', '<', 0.4);
    }

    public function scopeAltaProbabilidad($query)
    {
        return $query->where('probabilidad_prenez', '>=', 0.7);
    }

    public function scopeValidadas($query)
    {
        return $query->whereNotNull('resultado_real');
    }

    // Accessors
    public function getProbabilidadPorcentajeAttribute()
    {
        return round($this->probabilidad_prenez * 100, 2) . '%';
    }

    public function getNivelRiesgoAttribute()
    {
        if ($this->probabilidad_prenez >= 0.7) return 'Bajo riesgo';
        if ($this->probabilidad_prenez >= 0.4) return 'Riesgo moderado';
        return 'Alto riesgo';
    }

    // Método para validar predicción
    public function validarConResultadoReal($resultadoReal)
    {
        $this->resultado_real = $resultadoReal;
        $this->prediccion_correcta = ($this->prediccion_binaria == $resultadoReal);
        $this->fecha_validacion = now();
        $this->save();
    }
}
