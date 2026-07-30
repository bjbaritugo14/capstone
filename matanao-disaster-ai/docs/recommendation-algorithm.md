# Recommendation Algorithm

The recommendation module uses a Decision Tree as the main algorithm and Ollama as an optional local AI assistance layer.

## Inputs

- Barangay
- Disaster type
- Damage severity
- Affected families
- Household members
- Affected structures
- Description

## Outputs

- Food packs
- Medicine kits
- Cash assistance
- Basis or explanation

## Decision Tree Rules

1. If severity is `severe`, the system gives high priority assistance.
   - Food packs = affected families x 2
   - Medicine kits increase based on household members
   - Cash assistance increases based on affected families and affected structures

2. If severity is `moderate`, the system gives medium priority assistance.
   - Food packs = affected families x 1.5
   - Medicine kits are based on household members
   - Cash assistance uses a moderate multiplier

3. If severity is `minor`, the system gives basic assistance.
   - Food packs = at least one per affected family
   - Medicine kits use the lowest multiplier
   - Cash assistance uses the lowest multiplier

4. Family and structure totals are compared with the configured focused and immediate response thresholds.

5. Disaster type and description are processed through predefined contextual branches:
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
