<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Barangay;
use Illuminate\Http\JsonResponse;

class BarangayController extends Controller
{
    public function index(): JsonResponse
    {
        $barangays = Barangay::query()
            ->active()
            ->orderBy('barangay_name')
            ->get()
            ->map(fn (Barangay $barangay): array => [
                'id' => $barangay->barangay_id,
                'name' => $barangay->barangay_name,
                'municipality' => $barangay->municipality,
                'province' => $barangay->province,
            ])
            ->values();

        return response()->json($barangays);
    }
}
