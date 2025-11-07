<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Grupo extends Model
{
    use HasFactory;

    protected $table = 'grupos';

    protected $fillable = [
        'nombre',
        'descripcion',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    // Relaciones
    public function animals()
    {
        return $this->hasMany(Animal::class, 'grupo_id');
    }

    // Scopes
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    // Accessors
    public function getTotalAnimalesAttribute()
    {
        return $this->animals()->count();
    }

    public function getTotalAnimalesActivosAttribute()
    {
        return $this->animals()->where('activo', true)->count();
    }
}
