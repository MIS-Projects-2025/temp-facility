<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Chemicals extends Model
{
  protected $table = 'chemicals';

  public $timestamps = false;

  protected $casts = [
    'modified_at'  => 'datetime',
  ];

  protected $fillable = [
    'name',
    'description',
  ];
}
