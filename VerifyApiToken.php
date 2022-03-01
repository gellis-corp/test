<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Token;
use Route;
use DB;
use App;

class VerifyApiToken
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @param string|null $guard
     * @throws \Exception
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {

        $sign = $request->header('Sign', null);
        $key = $request->header('Key', null);

        $data = [
            'url' => $request->fullUrl(),
            'headers' => [
                'Sign' => $sign,
                'Key' => $key
            ],
            'params' => $request->all()
        ];

        $logId = DB::table('api_log')
            ->insert([
                'data' => json_encode($data)
            ]);

        if ($sign && $key) {

            $token = Token::withoutGlobalScope('app-token')
                ->where('key', $key)
                ->first();

            if (!$token)
                return response()->json([
                    'success' => false,
                    'error' => 'Not authorized'
                ], 403);

            if ($token->user->blocked)
                return response()->json([
                    'success' => false,
                    'error' => 'User blocked'
                ], 403);

            $nonce = false;

            if ($request->has('nonce') && $request->input('nonce') > $token->nonce) {
                $nonce = true;
                $token->nonce = $request->input('nonce');

                if ($request->has('token'))
                    $token->token = $request->input('token');

                if ($request->has('lang'))
                    $token->lang = $request->input('lang');

                if ($request->has('os'))
                    $token->os = $request->input('os');

                $token->save();

                if ($token->lang) {
                    App::setLocale($token->lang);
                    $token->user->lang = $token->lang;
                    $token->user->save();
                }

            }

            if (!$nonce)
                return response()->json([
                    'success' => false,
                    'error' => 'Incorrect nonce'
                ], 403);

            $request->merge(['user' => $token->user ]);
            $request->merge(['tokenType' => $token->type ]);

            $request->setUserResolver(function () use ($token) {
                return $token->user;
            });

        }
        else
            return response()->json([
                'success' => false,
                'error' => 'Not authorized'
            ], 403);

        return $next($request);
    }
}
