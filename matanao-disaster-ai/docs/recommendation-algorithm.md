# Recommendation Algorithm

The recommendation module uses a Decision Tree as the main algorithm and Ollama as an optional local AI assistance layer.

## Inputs

- Barangay
- Disaster type
- Affected-family severity counts: minor, moderate, and severe
- Effective damage severity, taken from the highest affected-family severity present
- Affected families
- Household members
- Affected structures
- Description

## Outputs

- Food packs
- Medicine kits
- Cash assistance
- Basis or explanation

When the description contains medical-need words, medicine kits are increased while food packs and cash assistance are still calculated normally.

## Decision Tree Rules

1. The system counts affected-family records by severity.
   - Each affected family with `minor`, `moderate`, or `severe` damage is counted in its matching bucket.
   - If a family record has no severity, the system falls back to the report severity for that family.
   - If a report has no family records, all affected families are counted under the report severity.

2. For severe affected families, the system gives high priority assistance for that counted group.
   - Food packs = severe families x 2
   - Medicine kits increase based on severe household members
   - Cash assistance uses the configured severe per-family rate

3. For moderate affected families, the system gives medium priority assistance for that counted group.
   - Food packs = moderate families x 1.5
   - Medicine kits are based on moderate household members
   - Cash assistance uses the configured moderate per-family rate

4. For minor affected families, the system gives basic assistance for that counted group.
   - Food packs = at least one per minor affected family
   - Medicine kits use the lowest divisor
   - Cash assistance uses the configured minor per-family rate

5. The severe, moderate, and minor results are combined into one recommendation. Report-level structure assistance is added once using the highest affected-family severity present.

6. Family and structure totals are compared with the configured focused and immediate response thresholds.

7. If the description contains medical-need terms, the system increases medicine-kit planning.
   - English terms: `injured`, `wound`, `medical`, `medicine`, `hospital`, `sick`
   - Local terms: `nasamdan`, `naangol`, `samad`, `medikal`, `pangmedikal`, `tambal`, `ospital`, `masakiton`, `nagsakit`
   - Food packs are still calculated normally
   - Cash assistance is still calculated normally
   - Medicine kits are still calculated from household members and severity

8. Disaster type and description are processed through predefined contextual branches:
   - Flood and typhoon reports modestly increase food-pack planning.
   - Earthquake, landslide, and fire reports modestly increase medical-kit readiness.
   - Evacuation, medical, and major structural-damage terms apply the matching contextual adjustment.

Barangay does not change assistance quantities by itself. It is included in the recommendation basis and stored input snapshot so users can identify where the decision-support output applies without assigning unequal rates solely by location.

## Ollama Use

Ollama is not trained from scratch in this project. The system sends structured disaster data and the Decision Tree baseline to Ollama through a prompt. Ollama returns JSON with food packs, medicine kits, cash assistance, and explanation.

If Ollama is unavailable or returns invalid JSON, the system still generates a recommendation using the Decision Tree service.

Every generated recommendation stores its basis, source, and input snapshot for recommendation history and DSWD review.

## Code Location

- Decision Tree algorithm: `app/Services/DecisionTreeRecommendationService.php`
- Ollama integration: `app/Services/OllamaRecommendationService.php`
- Recommendation page/controller: `app/Http/Controllers/RecommendationController.php`
