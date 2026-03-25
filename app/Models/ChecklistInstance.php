<?php

namespace App\Models;

use App\Models\Checklist;
use Illuminate\Database\Eloquent\Model;

class ChecklistInstance extends Model
{
  protected $table = 'checklist_instances';
  public $timestamps = false;

  protected $casts = [
    'results' => 'array',
    'created_at' => 'datetime',
    'verified_at' => 'datetime',
  ];

  protected $fillable = [
    'checklist_id',
    'created_by',
    'notes',
    'verified_at',
    'verified_by',
    'submission_type',
  ];

  public function results()
  {
    return $this->hasMany(ChecklistItemResult::class, 'checklist_instance_id');
  }

  public function lateResults()
  {
    return $this->hasMany(ChecklistItemResult::class, 'checklist_instance_id')
      ->whereColumn('checked_at', '>', 'period_end');
  }

  public function checklist()
  {
    return $this->belongsTo(Checklist::class, 'checklist_id');
  }

  public function verifier()
  {
    return $this->belongsTo(Employee::class, 'verified_by', 'EMPLOYID');
  }

  public function creator()
  {
    return $this->belongsTo(Employee::class, 'created_by', 'EMPLOYID');
  }

  public function approver()
  {
    return $this->belongsTo(Employee::class, 'approved_by', 'EMPLOYID');
  }

  public function pendingApprovers()
  {
    return $this->hasMany(ChecklistApprover::class, 'instance_id')
      ->where('instance_type', 'checklist')
      ->where('status', 'pending')
      ->whereNotIn('level', function ($q) {
        $q->select('level')
          ->from('checklist_approvers as sub')
          ->where('sub.instance_type', 'checklist')
          ->where('sub.status', 'approved')
          ->whereColumn('sub.instance_id', 'checklist_approvers.instance_id');
      })
      ->orderBy('level');
  }

  public function allApprovers()
  {
    return $this->hasMany(ChecklistApprover::class, 'instance_id')
      ->where('instance_type', 'checklist')
      ->orderBy('level')
      ->orderBy('user_id');
  }
}
