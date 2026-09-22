<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Procedure_Category extends Model
{
    use HasFactory;
    protected $table = 'procedure_categories';
    protected $fillable = ['name', 'color'];
    protected $visible = ['name', 'color'];

    protected static function newFactory(): \Database\Factories\ProcedureCategoryFactory
    {
        return \Database\Factories\ProcedureCategoryFactory::new();
    }

    public function procedures()
    {
        return $this->hasMany(Procedure::class, 'category_id');
    }
}
