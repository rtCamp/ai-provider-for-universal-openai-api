<?php
/**
 * Settings for OpenAI-compatible provider.
 *
 * @since 1.0.0
 * @package rtCamp\AIProviderUniversalOpenAI
 */

declare( strict_types=1 );

namespace rtCamp\AIProviderUniversalOpenAI\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;

/**
 * Class OpenAiCompatibleSettings.
 */
class OpenAiCompatibleSettings {

	private const OPTION_GROUP        = 'ai-provider-for-universal-openai-api-settings';
	private const OPTION_NAME         = 'ai_provider_for_universal_openai_api_settings';
	private const PAGE_SLUG           = 'ai-provider-for-universal-openai-api';
	private const SECTION_ID          = 'ai_provider_for_universal_openai_api_main';
	private const AJAX_ACTION_MODELS  = 'ai_provider_for_universal_openai_api_models';
	private const NONCE_ACTION        = 'ai_provider_for_universal_openai_api_nonce';
	private const KEY_ENDPOINT_URL    = 'endpoint_url';
	private const KEY_TEXT_MODEL      = 'text_model';
	private const KEY_IMAGE_MODEL     = 'image_model';
	private const DEFAULT_ENDPOINT    = 'https://api.openai.com/v1';
	private const DEFAULT_TEXT_MODEL  = '';
	private const DEFAULT_IMAGE_MODEL = '';
	private const MODELS_CACHE_TTL    = HOUR_IN_SECONDS;

	/**
	 * Initializes settings hooks.
	 *
	 * @since 1.0.0
	 */
	public function init(): void {
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_menu', [ $this, 'register_settings_screen' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_settings_script' ] );
		add_action( 'wp_ajax_' . self::AJAX_ACTION_MODELS, [ $this, 'ajax_list_models' ] );
	}

	/**
	 * Registers settings and fields.
	 *
	 * @since 1.0.0
	 */
	public function register_settings(): void {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			[
				'type'              => 'array',
				'default'           => self::get_default_settings(),
				'sanitize_callback' => [ $this, 'sanitize_settings' ],
			]
		);

		add_settings_section(
			self::SECTION_ID,
			'',
			'__return_empty_string',
			self::PAGE_SLUG
		);

		add_settings_field(
			self::OPTION_NAME . '_endpoint_url',
			__( 'API Endpoint URL', 'ai-provider-for-universal-openai-api' ),
			[ $this, 'render_endpoint_field' ],
			self::PAGE_SLUG,
			self::SECTION_ID,
			[ 'label_for' => self::OPTION_NAME . '-endpoint-url-search' ]
		);

		add_settings_field(
			self::OPTION_NAME . '_text_model',
			__( 'Default Text Model', 'ai-provider-for-universal-openai-api' ),
			[ $this, 'render_text_model_field' ],
			self::PAGE_SLUG,
			self::SECTION_ID,
			[ 'label_for' => self::OPTION_NAME . '-text-model' ]
		);

		add_settings_field(
			self::OPTION_NAME . '_image_model',
			__( 'Default Image Model', 'ai-provider-for-universal-openai-api' ),
			[ $this, 'render_image_model_field' ],
			self::PAGE_SLUG,
			self::SECTION_ID,
			[ 'label_for' => self::OPTION_NAME . '-image-model' ]
		);
	}

	/**
	 * Registers the settings page.
	 *
	 * @since 1.0.0
	 */
	public function register_settings_screen(): void {
		add_options_page(
			__( 'AI Provider for Universal OpenAI API Settings', 'ai-provider-for-universal-openai-api' ),
			__( 'AI Provider for Universal OpenAI API Settings', 'ai-provider-for-universal-openai-api' ),
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render_screen' ]
		);
	}

	/**
	 * Sanitizes a URL with support for localhost and non-standard ports.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url The URL to sanitize.
	 * @return string The sanitized URL.
	 */
	private static function sanitize_endpoint_url( string $url ): string {
		$url = trim( $url );

		if ( '' === $url ) {
			return '';
		}

		// For localhost and custom ports, use a more permissive approach.
		// First check if it's a localhost URL.
		if ( strpos( $url, 'localhost' ) !== false || strpos( $url, '127.0.0.1' ) !== false ) {
			// Basic validation for localhost URLs.
			if ( ! preg_match( '/^https?:\/\/.+/', $url ) ) {
				return '';
			}
			// Return the URL as-is (already trimmed).
			return $url;
		}

		// For non-localhost URLs, use WordPress's stricter validation.
		$escaped = esc_url_raw( $url );
		if ( '' === $escaped ) {
			// If esc_url_raw rejects it but it looks like a valid URL, try to sanitize it differently.
			// This handles edge cases where esc_url_raw might be too strict.
			if ( preg_match( '/^https?:\/\/.+/', $url ) ) {
				return $url;
			}
			return '';
		}

		return $escaped;
	}

	/**
	 * Sanitizes settings.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Raw value.
	 * @return array<string, string>
	 */
	public function sanitize_settings( $value ): array {
		if ( ! is_array( $value ) ) {
			return self::get_default_settings();
		}

		$endpoint_url = isset( $value[ self::KEY_ENDPOINT_URL ] )
			? self::sanitize_endpoint_url( (string) $value[ self::KEY_ENDPOINT_URL ] )
			: self::DEFAULT_ENDPOINT;
		if ( '' === $endpoint_url ) {
			$endpoint_url = self::DEFAULT_ENDPOINT;
		}
		$endpoint_url = rtrim( $endpoint_url, '/' );

		$text_model  = isset( $value[ self::KEY_TEXT_MODEL ] )
			? sanitize_text_field( trim( (string) $value[ self::KEY_TEXT_MODEL ] ) )
			: self::DEFAULT_TEXT_MODEL;
		$image_model = isset( $value[ self::KEY_IMAGE_MODEL ] )
			? sanitize_text_field( trim( (string) $value[ self::KEY_IMAGE_MODEL ] ) )
			: self::DEFAULT_IMAGE_MODEL;

		return [
			self::KEY_ENDPOINT_URL => $endpoint_url,
			self::KEY_TEXT_MODEL   => $text_model,
			self::KEY_IMAGE_MODEL  => $image_model,
		];
	}

	/**
	 * Renders the settings screen.
	 *
	 * @since 1.0.0
	 */
	public function render_screen(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap ai-provider-for-universal-openai-api-settings-wrap universal-openai-connector-settings-wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p><?php esc_html_e( 'Configure an OpenAI-compatible endpoint and choose default models used for text and image generation.', 'ai-provider-for-universal-openai-api' ); ?></p>

			<div class="ai-provider-for-universal-openai-api-info-banner universal-openai-connector-info-banner">
				<span class="ai-provider-for-universal-openai-api-banner-icon universal-openai-connector-banner-icon" aria-hidden="true">
					<img src="<?php echo esc_url( AI_PROVIDER_FOR_UNIVERSAL_OPENAI_API_PLUGIN_URL . 'assets/images/info.svg' ); ?>" width="18" height="18" alt="" />
				</span>
				<p>
					<?php
					printf(
						/* translators: 1: opening anchor tag, 2: closing anchor tag */
						esc_html__( 'Set your API key under %1$sSettings > Connectors%2$s for the AI Provider for Universal OpenAI API provider.', 'ai-provider-for-universal-openai-api' ),
						'<a href="' . esc_url( admin_url( 'options-connectors.php' ) ) . '">',
						'</a>'
					);
					?>
				</p>
			</div>

			<form action="options.php" method="post" class="ai-provider-for-universal-openai-api-card universal-openai-connector-card">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Returns a list of well-known OpenAI-compatible provider endpoints.
	 *
	 * @since 1.0.1
	 *
	 * @return array<string, string> Label => URL pairs.
	 */
	private static function get_preset_endpoints(): array {
		$raw = apply_filters(
			'ai_provider_for_universal_openai_api_endpoints',
			apply_filters(
				'universal_openai_connector_endpoints',
				[
					'OpenAI'       => 'https://api.openai.com/v1',
					'Mistral AI'   => 'https://api.mistral.ai/v1',
					'Together AI'  => 'https://api.together.xyz/v1',
					'Groq'         => 'https://api.groq.com/openai/v1',
					'Fireworks AI' => 'https://api.fireworks.ai/inference/v1',
					'Xiaomi AI'    => 'https://api.ai.xiaomi.com/v1',
					'NVIDIA NIM'   => 'https://integrate.api.nvidia.com/v1',
					'OpenRouter'   => 'https://openrouter.ai/api/v1',
					'Google'       => 'https://generativelanguage.googleapis.com/v1beta/openai',
					'Anthropic'    => 'https://api.anthropic.com/v1',
					'DeepSeek'     => 'https://api.deepseek.com/v1',
					'Perplexity'   => 'https://api.perplexity.ai',
				]
			)
		);

		// Guard against a filter returning a non-array.
		if ( ! is_array( $raw ) ) {
			return [];
		}

		// Keep only entries where both the label (key) and URL (value) are non-empty strings.
		$normalized = [];
		foreach ( $raw as $label => $url ) {
			if ( ! is_string( $label ) || '' === trim( $label ) ) {
				continue;
			}
			if ( ! is_string( $url ) || '' === trim( $url ) ) {
				continue;
			}
			$normalized[ $label ] = $url;
		}

		return $normalized;
	}

	/**
	 * Renders endpoint URL field.
	 *
	 * @since 1.0.0
	 */
	public function render_endpoint_field(): void {
		$settings = self::get_settings();
		$value    = (string) $settings[ self::KEY_ENDPOINT_URL ];
		$id       = self::OPTION_NAME . '-endpoint-url';
		$name     = self::OPTION_NAME . '[' . self::KEY_ENDPOINT_URL . ']';
		$presets  = self::get_preset_endpoints();

		$presets_data = [];
		foreach ( $presets as $label => $url ) {
			$presets_data[] = [
				'label' => $label,
				'url'   => $url,
			];
		}
		?>
		<div
			id="openai-compatible-endpoint-combobox"
			data-presets="<?php echo esc_attr( (string) wp_json_encode( $presets_data ) ); ?>"
		>
			<div class="openai-compatible-endpoint-search-container">
				<span class="openai-compatible-endpoint-search-icon" aria-hidden="true">
					<img src="<?php echo esc_url( AI_PROVIDER_FOR_UNIVERSAL_OPENAI_API_PLUGIN_URL . 'assets/images/search.svg' ); ?>" width="14" height="14" alt="" />
				</span>
				<input
					type="text"
					role="combobox"
					id="<?php echo esc_attr( $id ); ?>-search"
					name="<?php echo esc_attr( $name ); ?>"
					autocomplete="off"
					spellcheck="false"
					aria-expanded="false"
					aria-haspopup="listbox"
					aria-autocomplete="list"
					aria-controls="<?php echo esc_attr( $id ); ?>-listbox"
					value="<?php echo esc_attr( $value ); ?>"
					placeholder="<?php esc_attr_e( 'Search or enter custom URL', 'ai-provider-for-universal-openai-api' ); ?>"
					class="openai-compatible-endpoint-search-input"
				/>
				<button
					type="button"
					id="openai-compatible-endpoint-toggle"
					aria-label="<?php esc_attr_e( 'Toggle provider list', 'ai-provider-for-universal-openai-api' ); ?>"
					aria-expanded="false"
					aria-controls="<?php echo esc_attr( $id ); ?>-listbox"
				>
					<img src="<?php echo esc_url( AI_PROVIDER_FOR_UNIVERSAL_OPENAI_API_PLUGIN_URL . 'assets/images/chevron-down.svg' ); ?>" width="14" height="14" alt="" aria-hidden="true" />
				</button>
			</div>
			<ul
				role="listbox"
				id="<?php echo esc_attr( $id ); ?>-listbox"
				aria-label="<?php esc_attr_e( 'Provider suggestions', 'ai-provider-for-universal-openai-api' ); ?>"
				class="openai-compatible-endpoint-presets-list"
			></ul>
		</div>
		<input
			type="hidden"
			id="<?php echo esc_attr( $id ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
		/>
		<p class="description openai-compatible-endpoint-description">
			<?php esc_html_e( 'Search a preset provider or type a custom base URL for your OpenAI-compatible API.', 'ai-provider-for-universal-openai-api' ); ?>
		</p>
		<?php
	}

	/**
	 * Renders text model dropdown.
	 *
	 * @since 1.0.0
	 */
	public function render_text_model_field(): void {
		$settings = self::get_settings();
		$value    = (string) $settings[ self::KEY_TEXT_MODEL ];
		$id       = self::OPTION_NAME . '-text-model';
		$name     = self::OPTION_NAME . '[' . self::KEY_TEXT_MODEL . ']';
		?>
		<div class="openai-compatible-model-select-wrapper">
			<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" class="regular-text">
				<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $value ); ?></option>
			</select>
			<span id="openai-compatible-text-model-status" class="openai-compatible-model-status"></span>
		</div>
		<p class="description">
			<?php esc_html_e( 'Optional override. Leave as "Use AI Client default" to let WordPress AI Client choose.', 'ai-provider-for-universal-openai-api' ); ?>
		</p>
		<?php
	}

	/**
	 * Renders image model dropdown.
	 *
	 * @since 1.0.0
	 */
	public function render_image_model_field(): void {
		$settings = self::get_settings();
		$value    = (string) $settings[ self::KEY_IMAGE_MODEL ];
		$id       = self::OPTION_NAME . '-image-model';
		$name     = self::OPTION_NAME . '[' . self::KEY_IMAGE_MODEL . ']';
		?>
		<div class="openai-compatible-model-select-wrapper">
			<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" class="regular-text">
				<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $value ); ?></option>
			</select>
			<span id="openai-compatible-image-model-status" class="openai-compatible-model-status"></span>
		</div>
		<p class="description">
			<?php esc_html_e( 'Optional override. Leave as "Use AI Client default" to let WordPress AI Client choose.', 'ai-provider-for-universal-openai-api' ); ?>
		</p>
		<?php
	}

	/**
	 * Enqueues settings page JavaScript.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook_suffix Current admin hook suffix.
	 */
	public function enqueue_settings_script( string $hook_suffix ): void {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		$plugin_dir = AI_PROVIDER_FOR_UNIVERSAL_OPENAI_API_PLUGIN_DIR;
		$asset_file = $plugin_dir . 'build/admin/settings.asset.php';
		$asset      = file_exists( $asset_file ) ? require $asset_file : []; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable -- Asset file path is built from a known constant.

		$dependencies = isset( $asset['dependencies'] ) ? $asset['dependencies'] : [];
		$version      = isset( $asset['version'] ) ? $asset['version'] : AI_PROVIDER_FOR_UNIVERSAL_OPENAI_API_VERSION;

		wp_enqueue_script(
			'ai-provider-for-universal-openai-api-settings',
			plugins_url( 'build/admin/settings.js', AI_PROVIDER_FOR_UNIVERSAL_OPENAI_API_PLUGIN_FILE ),
			$dependencies,
			$version,
			true
		);

		wp_enqueue_style(
			'ai-provider-for-universal-openai-api-settings',
			plugins_url( 'build/admin/style-settings.css', AI_PROVIDER_FOR_UNIVERSAL_OPENAI_API_PLUGIN_FILE ),
			[],
			$version
		);
		wp_style_add_data( 'ai-provider-for-universal-openai-api-settings', 'rtl', 'replace' );

		$settings_data = [
			'ajaxUrl'            => esc_url_raw(
				add_query_arg(
					[
						'action'   => self::AJAX_ACTION_MODELS,
						'_wpnonce' => wp_create_nonce( self::NONCE_ACTION ),
					],
					admin_url( 'admin-ajax.php' )
				)
			),
			'selectedTextModel'  => self::get_selected_text_model(),
			'selectedImageModel' => self::get_selected_image_model(),
			'i18n'               => [
				'loading'         => __( 'Loading models…', 'ai-provider-for-universal-openai-api' ),
				'loaded'          => __( 'models loaded.', 'ai-provider-for-universal-openai-api' ),
				'errorLoad'       => __( 'Could not load models from endpoint.', 'ai-provider-for-universal-openai-api' ),
				'aiClientDefault' => __( 'Use AI Client default', 'ai-provider-for-universal-openai-api' ),
			],
		];

		wp_localize_script(
			'ai-provider-for-universal-openai-api-settings',
			'aiProviderForUniversalOpenAiApiSettings',
			$settings_data
		);
		wp_localize_script(
			'ai-provider-for-universal-openai-api-settings',
			'universalOpenAiConnectorSettings',
			$settings_data
		);
	}

	/**
	 * Handles AJAX model list for dropdowns.
	 *
	 * @since 1.0.0
	 */
	public function ajax_list_models(): void {
		check_ajax_referer( self::NONCE_ACTION );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'ai-provider-for-universal-openai-api' ), 403 );
		}

		// Accept an optional endpoint URL POSTed by the settings page so models can be
		// previewed for an unsaved endpoint before the form is submitted.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already verified above via check_ajax_referer.
		if ( isset( $_POST['endpoint_url'] ) ) {
			// Key present: apply the same empty→default substitution that sanitize_settings uses,
			// so a cleared field previews models from DEFAULT_ENDPOINT rather than the saved one.
			$sanitized = self::sanitize_endpoint_url( sanitize_text_field( wp_unslash( (string) $_POST['endpoint_url'] ) ) );
			$endpoint  = rtrim( '' !== $sanitized ? $sanitized : self::DEFAULT_ENDPOINT, '/' );
		} else {
			$settings = self::get_settings();
			$endpoint = rtrim( (string) $settings[ self::KEY_ENDPOINT_URL ], '/' );
		}

		$api_key   = self::get_api_key();
		$cache_key = 'ai_openai_compatible_models_' . md5( $endpoint . '|' . $api_key );

		$cached = get_transient( $cache_key );
		if ( false !== $cached && is_array( $cached ) ) {
			wp_send_json_success( $cached );
		}

		$models_url = self::get_models_url( $endpoint );
		$headers    = [
			'Accept' => 'application/json',
		];
		if ( '' !== $api_key ) {
			$headers['Authorization'] = 'Bearer ' . $api_key;
		}

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_get_wp_remote_get -- External request to configured provider endpoint.
		$response = wp_remote_get(
			$models_url,
			[
				'headers' => $headers,
			]
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error(
				sprintf(
					/* translators: %s: Error message. */
					__( 'Could not fetch models. Error: %s', 'ai-provider-for-universal-openai-api' ),
					$response->get_error_message()
				),
				500
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			wp_send_json_error( __( 'Unexpected /models response format. Expected JSON object.', 'ai-provider-for-universal-openai-api' ), 500 );
		}

		$raw_models = [];
		if ( isset( $data['models'] ) && is_array( $data['models'] ) ) {
			$raw_models = $data['models'];
		} elseif ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
			$raw_models = $data['data'];
		}

		if ( [] === $raw_models ) {
			wp_send_json_error( __( 'Unexpected /models response format. Expected JSON with a models or data array.', 'ai-provider-for-universal-openai-api' ), 500 );
		}

		$models = array_values(
			array_filter(
				array_map(
					static function ( $model ) {
						if ( ! is_array( $model ) ) {
							return null;
						}

						$type = isset( $model['type'] ) ? strtolower( trim( (string) $model['type'] ) ) : '';
						if ( 'embedding' === $type ) {
							return null;
						}

						$id = '';
						if ( isset( $model['id'] ) && '' !== trim( (string) $model['id'] ) ) {
							$id = trim( (string) $model['id'] );
						} elseif ( isset( $model['key'] ) && '' !== trim( (string) $model['key'] ) ) {
							$id = trim( (string) $model['key'] );
						} elseif ( isset( $model['selected_variant'] ) && '' !== trim( (string) $model['selected_variant'] ) ) {
							$id = trim( (string) $model['selected_variant'] );
						}

						if ( '' === $id ) {
							return null;
						}

						$name = $id;
						if ( isset( $model['name'] ) && '' !== trim( (string) $model['name'] ) ) {
							$name = trim( (string) $model['name'] );
						} elseif ( isset( $model['display_name'] ) && '' !== trim( (string) $model['display_name'] ) ) {
							$name = trim( (string) $model['display_name'] );
						}

						return [
							'id'       => $id,
							'name'     => $name,
							'is_image' => self::is_likely_image_model( $id ),
						];
					},
					$raw_models
				)
			)
		);

		set_transient( $cache_key, $models, self::MODELS_CACHE_TTL );
		wp_send_json_success( $models );
	}

	/**
	 * Returns selected text model.
	 *
	 * @since 1.0.0
	 */
	public static function get_selected_text_model(): string {
		$settings = self::get_settings();
		return trim( (string) $settings[ self::KEY_TEXT_MODEL ] );
	}

	/**
	 * Returns the effective text model identifier.
	 *
	 * Returns the user-configured text model when set. Otherwise fetches the
	 * list of available models from the endpoint and returns the first model
	 * that does not resemble an image-generation model.
	 *
	 * @since 1.0.0
	 */
	public static function get_effective_text_model(): string {
		$selected = self::get_selected_text_model();
		if ( '' !== $selected ) {
			return $selected;
		}

		foreach ( self::fetch_all_models() as $model ) {
			if ( ! self::is_likely_image_model( (string) $model['id'] ) ) {
				return (string) $model['id'];
			}
		}

		return '';
	}

	/**
	 * Returns selected image model.
	 *
	 * @since 1.0.0
	 */
	public static function get_selected_image_model(): string {
		$settings = self::get_settings();
		return trim( (string) $settings[ self::KEY_IMAGE_MODEL ] );
	}

	/**
	 * Returns the effective image model identifier.
	 *
	 * Returns the user-configured image model when set. Otherwise fetches the
	 * list of available models from the endpoint and returns the first model
	 * that resembles an image-generation model, or an empty string if none
	 * is found.
	 *
	 * @since 1.0.0
	 */
	public static function get_effective_image_model(): string {
		$selected = self::get_selected_image_model();
		if ( '' !== $selected ) {
			return $selected;
		}

		foreach ( self::fetch_all_models() as $model ) {
			if ( self::is_likely_image_model( (string) $model['id'] ) ) {
				return (string) $model['id'];
			}
		}

		return '';
	}

	/**
	 * Returns endpoint URL.
	 *
	 * @since 1.0.0
	 */
	public static function get_endpoint_url(): string {
		$settings = self::get_settings();
		$url      = (string) $settings[ self::KEY_ENDPOINT_URL ];
		if ( '' === trim( $url ) ) {
			return self::DEFAULT_ENDPOINT;
		}
		return rtrim( $url, '/' );
	}

	/**
	 * Returns the correct endpoint URL for /models request, applying dynamic custom filters.
	 *
	 * @since 1.0.2
	 *
	 * @param string $endpoint The base endpoint URL.
	 * @return string The resolved /models URL.
	 */
	private static function get_models_url( string $endpoint ): string {
		$models_url = $endpoint . '/models';
		$models_url = apply_filters( 'ai_provider_for_universal_openai_api_models_url', $models_url, $endpoint );
		return apply_filters( 'universal_openai_connector_models_url', $models_url, $endpoint );
	}

	/**
	 * Returns current settings merged with defaults.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string>
	 */
	public static function get_settings(): array {
		$settings = get_option( self::OPTION_NAME, null );
		if ( null === $settings ) {
			$settings = get_option( 'ai_provider_for_universal_openai_api', null );
			if ( null === $settings ) {
				$settings = get_option( 'universal_openai_connector_settings', null );
				if ( null === $settings ) {
					$settings = get_option( 'universal_openai_connector', [] );
				}
			}
		}
		return array_merge( self::get_default_settings(), (array) $settings );
	}

	/**
	 * Returns defaults.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string>
	 */
	private static function get_default_settings(): array {
		return [
			self::KEY_ENDPOINT_URL => self::DEFAULT_ENDPOINT,
			self::KEY_TEXT_MODEL   => self::DEFAULT_TEXT_MODEL,
			self::KEY_IMAGE_MODEL  => self::DEFAULT_IMAGE_MODEL,
		];
	}

	/**
	 * Reads configured API key from AI Client registry, falling back to env var.
	 *
	 * @since 1.0.0
	 */
	private static function get_api_key(): string {
		if ( class_exists( AiClient::class ) ) {
			$registry = AiClient::defaultRegistry();
			if ( $registry->hasProvider( 'openai_compatible' ) ) {
				$auth = $registry->getProviderRequestAuthentication( 'openai_compatible' );
				if ( $auth instanceof ApiKeyRequestAuthentication ) {
					return (string) $auth->getApiKey();
				}
			}
		}

		$env_key = getenv( 'OPENAI_COMPATIBLE_API_KEY' );
		if ( false === $env_key ) {
			return '';
		}

		return trim( (string) $env_key );
	}

	/**
	 * Fetches the list of available models from the configured endpoint.
	 *
	 * Results are cached for MODELS_CACHE_TTL seconds, sharing the same
	 * transient key used by the AJAX handler so admin-page visits warm the
	 * cache for runtime auto-selection. Returns an empty array if the
	 * endpoint is unreachable or returns an unexpected response.
	 *
	 * @since 1.0.0
	 *
	 * @return array<mixed, mixed>
	 */
	public static function fetch_all_models(): array {
		$settings  = self::get_settings();
		$endpoint  = rtrim( (string) $settings[ self::KEY_ENDPOINT_URL ], '/' );
		$api_key   = self::get_api_key();
		$cache_key = 'ai_openai_compatible_models_' . md5( $endpoint . '|' . $api_key );

		$cached = get_transient( $cache_key );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$models_url = self::get_models_url( $endpoint );
		$headers    = [
			'Accept' => 'application/json',
		];
		if ( '' !== $api_key ) {
			$headers['Authorization'] = 'Bearer ' . $api_key;
		}

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.wp_remote_get_wp_remote_get -- External request to configured provider endpoint.
		$response = wp_remote_get(
			$models_url,
			[
				'headers' => $headers,
			]
		);

		if ( is_wp_error( $response ) ) {
			return [];
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			return [];
		}

		$raw_models = [];
		if ( isset( $data['models'] ) && is_array( $data['models'] ) ) {
			$raw_models = $data['models'];
		} elseif ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
			$raw_models = $data['data'];
		}

		$models = array_values(
			array_filter(
				array_map(
					static function ( $model ) {
						if ( ! is_array( $model ) ) {
							return null;
						}

						$type = isset( $model['type'] ) ? strtolower( trim( (string) $model['type'] ) ) : '';
						if ( 'embedding' === $type ) {
							return null;
						}

						$id = '';
						if ( isset( $model['id'] ) && '' !== trim( (string) $model['id'] ) ) {
							$id = trim( (string) $model['id'] );
						} elseif ( isset( $model['key'] ) && '' !== trim( (string) $model['key'] ) ) {
							$id = trim( (string) $model['key'] );
						} elseif ( isset( $model['selected_variant'] ) && '' !== trim( (string) $model['selected_variant'] ) ) {
							$id = trim( (string) $model['selected_variant'] );
						}

						if ( '' === $id ) {
							return null;
						}

						$name = $id;
						if ( isset( $model['name'] ) && '' !== trim( (string) $model['name'] ) ) {
							$name = trim( (string) $model['name'] );
						} elseif ( isset( $model['display_name'] ) && '' !== trim( (string) $model['display_name'] ) ) {
							$name = trim( (string) $model['display_name'] );
						}

						return [
							'id'       => $id,
							'name'     => $name,
							'is_image' => self::is_likely_image_model( $id ),
						];
					},
					$raw_models
				)
			)
		);

		if ( ! empty( $models ) ) {
			set_transient( $cache_key, $models, self::MODELS_CACHE_TTL );
		}

		return $models;
	}

	/**
	 * Returns true if the model ID resembles an image-generation model.
	 *
	 * Uses keyword matching on the lowercased model identifier, similar to
	 * how the Ollama provider detects image-generation models.
	 *
	 * @since 1.0.0
	 *
	 * @param string $model_id The model identifier to inspect.
	 */
	private static function is_likely_image_model( string $model_id ): bool {
		$lower    = strtolower( $model_id );
		$keywords = [
			'dall-e',
			'dalle',
			'stable-diffusion',
			'sdxl',
			'flux',
			'imagen',
			'dreamstudio',
			'midjourney',
			'firefly',
			'kandinsky',
			'deepfloyd',
			'wuerstchen',
			'stable-cascade',
			'playground-v',
			'riverflow',
			'recraft',
			'seedream',
			'imagine',
			// Segment-based patterns: hyphens and slashes act as word separators in model IDs.
			'-image',
			'/image',
			'image-',
		];
		foreach ( $keywords as $keyword ) {
			if ( str_contains( $lower, $keyword ) ) {
				return true;
			}
		}
		return false;
	}
}
