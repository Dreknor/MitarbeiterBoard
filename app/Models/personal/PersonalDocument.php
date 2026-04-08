<?php

namespace App\Models\personal;

use App\Enums\DocumentStatus;
use App\Enums\SyncStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class PersonalDocument extends Model implements Auditable
{
    use HasFactory, SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $table = 'pers_documents';

    protected $fillable = [
        'employe_id',
        'document_type_id',
        'title',
        'nextcloud_path',
        'nextcloud_file_id',
        'issue_date',
        'expiry_date',
        'reminder_days',
        'reminder_sent_at',
        'status',
        'sync_status',
        'notes',
        'uploaded_by',
    ];

    protected $casts = [
        'issue_date'       => 'date',
        'expiry_date'      => 'date',
        'reminder_sent_at' => 'datetime',
        'status'           => DocumentStatus::class,
        'sync_status'      => SyncStatus::class,
    ];

    // ---- Relationships ----

    public function employe(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employe_id');
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class, 'document_type_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}

