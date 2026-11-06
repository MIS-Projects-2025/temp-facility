<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
  /** @use HasFactory<\Database\Factories\UserFactory> */
  use HasFactory;

  protected $table = 'locations';
  public $timestamps = false;
  protected $fillable = [
    'location_name',
    'created_by',
    'modified_by',
  ];

  protected $casts = [
    'created_at'  => 'datetime',
    'modified_at'  => 'datetime',
  ];

  public function assets()
  {
    return $this->hasMany(Asset::class, 'location_id');
  }

  public function restrooms()
  {
    return $this->hasMany(Restroom::class, 'location_id');
  }

  protected static function booted()
  {
    static::deleting(function (Location $location) {
      $used = array_filter([
        'asset'    => $location->assets()->count(),
        'restroom' => $location->restrooms()->count(),
      ]);

      if (!$used) return;

      $parts = collect($used)->map(fn($n, $label) => "{$n} {$label}(s)")->implode(' and ');

      throw new DomainException(
        "Cannot delete \"{$location->location_name}\": still used by {$parts}. Reassign them first."
      );
    });
  }
}
