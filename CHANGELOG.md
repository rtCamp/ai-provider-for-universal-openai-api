# Changelog

## 1.2.0

- Added decision-making capability via the `/v1/decisions` endpoint.
- Added `OpenAiCompatibleProvider::decisionModel()` and `OpenAiCompatibleProvider::decisionModelIds()`.
- Added typed decision DTOs: `DecisionQuestion`, `DecisionAnswer`, `DecisionResult`, and `DecisionTypeEnum`.
- Added support for predicate, choice, and score question types.
- Added Default Decision Model setting and admin dropdown with automatic discovery of decision-capable models.
- Added developer filters `ai_provider_for_universal_openai_api_decision_params` and `openai_compatible_decision_params`.

## 1.1.0

- Added filters `ai_provider_for_universal_openai_api_models_url` and `ai_provider_for_universal_openai_api_url` for dynamic endpoint routing.
- Add is_image flag to models and improve UI filtering.
- Refactor admin settings to TypeScript and modernize build configuration.
- Modernize settings UI with improved design and alignment.
- refactor: update plugin URL constant definition to use plugin_dir_url.
- Update project dependencies and development tools
- Fixes : Dynamically fetch and map models from endpoint for metadata resolution.

## 1.0.1

- Added preset URL selector and reorganised settings assets
- Fixed Term Generation and handles diffrent response format

## 1.0.0

- Initial release as AI Provider for Universal OpenAI API (formerly Universal OpenAI Connector).
- OpenAI-compatible REST API model discovery, text generation, and image generation support.
- Multimodal (vision) input support for vision-capable models.
- Admin settings page for API endpoint URL, default text model, and default image model.
- Automatic localhost allowlisting for local AI servers.
- Extended HTTP timeout (300 s) for image generation.
- Environment-variable overrides for endpoint URL and API key.
- Developer filters for customising text and image generation request parameters.
