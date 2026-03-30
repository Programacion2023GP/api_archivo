<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SignaturesProcedure extends Model
{
    protected $table = 'signatures_procedure';
    protected $fillable = [
        'signedBy',
        'user_id',
        'procedure_id'

    ];
}
