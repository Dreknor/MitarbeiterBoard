<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = ['users_id', 'subscriptionable_type', 'subscriptionable_id'];

    /**
     * Get the owning subscriptionable model.
     */
    public function subscriptionable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'users_id');
    }
}
