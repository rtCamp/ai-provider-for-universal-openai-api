=== AI Provider for Universal OpenAI API ===
Contributors:      rtcamp, milindmore22, vishal4669, aishwarryapande, aviralmittal89
Tags:              ai, openai, llm, text-generation, image-generation
Requires at least: 7.0
Tested up to:      7.0
Stable tag:        1.1.0
Requires PHP:      7.4
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

OpenAI-compatible provider for the WordPress AI Client with a configurable endpoint and default text/image models.

== Description ==

This plugin provides an [OpenAI-compatible](https://platform.openai.com/docs/api-reference) integration for the [WordPress AI Client](https://wordpress.org/plugins/ai/). Point it at the official OpenAI API, a self-hosted Ollama instance, LM Studio, LocalAI, Mistral AI, Together AI, Groq, Fireworks AI, or any other service that speaks the OpenAI REST API format — text generation, chat, and image generation included.

**Features:**

* OpenAI-compatible provider registration for WordPress AI Client.
* Configure the API endpoint URL, default text model, and default image model from a single settings page (*Settings > AI Provider for Universal OpenAI API*).
* API key managed through *Settings > Connectors* — no custom credential UI needed.
* Automatic model discovery via the `/models` endpoint of the configured API (results cached for one hour).
* Localhost and private-network endpoints fully supported for local AI servers.
* Environment-variable overrides: `OPENAI_COMPATIBLE_BASE_URL` and `OPENAI_COMPATIBLE_API_KEY`.
* Automatically strips parameters unsupported by non-OpenAI backends (`response_format`, `n`, `quality`, `style`, `reasoning_effort`).
* Developer filters to customise request parameters for text and image generation.
* Extended timeout (300 s) for image generation to accommodate slow backends.

**Text generation capabilities:**

* Chat history, system instructions, JSON / structured output, function / tool calling.
* Multimodal input (text and images) for vision-capable models.
* Generation options: candidate count, max tokens, temperature, top-p, stop sequences, frequency penalty, presence penalty, custom options.

**Image generation capabilities:**

* Output formats: PNG, JPEG, WebP.
* Aspect ratios: 1:1, 3:2, 2:3, 7:4, 4:7.
* Inline or remote file delivery.
* Custom options passthrough.

== Installation ==

1. Ensure the WordPress AI plugin is installed and activated.
2. Upload the `ai-provider-for-universal-openai-api` folder to `/wp-content/plugins/`, or install it via the WordPress admin.
3. Activate **AI Provider for Universal OpenAI API** through the Plugins menu in WordPress.
4. Go to *Settings > Connectors* and enter your API key for the **AI Provider for Universal OpenAI API** provider.
5. Go to *Settings > AI Provider for Universal OpenAI API* and set your **API Endpoint URL** (defaults to `https://api.openai.com/v1`).
6. Optionally pick a **Default Text Model** and **Default Image Model** from the automatically-populated dropdowns.
7. Save the settings.

== Screenshots ==

1. AI Provider for Universal OpenAI API provider in Connectors page showing connection status and API key field.
2. Settings page showing API endpoint URL, default text model, and default image model dropdowns.
3. Example of generating post excerpt using OpenRouter in the WordPress editor.
4. Example of generating an image using OpenRouter in the WordPress editor.

== Frequently Asked Questions ==

= Which endpoints does this plugin work with? =

Any endpoint that implements the OpenAI REST API: the official OpenAI API, Ollama (`http://localhost:11434/v1`), LM Studio (`http://localhost:1234/v1`), LocalAI, Mistral AI, Together AI, Groq, Fireworks AI, and many others.

= Do I need an API key? =

It depends on the service. The official OpenAI API requires an API key. Self-hosted services such as Ollama or LM Studio do not require authentication by default. If authentication is needed, enter your API key in *Settings > Connectors* for the **AI Provider for Universal OpenAI API** provider. For local servers that do not require a key, leave the field blank.

= What endpoint URL is used by default? =

The default is `https://api.openai.com/v1`. You can change this in *Settings > AI Provider for Universal OpenAI API* or by setting the `OPENAI_COMPATIBLE_BASE_URL` environment variable.

= Can I change the API endpoint? =

Yes. Enter the full base URL in *Settings > AI Provider for Universal OpenAI API > API Endpoint URL*, or set the `OPENAI_COMPATIBLE_BASE_URL` environment variable. The environment variable takes priority over the admin setting.

= Can I use a local AI server running on localhost? =

Yes. The plugin automatically allows HTTP requests to `localhost`, `127.0.0.1`, and `::1` when the configured endpoint matches those hosts, so local AI servers work out of the box without affecting other WordPress HTTP calls.

= Can I use vision / image-input models? =

Yes. When a model supports vision capabilities, the plugin automatically uses the multimodal input format when image parts are included in a prompt. No additional configuration is needed.

= Can this plugin generate images? =

Yes. Any endpoint that implements `/v1/images/generations` is supported. Configure the **Default Image Model** in settings to a model that supports image generation (e.g. `dall-e-3` for OpenAI).

= Why are some OpenAI parameters removed for non-OpenAI endpoints? =

Many strict OpenAI-compatible backends reject parameters they do not recognise (`response_format`, `n`, `quality`, `style`, `reasoning_effort`). The plugin strips these automatically when the endpoint URL does not contain `api.openai.com`. You can re-add them via the developer filters.

= How can I customise the parameters sent to the API? =

Use the `openai_compatible_text_generation_params` filter for text generation or the `openai_compatible_image_generation_params` filter for image generation. Both filters receive and must return the full parameters array.

= Why does image generation sometimes time out? =

Generating images can take significant time on slow or self-hosted backends. The plugin extends the HTTP timeout to 300 seconds for image generation requests to accommodate this.

== Changelog ==

= 1.1.0 =

* Feature: Add filters `ai_provider_for_universal_openai_api_models_url` and `ai_provider_for_universal_openai_api_url` for dynamic endpoint routing.
* Feature: Add is_image flag to models and improve UI filtering.
* Refactor: admin settings to TypeScript and modernize build configuration.
* Refactor: settings UI with improved design and alignment.
* Refactor: update plugin URL constant definition to use plugin_dir_url.
* Fixes : Update project dependencies and development tools.
* Fixes : Dynamically fetch and map models from endpoint for metadata resolution.

= 1.0.1=

* Feature : Add URL preset selector and reorganize settings assets
* Fixes: Term Generation and handles diffrent response format.

= 1.0.0 =

* Initial release as AI Provider for Universal OpenAI API (formerly Universal OpenAI Connector).
* OpenAI-compatible REST API model discovery, text generation, and image generation support.
* Multimodal (vision) input support for vision-capable models.
* Admin settings page for API endpoint URL, default text model, and default image model.
* Automatic localhost allowlisting for local AI servers.
* Extended HTTP timeout (300 s) for image generation.
* Environment-variable overrides for endpoint URL and API key.
* Developer filters for customising text and image generation request parameters.

== Upgrade Notice ==

= 1.1.0 =

Feature: Improved settings UI and added fixes.
