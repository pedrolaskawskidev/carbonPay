<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CarbonAnalysis extends Model
{
    protected $fillable = [
        'response_id', 'email', 'telefone', 'data',
        'area_cultivo_hectares', 'area_desmatamento_hectares',
        'cred_cultivo_min', 'cred_cultivo_max',
        'cred_evitado_min', 'cred_evitado_max',
        'cred_total_min', 'cred_total_max',
        'preco_credito', 'valor_anual_min', 'valor_anual_max',
        'observacoes',
    ];

    protected $casts = [
        'data' => 'array',
    ];
}
