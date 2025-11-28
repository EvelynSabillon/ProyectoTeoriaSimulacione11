<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

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

    // ========================================================================
    // RELACIONES
    // ========================================================================

    /**
     * Un semental puede tener muchos registros IATF
     */
    public function iatfRecords()
    {
        return $this->hasMany(IatfRecord::class);
    }

    // ========================================================================
    // SCOPES
    // ========================================================================

    /**
     * Scope para sementales activos
     */
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Scope para sementales con tasa de preñez mayor a un valor
     */
    public function scopeConTasaMayorA($query, $tasa)
    {
        return $query->where('tasa_historica_prenez', '>=', $tasa);
    }

    /**
     * Scope para sementales con buenos resultados (>= 60%)
     */
    public function scopeBuenosResultados($query)
    {
        return $query->where('tasa_historica_prenez', '>=', 60);
    }

    // ========================================================================
    // MÉTODOS DE ESTADÍSTICAS
    // ========================================================================

    /**
     * Actualizar estadísticas del semental
     * MEJORADO: Ahora considera prenez_confirmada (boolean) además de resultado_iatf
     */
    public function actualizarEstadisticas()
    {
        try {
            // Contar total de servicios con resultado confirmado
            $this->total_servicios = $this->iatfRecords()
                ->whereNotNull('prenez_confirmada') // Solo los que tienen resultado
                ->count();

            // Opción 1: Usar campo prenez_confirmada (boolean) - RECOMENDADO
            $this->total_preneces = $this->iatfRecords()
                ->where('prenez_confirmada', true)
                ->count();

            // Opción 2: Usar campo resultado_iatf (string) - ALTERNATIVO
            // Descomentar si prefieres usar este campo
            /*
            $this->total_preneces = $this->iatfRecords()
                ->where('resultado_iatf', 'confirmada')
                ->count();
            */

            // Contar muertes embrionarias
            $this->total_muertes_embrionarias = $this->iatfRecords()
                ->where('resultado_iatf', 'muerte_embrionaria')
                ->count();

            // Calcular tasa de preñez
            if ($this->total_servicios > 0) {
                $this->tasa_historica_prenez = round(
                    ($this->total_preneces / $this->total_servicios) * 100, 
                    2
                );
            } else {
                // Si no hay servicios confirmados, mantener valor por defecto
                if (is_null($this->tasa_historica_prenez)) {
                    $this->tasa_historica_prenez = 50.00; // Valor conservador
                }
            }

            // Guardar sin disparar eventos (evitar recursión)
            $this->saveQuietly();

            Log::info("Estadísticas actualizadas para semental: {$this->nombre}", [
                'semental_id' => $this->id,
                'total_servicios' => $this->total_servicios,
                'total_preneces' => $this->total_preneces,
                'tasa_prenez' => $this->tasa_historica_prenez,
            ]);

            return $this;

        } catch (\Exception $e) {
            Log::error("Error actualizando estadísticas del semental {$this->nombre}", [
                'semental_id' => $this->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Obtener tasa de preñez calculada en tiempo real (sin guardar)
     * Útil para verificar sin modificar la BD
     */
    public function calcularTasaPrenezActual()
    {
        $totalServicios = $this->iatfRecords()
            ->whereNotNull('prenez_confirmada')
            ->count();

        if ($totalServicios === 0) {
            return null;
        }

        $totalPreneces = $this->iatfRecords()
            ->where('prenez_confirmada', true)
            ->count();

        return round(($totalPreneces / $totalServicios) * 100, 2);
    }

    // ========================================================================
    // ACCESSORS (ATRIBUTOS CALCULADOS)
    // ========================================================================

    /**
     * Obtener texto descriptivo de calidad del semental
     */
    public function getCalidadTextoAttribute()
    {
        if (is_null($this->tasa_historica_prenez)) {
            return 'Sin datos';
        }

        if ($this->tasa_historica_prenez >= 70) {
            return 'Excelente';
        } elseif ($this->tasa_historica_prenez >= 60) {
            return 'Muy Buena';
        } elseif ($this->tasa_historica_prenez >= 50) {
            return 'Buena';
        } elseif ($this->tasa_historica_prenez >= 40) {
            return 'Regular';
        } else {
            return 'Baja';
        }
    }

    /**
     * Obtener color para UI basado en tasa de preñez
     */
    public function getColorCalidadAttribute()
    {
        if (is_null($this->tasa_historica_prenez)) {
            return 'gray';
        }

        if ($this->tasa_historica_prenez >= 70) {
            return 'green';
        } elseif ($this->tasa_historica_prenez >= 60) {
            return 'blue';
        } elseif ($this->tasa_historica_prenez >= 50) {
            return 'yellow';
        } elseif ($this->tasa_historica_prenez >= 40) {
            return 'orange';
        } else {
            return 'red';
        }
    }

    /**
     * Verificar si el semental es apto para IATF (tasa >= 40%)
     */
    public function getEsAptoAttribute()
    {
        return $this->tasa_historica_prenez >= 40;
    }

    /**
     * Verificar si el semental es recomendado (tasa >= 60%)
     */
    public function getEsRecomendadoAttribute()
    {
        return $this->tasa_historica_prenez >= 60;
    }

    // ========================================================================
    // MÉTODOS BOOT
    // ========================================================================

    protected static function boot()
    {
        parent::boot();

        // Al crear un semental nuevo, establecer valores por defecto
        static::creating(function ($semental) {
            if (is_null($semental->tasa_historica_prenez)) {
                $semental->tasa_historica_prenez = 50.00; // Valor conservador
            }

            if (is_null($semental->total_servicios)) {
                $semental->total_servicios = 0;
            }

            if (is_null($semental->total_preneces)) {
                $semental->total_preneces = 0;
            }

            if (is_null($semental->total_muertes_embrionarias)) {
                $semental->total_muertes_embrionarias = 0;
            }
        });
    }

    // ========================================================================
    // MÉTODOS HELPER
    // ========================================================================

    /**
     * Verificar si necesita actualización de estadísticas
     * (tiene servicios sin reflejar en las estadísticas)
     */
    public function necesitaActualizacion()
    {
        $serviciosReales = $this->iatfRecords()
            ->whereNotNull('prenez_confirmada')
            ->count();

        return $serviciosReales != $this->total_servicios;
    }

    /**
     * Obtener resumen de estadísticas
     */
    public function getResumenEstadisticas()
    {
        return [
            'total_servicios' => $this->total_servicios,
            'total_preneces' => $this->total_preneces,
            'total_vacias' => $this->total_servicios - $this->total_preneces,
            'total_muertes_embrionarias' => $this->total_muertes_embrionarias,
            'tasa_prenez' => $this->tasa_historica_prenez,
            'calidad' => $this->calidad_texto,
            'es_apto' => $this->es_apto,
            'es_recomendado' => $this->es_recomendado,
        ];
    }

    /**
     * Obtener sementales recomendados para IATF (ordenados por tasa)
     */
    public static function obtenerRecomendados($limite = 5)
    {
        return static::activos()
            ->whereNotNull('tasa_historica_prenez')
            ->where('tasa_historica_prenez', '>=', 60)
            ->orderBy('tasa_historica_prenez', 'desc')
            ->limit($limite)
            ->get();
    }
}