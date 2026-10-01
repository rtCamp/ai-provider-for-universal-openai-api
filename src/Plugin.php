<?php
/**
 * Main plugin class.
 *
 * @since 1.0.0
 * @package rtCamp\AIProviderUniversalOpenAI
 */

declare( strict_types=1 );

namespace rtCamp\AIProviderUniversalOpenAI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication;
use rtCamp\AIProviderUniversalOpenAI\Provider\OpenAiCompatibleProvider;
use rtCamp\AIProviderUniversalOpenAI\Settings\OpenAiCompatibleSettings;

/**
 * Class Plugin.
 */
class Plugin {

	/**
	 * Initializes plugin hooks.
	 *
	 * @since 1.0.0
	 */
	public function init(): void {
		add_action( 'init', [ $this, 'register_provider' ], 5 );
		add_action( 'init', [ $this, 'register_fallback_auth' ], 15 );
		add_action( 'init', [ $this, 'initialize_settings' ] );
		add_action( 'init', [ $this, 'allow_localhost_requests' ], 20 );
		add_action( 'wp_loaded', [ $this, 'setup_http_request_filters' ] );
		add_filter( 'plugin_action_links_' . plugin_basename( AI_PROVIDER_FOR_UNIVERSAL_OPENAI_API_PLUGIN_FILE ), [ $this, 'plugin_action_links' ] );
	}

	/**
	 * Registers the provider with AI Client.
	 *
	 * @since 1.0.0
	 */
	public function register_provider(): void {
		if ( ! class_exists( AiClient::class ) ) {
			return;
		}

		$registry = AiClient::defaultRegistry();

		if ( $registry->hasProvider( OpenAiCompatibleProvider::class ) ) {
			return;
		}

		$registry->registerProvider( OpenAiCompatibleProvider::class );
	}

	/**
	 * Registers fallback API key auth object when credentials are missing.
	 *
	 * @since 1.0.0
	 */
	public function register_fallback_auth(): void {
		if ( ! class_exists( AiClient::class ) ) {
			return;
		}

		$registry = AiClient::defaultRegistry();

		if ( ! $registry->hasProvider( 'openai_compatible' ) ) {
			return;
		}

		$auth = $registry->getProviderRequestAuthentication( 'openai_compatible' );
		if ( null !== $auth ) {
			return;
		}

		$env_key = (string) getenv( 'OPENAI_COMPATIBLE_API_KEY' );
		$registry->setProviderRequestAuthentication(
			'openai_compatible',
			new ApiKeyRequestAuthentication( $env_key )
		);
	}

	/**
	 * Initializes settings.
	 *
	 * @since 1.0.0
	 */
	public function initialize_settings(): void {
		$settings = new OpenAiCompatibleSettings();
		$settings->init();
	}

	/**
	 * Allows localhost HTTP requests for the OpenAI-compatible endpoint.
	 *
	 * WordPress by default blocks HTTP requests to local/private IPs.
	 * This method adds a filter to allow localhost and 127.0.0.1 requests.
	 * when they are for the configured OpenAI-compatible endpoint.
	 *
	 * @since 1.0.0
	 *
	 * @param bool   $external Whether the request is to an external host.
	 * @param string $host     The host being requested.
	 * @param string $url      The full URL being requested.
	 * @return bool
	 */
	public function allow_localhost_for_openai_compatible( bool $external, string $host, string $url ): bool {
		// If already allowed as external, keep it.
		if ( $external ) {
			return true;
		}

		// Check if this is a localhost request.
		if ( 'localhost' !== $host && '127.0.0.1' !== $host && '::1' !== $host ) {
			return $external;
		}

		// Check if the request URL matches the configured endpoint.
		$endpoint = OpenAiCompatibleSettings::get_endpoint_url();
		if ( empty( $endpoint ) ) {
			return $external;
		}

		// Allow if the URL starts with the configured endpoint.
		// This ensures we only allow requests to the specific configured endpoint.
		if ( 0 === strpos( $url, rtrim( $endpoint, '/' ) ) ) {
			return true;
		}

		return $external;
	}

	/**
	 * Sets up HTTP request filters that run on every request.
	 *
	 * This runs at wp_loaded to ensure the filter is active for all subsequent HTTP requests.
	 *
	 * @since 1.0.0
	 */
	public function setup_http_request_filters(): void {
		// Add filter to allow localhost requests (runs during http validation).
		add_filter(
			'http_request_host_is_external',
			[ $this, 'allow_localhost_for_openai_compatible' ],
			10,
			3
		);

		// Add filter to modify the HTTP request arguments before sending.
		// This helps bypass some validation issues.
		// Note: this callback only sets `reject_unsafe_urls` and never modifies the timeout.
		// phpcs:ignore WordPressVIPMinimum.Hooks.RestrictedHooks.http_request_args
		add_filter(
			'http_request_args',
			[ $this, 'modify_http_request_args' ],
			10,
			2
		);
	}

	/**
	 * Modifies HTTP request arguments to allow localhost requests.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $args The HTTP request arguments.
	 * @param string               $url  The URL being requested.
	 * @return array<string, mixed> Modified arguments.
	 */
	public function modify_http_request_args( array $args, string $url ): array {
		$endpoint = OpenAiCompatibleSettings::get_endpoint_url();
		if ( empty( $endpoint ) ) {
			return $args;
		}

		// Check if this is a request to our configured endpoint.
		if ( 0 !== strpos( $url, rtrim( $endpoint, '/' ) ) ) {
			return $args;
		}

		// For requests to our endpoint, allow connections to localhost.
		$args['reject_unsafe_urls'] = false;

		return $args;
	}

	/**
	 * Registers the filter to allow localhost HTTP requests.
	 *
	 * @since 1.0.0
	 */
	public function allow_localhost_requests(): void {
		// Also add the filter during init if needed.
		add_filter(
			'http_request_host_is_external',
			[ $this, 'allow_localhost_for_openai_compatible' ],
			10,
			3
		);
	}

	/**
	 * Adds settings quick link in plugins list.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string> $links Existing links.
	 * @return array<string>
	 */
	public function plugin_action_links( array $links ): array {
		$settings_link = sprintf(
			'<a href="%1$s">%2$s</a>',
			admin_url( 'options-general.php?page=ai-provider-for-universal-openai-api' ),
			esc_html__( 'Settings', 'ai-provider-for-universal-openai-api' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}
}
