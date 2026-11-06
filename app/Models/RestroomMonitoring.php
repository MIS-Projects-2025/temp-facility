<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RestroomMonitoring extends Model
{
  protected $table = 'restroom_monitoring';
  public $timestamps = false;

  protected $fillable = [
    'restroom_id',
    'fixture_id',
    'checked_by',
    'restroom_monitoring_instance_id',
    'status',
    'remarks',
  ];

  public function restroom()
  {
    return $this->belongsTo(Restroom::class, 'restroom_id', 'id');
  }

  public function fixture()
  {
    return $this->belongsTo(Fixture::class, 'fixture_id', 'id');
  }

  protected $casts = [
    'created_at'  => 'datetime',
    'updated_at'  => 'datetime',
  ];
}
