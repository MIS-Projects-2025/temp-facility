<?php

namespace App\Models;

use App\Models\Checklist;
use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
  protected $table = 'assets';
  public $timestamps = false;

  protected $fillable = [
    'checklist_id',
    'properties',
    'code',
    'location_id',
    'modified_by',
    'modified_at',
  ];

  protected $casts = [
    'properties' => 'array',
  ];

  public function location()
  {
    return $this->belongsTo(Location::class, 'location_id');
  }
}
