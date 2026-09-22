<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaedDiaryCategory extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'color', 'user_id'];

    // Bestehend
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // NEU: Relationship zu Entries
    public function entries()
    {
        return $this->hasMany(PaedDiaryEntry::class, 'category_id');
    }

    // NEU: Pivot – welche User haben diese Kategorie ausgeblendet
    public function hiddenByUsers()
    {
        return $this->belongsToMany(
            User::class,
            'paed_diary_user_hidden_categories',
            'paed_diary_category_id',
            'user_id'
        );
    }

    // NEU: Scope – nur globale Kategorien (user_id = null)
    public function scopeGlobal($query)
    {
        return $query->whereNull('user_id');
    }

    // NEU: Scope – für einen bestimmten User sichtbare Kategorien (global + eigene)
    public function scopeForUser($query, int $userId)
    {
        return $query->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $userId));
    }

    // NEU: Helper
    public function isGlobal(): bool
    {
        return is_null($this->user_id);
    }

    public function isOwnedBy(int $userId): bool
    {
        return $this->user_id === $userId;
    }
}
