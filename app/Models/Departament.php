<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Departament extends Model
{
    protected $primaryKey = 'id';
    protected $table = 'departaments';
    protected $fillable = ['name', 'abbreviation', 'classification_code', 'departament_id', 'authorized', 'active'];

    public function children()
    {
        return $this->hasMany(Departament::class, 'departament_id');
    }

    public function childrenRecursive()
    {
        return $this->children()
            ->where('active', true)
            ->with('childrenRecursive');
    }

    public function parent()
    {
        return $this->belongsTo(Departament::class, 'departament_id');
    }

    public function director()
    {
        return $this->hasOne(User::class, 'departament_id')
            ->where('role', 'director')  // Usamos el campo role directamente
            ->where('active', true);
    }
    public function users()
    {
        return $this->hasMany(User::class, 'departament_id');
    }
}
