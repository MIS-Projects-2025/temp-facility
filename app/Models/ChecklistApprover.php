<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChecklistApprover extends Model
{
  protected $table = 'checklist_approvers';

  protected $fillable = [
    'instance_id',
    'instance_type',
    'user_id',
    'level',
    'status',
    'decided_at',
    'remarks',
  ];

  protected $casts = [
    'decided_at' => 'datetime',
    'level'      => 'integer',
  ];

  public $timestamps = false;

  public function instance()
  {
    return $this->belongsTo(ChecklistInstance::class, 'instance_id');
  }

  public function employee()
  {
    return $this->belongsTo(Employee::class, 'user_id', 'EMPLOYID');
  }
}
