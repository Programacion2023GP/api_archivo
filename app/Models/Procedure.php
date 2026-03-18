<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Procedure extends Model
{
    protected $table = 'procedures';

    protected $fillable = [
        'year',

        'boxes',
        'process_id',
        // 'fileNumber',
        // 'archiveCode',
        'user_id',
        'departament_id',
        'startDate',
        'endDate',
        'description',
        'electronic',
        'totalPages',
        'observation',
        'administrative_value',
        'accounting_fiscal_value',
        'legal_value',
        'retention_period_current',
        'retention_period_archive',
        'location_building',
        'location_furniture',
        'location_position',
        'errorFieldsKey',
        'errorDescriptionField',    
        'fisic',
        'status_id',
        'error',

    ];

    protected $casts = [
        'digital' => 'boolean',
        'status_id' => 'number',
        'fisic' => 'boolean',
        'error' => 'boolean',

        'electronic' => 'boolean',
        'administrative_value' => 'boolean',
        'accounting_fiscal_value' => 'boolean',
        'legal_value' => 'boolean',
       
        'location_building' => 'boolean',
        'location_position' => 'boolean',
        'location_furniture' => 'boolean',

        'startDate' => 'date:Y-m-d',
        'endDate' => 'date:Y-m-d',
    ];
    // public function process()
    // {
    //     return $this->belongsTo(Process::class);
    // }

    // public function user()
    // {
    //     return $this->belongsTo(User::class);
    // }

    // public function departament()
    // {
    //     return $this->belongsTo(Departament::class);
    // }
}
