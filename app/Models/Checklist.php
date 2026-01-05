<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Checklist extends Model
{
  protected $table = 'checklists';
  public $timestamps = false;
  // protected $fillable = [
  //   'name',
  //   'description',
  // ];
}
