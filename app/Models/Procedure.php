<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Procedure extends Model
{
    protected $table = 'procedures';

    protected $fillable = [
        'boxes',
        'fileNumber',
        'archiveCode',
        'process_id',
        'user_id',
        'departament_id',
        'description',
        'digital',
        'electronic',
        'startDate',
        'endDate',
        'totalPages',
        'observation'
    ];

    protected $casts = [
        'digital' => 'boolean',
        'electronic' => 'boolean',
        'startDate' => 'date',
        'endDate' => 'date'
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
