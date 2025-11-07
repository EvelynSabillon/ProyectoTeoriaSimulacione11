<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class IatfRecord extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'animal_id',
        'semental_id',
        'fecha_iatf',
        'fecha_protocolo_dia_0',
        'fecha_protocolo_dia_8',
        'fecha_protocolo_dia_9',
        'fecha_protocolo_dia_10',
        'condicion_ovarica_od',
        'condicion_ovarica_oi',
        'tono_uterino',
        'tratamiento_previo',
        'dias_tonificacion',
        'sal_mineral_gr',
        'modivitasan_ml',
        'fosfoton_ml',
        'seve_ml',
        'desparasitacion_previa',
        'vitaminas_aplicadas',
        'dispositivo_dib',
        'estradiol_ml',
        'retirada_dib',
        'ecg_ml',
        'pf2_alpha_ml',
        'hora_iatf',
        'epoca_anio',
        'temperatura_ambiente',
        'humedad_relativa',
        'estres_manejo',
        'calidad_pasturas',
        'disponibilidad_agua',
        'gestacion_previa',
        'dias_gestacion_previa',
        'resultado_iatf',
        'prenez_confirmada',
        'fecha_confirmacion',
        'dias_gestacion_confirmada',
        'observaciones',
    ];

    protected $casts = [
        'fecha_iatf' => 'date',
        'fecha_confirmacion' => 'date',
        'fecha_protocolo_dia_0' => 'datetime',
        'fecha_protocolo_dia_8' => 'datetime',
        'fecha_protocolo_dia_9' => 'datetime',
        'fecha_protocolo_dia_10' => 'datetime',
        'hora_iatf' => 'datetime:H:i',
        'desparasitacion_previa' => 'boolean',
        'vitaminas_aplicadas' => 'boolean',
        'dispositivo_dib' => 'boolean',
        'retirada_dib' => 'boolean',
        'gestacion_previa' => 'boolean',
        'prenez_confirmada' => 'boolean',
        'tono_uterino' => 'decimal:2',
        'dias_tonificacion' => 'integer',
        'sal_mineral_gr' => 'decimal:2',
        'modivitasan_ml' => 'decimal:2',
        'fosfoton_ml' => 'decimal:2',
        'seve_ml' => 'decimal:2',
        'estradiol_ml' => 'decimal:2',
        'ecg_ml' => 'decimal:2',
        'pf2_alpha_ml' => 'decimal:2',
        'temperatura_ambiente' => 'decimal:2',
        'humedad_relativa' => 'decimal:2',
        'estres_manejo' => 'integer',
        'calidad_pasturas' => 'integer',
        'dias_gestacion_previa' => 'integer',
        'dias_gestacion_confirmada' => 'integer',
    ];

    // Relaciones
    public function animal()
    {
        return $this->belongsTo(Animal::class);
    }

    public function semental()
    {
        return $this->belongsTo(Semental::class);
    }

    public function prediction()
    {
        return $this->hasOne(Prediction::class);
    }

    // Scopes
    public function scopeConfirmadas($query)
    {
        return $query->where('resultado_iatf', 'confirmada');
    }

    public function scopePendientes($query)
    {
        return $query->where('resultado_iatf', 'pendiente');
    }

    public function scopeEntreFechas($query, $inicio, $fin)
    {
        return $query->whereBetween('fecha_iatf', [$inicio, $fin]);
    }

    // Accessors
    public function getResultadoLabelAttribute()
    {
        $labels = [
            'confirmada' => 'Confirmada/Gestante',
            'no_prenada' => 'No Preñada/Vacía',
            'muerte_embrionaria' => 'Muerte Embrionaria',
            'pendiente' => 'Pendiente',
        ];
        return $labels[$this->resultado_iatf] ?? $this->resultado_iatf;
    }
}