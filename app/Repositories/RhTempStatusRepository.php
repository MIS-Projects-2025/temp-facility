<?php

namespace App\omega\Repo;

use Illuminate\Http\Request;

class RhTempStatusRepository
{
  const OFFLINE = 'Offline';
  const TIMEOUT = 5;

  protected $request;

  public function __construct(Request $request)
  {
    $this->request = $request;
  }

  public function statusOf($device = null)
  {
    $device = $device ?? $this->request;

    $content = $this->fetch("http://{$device->ip}/postReadHtml?a");

    if ($content === null) {
      return $this->offlineResponse($device);
    }

    return [
      'ip'           => $device->ip,
      'location'     => $device->location,
      'temp'         => $this->extract($content, 'Temperature', 11, 5),
      'rh'           => $this->extract($content, 'Humidity', 8, 5),
      'is_recording' => $this->extract($content, 'Recording', 10, 2) === 'Of' ? 'Off' : 'On',
    ];
  }

  protected function fetch(string $url): ?string
  {
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
      return null;
    }

    $cl = curl_init($url);
    curl_setopt($cl, CURLOPT_CONNECTTIMEOUT, self::TIMEOUT);
    curl_setopt($cl, CURLOPT_TIMEOUT, self::TIMEOUT);
    curl_setopt($cl, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($cl);
    curl_close($cl);

    return $response ?: null;
  }

  protected function extract(string $content, string $keyword, int $offset, int $length): string
  {
    $start = strpos($content, $keyword);

    if ($start === false) {
      return self::OFFLINE;
    }

    return substr($content, $start + $offset, $length);
  }

  protected function offlineResponse($device): array
  {
    return [
      'ip'           => $device->ip,
      'location'     => $device->location,
      'temp'         => self::OFFLINE,
      'rh'           => self::OFFLINE,
      'is_recording' => 'Off',
    ];
  }
}
