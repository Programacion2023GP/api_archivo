<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Proccess extends Model
{
    protected $primaryKey = 'id';
    protected $table = 'proccess';
    protected $fillable = ['name','at','ac', 'description', 'departament_id', 'classification_code', 'proccess_id', 'active'];
    public function children()
    {
        return $this->hasMany(Proccess::class, 'proccess_id');
    }

    public function childrenRecursive()
    {
        return $this->children()
            ->with('childrenRecursive');
    }
}
