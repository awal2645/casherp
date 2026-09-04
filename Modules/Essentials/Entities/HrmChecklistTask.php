<?php

namespace Modules\Essentials\Entities;

use Illuminate\Database\Eloquent\Model;

class HrmChecklistTask extends Model
{
    protected $table = 'hrm_checklist_tasks';
    protected $guarded = ['id'];
    protected $casts = ['due_date' => 'date', 'completed_at' => 'datetime'];

    public function checklist() { return $this->belongsTo(HrmChecklist::class, 'checklist_id'); }
}
