<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChecklistApprovalConfig extends Model
{
  protected $table = 'checklist_approval_config';

  protected $fillable = [
    'user_id',
    'level',
    'instance_type',
  ];

  protected $casts = [
    'level' => 'integer',
  ];

  public function employee()
  {
    return $this->belongsTo(Employee::class, 'user_id', 'EMPLOYID');
  }
}
