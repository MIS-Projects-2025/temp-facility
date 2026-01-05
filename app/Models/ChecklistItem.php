<?php

namespace App\Models;

use App\Models\Checklist;
use Illuminate\Database\Eloquent\Model;

class ChecklistItem extends Model
{
  protected $table = 'checklist_items';
  public $timestamps = false;

  protected $fillable = [
    'checklist_id',
    'item_id',
    'criteria',
    'schedule_type',
  ];

  public function checklist()
  {
    return $this->belongsTo(Checklist::class, 'checklist_id');
  }
}
