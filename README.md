# AI Provider for Universal OpenAI API - Connect Any OpenAI-Compatible API to WordPress

**Contributors:** [rtCamp](https://profiles.wordpress.org/rtcamp/), [milindmore22](https://profiles.wordpress.org/milindmore22), [vishal4669](https://profiles.wordpress.org/vishal4669/), [aviralmittal89](https://profiles.wordpress.org/aviralmittal89/)

**Tags:** WordPress, AI, OpenAI, LLM, Text Generation, Image Generation, Self-hosted, Local AI, Ollama, Groq

This plugin is licensed under the GPL v2 or later.

## Overview

AI Provider for Universal OpenAI API registers an `openai_compatible` provider with the WordPress AI Client so that **any OpenAI-compatible API** can power AI features across WordPress — text generation, chat, and image generation included.

## Description

**AI Provider for Universal OpenAI API** bridges the WordPress AI Client with any OpenAI-compatible REST API, allowing you to:

* **Connect to any OpenAI-compatible endpoint** — official OpenAI, self-hosted, or third-party
* **Generate text** using any LLM accessible through the configured endpoint
* **Generate images** using models that support image generation
* **Configure default models** for text and image generation from WordPress admin settings
* **Discover available models** automatically from your configured endpoint
* **Switch endpoints and models** without changing any code — just update your admin settings

This makes it simple to integrate any OpenAI-compatible API into your WordPress site while keeping full control over which service and models you use.

## Why AI Provider for Universal OpenAI API?

Many teams need the flexibility to swap AI providers without being locked into a single service — for cost, privacy, compliance, or feature reasons. This plugin handles that by:

- **Provider-agnostic:** Point at OpenAI, Ollama, LM Studio, Groq, Mistral AI, or any compatible service
- **Local inference support:** Works with self-hosted servers; WordPress HTTP restrictions for local URLs are resolved automatically
- **Standardized interface:** All models exposed through the WordPress AI Client's consistent API
- **Flexible configuration:** Switch endpoints and models from admin settings — no code changes required
- **Automatic model discovery:** Dropdowns are populated by querying the `/models` endpoint of your configured API

### Key Benefits

- **Provider freedom:** Change AI providers by updating a single URL in settings
- **No lock-in:** Use the official OpenAI API today, switch to a local Ollama instance tomorrow
- **Cost control:** Route requests to cheaper or self-hosted models as needed
- **Vision support:** Use multimodal models for image + text prompts
- **Image generation:** Full support for text-to-image models
- **Simple setup:** Enter an endpoint URL and API key to start generating

### API Integration

The plugin communicates using the standard OpenAI REST API format:

| Endpoint | Purpose |
|---|---|
| `GET /v1/models` | Populate the model dropdowns in settings |
| `POST /v1/chat/completions` | Text generation requests |
| `POST /v1/images/generations` | Image generation requests |

### Supported endpoints (examples)

| Service | Example base URL |
|---|---|
| OpenAI | `https://api.openai.com/v1` |
| Ollama | `http://localhost:11434/v1` |
| LM Studio | `http://localhost:1234/v1` |
| LocalAI | `http://localhost:8080/v1` |
| Mistral AI | `https://api.mistral.ai/v1` |
| Together AI | `https://api.together.xyz/v1` |
| Groq | `https://api.groq.com/openai/v1` |
| Fireworks AI | `https://api.fireworks.ai/inference/v1` |
| OpenRouter | `https://openrouter.ai/api/v1` |

## Requirements

* WordPress 7.0 or higher
* PHP 7.4 or higher
* [WordPress AI Client plugin](https://wordpress.org/plugins/ai/) (`ai`)

## Installation

1. Ensure the WordPress AI plugin is installed and activated.
2. Upload the `ai-provider-for-universal-openai-api` directory to `/wp-content/plugins/`, or install via the WordPress Plugins screen.
3. Activate the plugin through the Plugins screen.
4. Go to **Settings → Connectors** and enter your API key for the **AI Provider for Universal OpenAI API** provider.
5. Go to **Settings → AI Provider for Universal OpenAI API** to configure your endpoint URL and select default models.

## Configuration

### Settings Page

Navigate to **Settings → AI Provider for Universal OpenAI API**:

* **API Endpoint URL:** The base URL of your OpenAI-compatible service (defaults to `https://api.openai.com/v1`). Supports presets or custom URLs.
* **Default Text Model:** Select the default model used for text generation. Populated dynamically from your endpoint.
* **Default Image Model:** Select the default model used for image generation. Populated dynamically from your endpoint.

### API Key

Configure your API key in **Settings → Connectors** under the **AI Provider for Universal OpenAI API** provider. For local endpoints that do not require authentication, this field can be left blank.

### Local Endpoints

If you run a local AI server (Ollama, LM Studio, LocalAI) at `localhost`, `127.0.0.1`, or `::1`, the plugin automatically configures WordPress's HTTP client to permit requests to those addresses without disabling safe URL checks globally.

## Usage with WordPress AI Client

Once configured, the provider is available across WordPress through the AI Client:

### Text Generation

```php
use WordPress\AiClient\AiClient;

$response = AiClient::prompt( 'Tell me a joke about WordPress.' )
    ->usingProvider( 'openai_compatible' )
    ->generateText();

echo $response;
```

### Image Generation

```php
use WordPress\AiClient\AiClient;

$response = AiClient::prompt( 'A minimalist logo for a tech blog' )
    ->usingProvider( 'openai_compatible' )
    ->generateImage();

$image_url = $response->getUrl();
```

### Multimodal (Vision) Input

Vision-capable models can accept image inputs alongside text. The plugin detects image parts in the prompt and switches to the multimodal input format automatically:

```php
use WordPress\AI_Client\Prompt_Builder;

$result = Prompt_Builder::create()
    ->using_provider( 'openai_compatible' )
    ->set_model( 'gpt-4o' ) // Vision-capable model at your endpoint
    ->add_text_message( 'Describe what you see in this image.' )
    ->add_image_from_url( 'https://example.com/photo.jpg' )
    ->generate_text();
```

#### WordPress Ability

You can use AI Provider for Universal OpenAI API's vision capabilities in any WordPress AI Client feature that supports image input, such as:

- Alt text generation
- Image captioning / analysis

### Environment Overrides

For advanced deployments, override defaults using environment variables:

| Variable | Description |
|---|---|
| `OPENAI_COMPATIBLE_BASE_URL` | Override the API endpoint URL |
| `OPENAI_COMPATIBLE_API_KEY` | Override the API key |

Environment variables take priority over database-stored settings.

## Developer Filters

### `ai_provider_for_universal_openai_api_models_url`

Filter the resolved URL used to fetch available models:

```php
add_filter( 'ai_provider_for_universal_openai_api_models_url', function ( string $url, string $endpoint ): string {
    return $url;
}, 10, 2 );
```

### `ai_provider_for_universal_openai_api_url`

Filter the endpoint URL for any request path:

```php
add_filter( 'ai_provider_for_universal_openai_api_url', function ( string $url, string $path, string $base_url ): string {
    return $url;
}, 10, 3 );
```

### `openai_compatible_text_generation_params`

Called before sending a text-generation request. Receives and must return the full parameters array.

```php
add_filter( 'openai_compatible_text_generation_params', function ( array $params ): array {
    $params['temperature'] = 0.2;
    return $params;
} );
```

### `openai_compatible_image_generation_params`

Called before sending an image-generation request. Receives and must return the full parameters array.

```php
add_filter( 'openai_compatible_image_generation_params', function ( array $params ): array {
    $params['size'] = '1024x1024';
    return $params;
} );
```

### Non-OpenAI backend compatibility

When the configured endpoint URL does **not** contain `api.openai.com`, the plugin automatically strips parameters that strict OpenAI-compatible backends often reject: `response_format`, `n`, `quality`, `style`, and `reasoning_effort`. You can re-add any of these via the filters above if your backend supports them.

## Development & Contributing

AI Provider for Universal OpenAI API is actively developed and maintained by [rtCamp](https://rtcamp.com/).

- **Repository:** [https://github.com/rtcamp/ai-provider-for-universal-openai-api](https://github.com/rtcamp/ai-provider-for-universal-openai-api)

We welcome contributions! Please open an issue or pull request on GitHub.

### PHP

```bash
composer run-script lint
composer run-script format
composer run-script phpstan
```

### JavaScript

```bash
npm run lint:js
npm run lint:js:fix
```

### Combined Lint

```bash
npm run lint
```

### Build Release Zip

```bash
npm run plugin-zip
```

This creates `ai-provider-for-universal-openai-api.zip` in the plugin root, excluding all development-only files.

## Frequently Asked Questions

### What is an OpenAI-compatible API?

Any REST API that implements the OpenAI API format — specifically `/v1/chat/completions`, `/v1/images/generations`, and `/v1/models` — is considered OpenAI-compatible. This includes the official OpenAI API as well as many self-hosted and third-party services.

### Do I need an API key?

It depends on the service. The official OpenAI API requires an API key. Self-hosted services like Ollama or LM Studio do not require authentication by default. If authentication is needed, enter your API key in **Settings → Connectors**.

### Does this plugin require the WordPress AI Client plugin?

Yes. The **WordPress AI Client** plugin (`ai`) must be installed and activated. This plugin registers the `openai_compatible` provider within that framework.

### Can I use local or self-hosted models?

Yes. The plugin automatically lifts WordPress's default restriction on HTTP requests to private IPs for `localhost`, `127.0.0.1`, and `::1` — but only when the request URL matches your configured endpoint. This means local AI servers work out of the box without affecting other WordPress HTTP calls.

### Can I use vision / image-input models?

Yes. When a model supports vision capabilities, the plugin automatically uses the multimodal input format when image parts are included in a prompt. No additional configuration is needed.

### Can this plugin generate images?

Yes. Any endpoint that implements `/v1/images/generations` is supported. Configure the **Default Image Model** in settings to a model that supports image generation (e.g. `dall-e-3` for OpenAI).

### Can I change the API endpoint?

Yes. Enter the full base URL in **Settings → AI Provider for Universal OpenAI API → API Endpoint URL**, or set the `OPENAI_COMPATIBLE_BASE_URL` environment variable. The environment variable takes priority.

### Why does text generation sometimes time out?

Large models can take significant time to respond, especially on self-hosted hardware. The plugin extends the HTTP timeout to 300 seconds for image generation requests to accommodate slow backends. For text generation, the standard WordPress timeout applies; if you consistently time out, consider running a faster or smaller model.

### Is multisite supported?

The plugin can be network-activated on multisite. Each site's settings are managed independently via their own **Settings → AI Provider for Universal OpenAI API** page.

## Troubleshooting

### No models appearing in the settings dropdowns

- Confirm the API endpoint is reachable and the URL in settings is correct.
- Check that the endpoint implements `/v1/models` and returns a valid response.
- Verify the **WordPress AI Client** plugin is active.
- If authentication is required, make sure the API key is entered in **Settings → Connectors**.

### Text or image generation failing

- Check that the selected model is available at the configured endpoint.
- Review the PHP error log for detailed error messages.
- For self-hosted backends, ensure the server is running and the model has finished loading.

### Settings not saving

- Check file permissions and database write access.
- Look for conflicting plugins that may be overriding the settings page.

### Common Issues

- **"AI provider not found" error in settings:** The WordPress AI Client plugin may not be fully initialised — deactivate and reactivate both plugins, then reload the settings page.
- **Connection refused errors:** Confirm the API server is running and the endpoint URL (including path) is correct.
- **Authentication errors:** Make sure your API key is entered correctly in **Settings → Connectors**.
- **Parameters rejected by backend:** Non-OpenAI backends may reject certain parameters. The plugin strips the most common incompatible ones automatically; use the `openai_compatible_text_generation_params` or `openai_compatible_image_generation_params` filters to fine-tune the request.

## Support & Community

- **Issues & Bug Reports:** [GitHub Issues](https://github.com/rtcamp/ai-provider-for-universal-openai-api/issues)
- **Source Code:** [GitHub Repository](https://github.com/rtcamp/ai-provider-for-universal-openai-api)

## License

This project is licensed under the GPL v2 or later — see the [LICENSE](LICENSE) file for details.

---

**Made with ❤️ by [rtCamp](https://rtcamp.com/)**
