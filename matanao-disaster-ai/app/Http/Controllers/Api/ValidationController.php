<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DamageReport;
use App\Models\ReportValidation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ValidationController extends Controller
{
    public function validateReport(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:validated,rejected'],
            'remarks' => ['nullable', 'string'],
        ]);

        $report = DamageReport::findOrFail($id);
        $report->update(['status' => $validated['status']]);

        ReportValidation::updateOrCreate(
            ['report_id' => $report->report_id],
            [
                'validated_by' => $request->user()->user_id,
                'validation_status' => $validated['status'],
                'remarks' => $validated['remarks'] ?? null,
            ],
        );

        return response()->json([
            'message' => 'Report validation saved.',
            'report_id' => $report->report_id,
            'status' => $report->status,
            'remarks' => $validated['remarks'] ?? null,
        ]);
    }
}
