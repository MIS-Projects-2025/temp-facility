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
    'location',
  ];

  public function checklist()
  {
    return $this->belongsTo(Checklist::class, 'checklist_id');
  }

  public function location()
  {
    return $this->belongsTo(Location::class, 'location');
  }
}
