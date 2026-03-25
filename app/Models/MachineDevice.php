<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class MachineDevice extends Model
{
  protected $connection = 'omega';
  protected $fillable = ['ip', 'location'];
}
