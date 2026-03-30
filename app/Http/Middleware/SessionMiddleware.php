<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SessionMiddleware
{
  public function handle(Request $request, Closure $next)
  {
    Log::info('SessionMiddleware triggered', [
      'path'   => $request->path(),
      'url'   => $request->url(),
      'method' => $request->method(),
      'route'  => optional($request->route())->getActionName(),
    ]);

    $cookieName = env('SSO_COOKIE_NAME', 'sso_token');

        // 1️⃣ Get token sources (priority: query → cookie → session)
        $tokenFromQuery   = $request->query('key');
        $tokenFromCookie  = $request->cookie($cookieName);
        $tokenFromSession = session('emp_data.token');

$token = $tokenFromQuery ?? $tokenFromCookie ?? $tokenFromSession;
    if (!$token) {
      return $this->redirectToLogin($request);
    }

    $existing = session('emp_data');
    if ($existing && $existing['token'] === $token) {
      $request->attributes->set('auth_user', (object) $existing);

       if ($tokenFromQuery) {
                $cookie = cookie($cookieName, $token, 60 * 24 * 7);
                return redirect($request->url())->withCookie($cookie);
            }
      return $next($request);
    }

    try {
      $user = DB::connection('authify')->table('authify_sessions')->where('token', $token)->first();
    } catch (\Throwable $e) {
      Log::error('Authify DB unreachable', ['error' => $e->getMessage()]);
      abort(503, 'Authentication service unavailable.');
    }

    $request->attributes->set('auth_user', $user);

    if (!$user) {
      session()->forget('emp_data');
        $expiredCookie = cookie()->forget($cookieName);
            return $this->redirectToLogin($request)->withCookie($expiredCookie);
    }

    session(['emp_data' => [
      'token'         => $user->token,
      'emp_id'        => $user->emp_id,
      'emp_name'      => $user->emp_name,
      'emp_firstname' => $user->emp_firstname,
      'emp_jobtitle'  => $user->emp_jobtitle,
      'emp_dept'      => $user->emp_dept,
      'emp_prodline'  => $user->emp_prodline,
      'emp_station'   => $user->emp_station,
      'generated_at'  => $user->generated_at,
    ]]);

    session()->save();

      $cookie = cookie($cookieName, $user->token, 60 * 24 * 7);
    $request->setUserResolver(fn() => (object) session('emp_data'));

    if ($tokenFromQuery) {
      $url = $request->url();
      $query = $request->query();
      unset($query['key']);
      if (!empty($query)) {
        $url .= '?' . http_build_query($query);
      }
      return redirect($url)->withCookie($cookie);
    }

    return $next($request)->withCookie($cookie);
  }

  private function redirectToLogin(Request $request)
  {
    $redirectUrl = urlencode($request->fullUrl());
    return redirect("http://192.168.2.221:8200/login?redirect={$redirectUrl}");
  }
}
