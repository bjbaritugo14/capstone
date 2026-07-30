<?php

namespace App\Services;

use App\Models\DamageReport;
use Illuminate\Support\Facades\Http;
use Throwable;

class OllamaRecommendationService
{
    public function __construct(
        protected DecisionTreeRecommendationService $decisionTree,
        protected SystemSettingService $settings,
    ) {}

    public function generate(DamageReport $report): array
    {
        $fallback = $this->decisionTree->generate($report);

        if (! $this->settings->boolean('recommendation_use_ollama_assistance')) {
            return [...$fallback, 'source' => 'decision_tree'];
        }

        try {
            $response = Http::timeout((int) config('services.ollama.timeout', 30))
                ->post(rtrim((string) config('services.ollama.base_url'), '/').'/api/generate', [
                    'model' => config('services.ollama.model', 'llama3.2'),
                    'prompt' => $this->prompt($report),
                    'stream' => false,
                    'format' => 'json',
                ]);

            if (! $response->successful()) {
                return [...$fallback, 'source' => 'decision_tree'];
            }

            $payload = json_decode((string) $response->json('response'), true);

            if (! is_array($payload)) {
                return [...$fallback, 'source' => 'decision_tree'];
            }

            return [
                'food_packs' => max(0, (int) ($payload['food_packs'] ?? $fallback['food_packs'])),
                'medicine_kits' => max(0, (int) ($payload['medicine_kits'] ?? $fallback['medicine_kits'])),
                'cash_assistance' => max(0, (float) ($payload['cash_assistance'] ?? $fallback['cash_assistance'])),
                'basis' => $fallback['basis'].' Ollama refinement: '.(string) ($payload['basis'] ?? 'No additional explanation provided.'),
                'source' => 'ollama',
                'inputs' => $fallback['inputs'],
            ];
        } catch (Throwable) {
            return [...$fallback, 'source' => 'decision_tree'];
        }
    }

    protected function prompt(DamageReport $report): string
    {
        $report->loadMissing(['location.barangay', 'affectedFamilyRecords']);

        $familyCount = $report->affectedFamilyRecords->count() ?: $report->affected_families;
        $householdMembers = $report->affectedFamilyRecords->sum('household_members');
        $base = $this->decisionTree->generate($report);

        return <<<PROMPT
You are an MDRRMO and DSWD relief recommendation assistant for Matanao, Davao del Sur.

Return only valid JSON with these exact keys:
{
  "food_packs": integer,
  "medicine_kits": integer,
  "cash_assistance": number,
  "basis": "short explanation"
}

Use conservative, practical relief values. Base the recommendation on:
- Barangay: {$report->location?->barangay?->barangay_name}
- Disaster type: {$report->disaster_type}
- Severity: {$report->damage_severity}
- Affected families: {$familyCount}
- Household members: {$householdMembers}
- Affected structures: {$report->affected_structures}
- Description: {$report->description}

Base Decision Tree result:
- Food packs: {$base['food_packs']}
- Medicine kits: {$base['medicine_kits']}
- Cash assistance: {$base['cash_assistance']}

Rules:
- Use the Decision Tree result as the baseline.
- You may slightly adjust values only if the description clearly supports it.
- Food packs should generally be at least one per affected family.
- Medicine kits should increase for moderate or severe incidents.
- Cash assistance should increase with severity and affected structures.
- Do not include markdown.
PROMPT;
    }
}
