<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WpTask extends Model
{
    use HasFactory;

    protected $table = 'wp_tasks';
    protected $visible = ['task'];
    protected $fillable = ['task'];

    public function wprow(){
        return $this->belongsTo(WPRows::class, 'wprow_id');
    }
}
