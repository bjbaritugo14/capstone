<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class EvidenceImage
{
    public static function url(string $path): string
    {
        $prefix = 'supabase://mobile-evidence/';
        if (! str_starts_with($path, $prefix)) {
            return asset('storage/'.$path);
        }

        $base = rtrim((string) config('services.supabase.url'), '/');
        $key = config('services.supabase.anon_key');
        if ($base === '' || ! $key || ! config('services.supabase.media_password')) {
            return '';
        }

        return Cache::remember('mobile-evidence-url:'.hash('sha256', $path), 3000, function () use ($path, $prefix, $base, $key) {
            $token = Cache::remember('mobile-evidence-session', 3000, function () use ($base, $key) {
                return Http::timeout(15)->withOptions(['verify' => config('services.supabase.ca_bundle') ?: true])->withHeaders(['apikey' => $key])
                    ->post($base.'/auth/v1/token?grant_type=password', [
                        'email' => config('services.supabase.media_email'),
                        'password' => config('services.supabase.media_password'),
                    ])->throw()->json('access_token');
            });
            $object = implode('/', array_map('rawurlencode', explode('/', substr($path, strlen($prefix)))));
            $signed = Http::timeout(15)->withOptions(['verify' => config('services.supabase.ca_bundle') ?: true])->withHeaders(['apikey' => $key])->withToken($token)
                ->post($base.'/storage/v1/object/sign/mobile-evidence/'.$object, ['expiresIn' => 3600])
                ->throw()->json('signedURL');

            return $base.'/storage/v1'.$signed;
        });
    }
}
