<?php

namespace App\Models;

use App\Models\Checklist;
use Illuminate\Database\Eloquent\Model;

class ChemicalSDSMonitoringInstance extends Model
{
  protected $table = 'chemical_sds_monitoring_instances';
  public $timestamps = false;

  protected $casts = [
    'results' => 'array',
    'checked_at'   => 'datetime',
    'modified_at'  => 'datetime',
    'verified_at'  => 'datetime',
  ];

  protected $fillable = [
    'created_by',
    'notes',
  ];

  public function results()
  {
    return $this->hasMany(ChemicalSDS::class, 'chemical_sds_id');
  }

  public function creator()
  {
    return $this->belongsTo(Employee::class, 'created_by', 'EMPLOYID');
  }
}
