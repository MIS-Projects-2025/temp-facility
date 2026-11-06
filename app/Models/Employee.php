<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
  protected $connection = 'masterlist';
  protected $table = 'employee_masterlist';
  protected $primaryKey = 'EMPLOYID';
  public $incrementing = false;
  protected $keyType = 'string';
  public $timestamps = false;
}
