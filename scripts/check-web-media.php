<?php

require __DIR__.'/../matanao-disaster-ai/vendor/autoload.php';
$app = require __DIR__.'/../matanao-disaster-ai/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$image = App\Models\ReportImage::where('image_path', 'like', 'supabase://%')->first();
if (! $image) {
    throw new RuntimeException('No migrated photo is available to check.');
}
$response = Illuminate\Support\Facades\Http::timeout(15)->withOptions(['verify' => config('services.supabase.ca_bundle') ?: true])->get($image->url);
if ($response->status() !== 200) {
    throw new RuntimeException('Website media download failed.');
}
echo "PASS: Laravel can display private Supabase photos through signed URLs.\n";
