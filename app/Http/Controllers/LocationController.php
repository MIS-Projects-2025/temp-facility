<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Cache;
use App\Models\Location;

class LocationController extends Controller
{
  private const CACHEKEY = 'all_locations';
  private const CACHE_DURATION = 60 * 60;

  public function index(Request $request)
  {
    // return Inertia::render('Location');
  }


  public function getAllLocation(Request $request)
  {

    return Cache::remember(self::CACHEKEY, self::CACHE_DURATION, function () {
      return Location::all();
    });
  }
}
