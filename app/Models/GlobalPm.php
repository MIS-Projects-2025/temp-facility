<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Model;

class GlobalPm extends Model
{
  protected $table = 'global_pm';
  public $timestamps = false;

  protected $fillable = [
    'maintenance_name',
    'maintenance_description',
    'modified_by',
  ];

  protected $casts = [
    'modified_at'  => 'datetime',
  ];

  public function latestPmHistory()
  {
    return $this->hasOne(GlobalPmHistory::class)->latestOfMany('done_date');
  }

  public function histories()
  {
    return $this->hasMany(GlobalPmHistory::class, 'global_pm_id');
  }

  public function schedule()
  {
    return $this->hasOne(GlobalPMSchedule::class, 'global_pm_id');
  }

  protected static function booted()
  {
    static::deleting(function (GlobalPm $pm) {
      if ($pm->histories()->exists()) {
        throw new DomainException(
          "Cannot delete \"{$pm->maintenance_name}\": it already has PM history."
        );
      }

      $pm->schedule()->delete();
    });
  }
}
