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
		$this->setup_http_request_filters();
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
	 * Allows requests to OpenAI-compatible endpoints, even when they resolve to local or private addresses.
	 *
	 * WordPress by default blocks HTTP requests to local and private IP addresses.
	 * This filter ensures requests destined for the configured or previewed endpoint
	 * are marked as external so WordPress allows them.
	 *
	 * @since 1.0.0
	 * @since 1.2.0 Updated to support custom ports, preview endpoints, and LAN hosts.
	 *
	 * @param bool   $external Whether the request host is considered external.
	 * @param string $host     The host being requested.
	 * @param string $url      The full URL being requested.
	 * @return bool Whether the request should be treated as external.
	 */
	public function allow_localhost_for_openai_compatible( bool $external, string $host, string $url ): bool {
		if ( $external ) {
			return true;
		}

		if ( '' !== $url && $this->is_openai_compatible_url( $url ) ) {
			return true;
		}

		if ( '' !== $host ) {
			foreach ( $this->get_candidate_endpoints() as $endpoint ) {
				$endpoint_host = (string) wp_parse_url( $endpoint, PHP_URL_HOST );
				if ( '' !== $endpoint_host && $this->is_matching_host( $host, $endpoint_host ) ) {
					return true;
				}
			}
		}

		return $external;
	}

	/**
	 * Allows custom ports for requests to OpenAI-compatible endpoints.
	 *
	 * WordPress by default only allows safe ports 80, 443, and 8080.
	 * This filter dynamically whitelists the port of the configured or previewed
	 * endpoint (such as 11434 for Ollama, 1234 for LM Studio, or custom ports).
	 *
	 * @since 1.2.0
	 *
	 * @param array<int> $ports Current list of allowed ports.
	 * @param string     $host  The host being requested.
	 * @param string     $url   The full URL being requested.
	 * @return array<int> Filtered list of allowed ports.
	 */
	public function allow_openai_compatible_ports( array $ports, string $host = '', string $url = '' ): array {
		if ( '' !== $url && $this->is_openai_compatible_url( $url ) ) {
			$port = wp_parse_url( $url, PHP_URL_PORT );
			if ( is_int( $port ) && $port > 0 && ! in_array( $port, $ports, true ) ) {
				$ports[] = $port;
			}
			return $ports;
		}

		foreach ( $this->get_candidate_endpoints() as $endpoint ) {
			$endpoint_port = wp_parse_url( $endpoint, PHP_URL_PORT );
			if ( ! is_int( $endpoint_port ) || $endpoint_port <= 0 || in_array( $endpoint_port, $ports, true ) ) {
				continue;
			}

			$endpoint_host = (string) wp_parse_url( $endpoint, PHP_URL_HOST );
			if ( '' !== $host && ! $this->is_matching_host( $host, $endpoint_host ) ) {
				continue;
			}

			$ports[] = $endpoint_port;
		}

		return $ports;
	}

	/**
	 * Sets up HTTP request filters that allow local and custom port requests.
	 *
	 * Attaches filters to allow local hosts, custom ports, and unsafe URLs for the
	 * configured or previewed OpenAI-compatible endpoint.
	 *
	 * @since 1.0.0
	 */
	public function setup_http_request_filters(): void {
		add_filter(
			'http_request_host_is_external',
			[ $this, 'allow_localhost_for_openai_compatible' ],
			10,
			3
		);

		add_filter(
			'http_allowed_safe_ports',
			[ $this, 'allow_openai_compatible_ports' ],
			10,
			3
		);

		// phpcs:ignore WordPressVIPMinimum.Hooks.RestrictedHooks.http_request_args
		add_filter(
			'http_request_args',
			[ $this, 'modify_http_request_args' ],
			10,
			2
		);
	}

	/**
	 * Modifies HTTP request arguments to allow localhost and private network requests.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $args The HTTP request arguments.
	 * @param string               $url  The URL being requested.
	 * @return array<string, mixed> Modified arguments.
	 */
	public function modify_http_request_args( array $args, string $url ): array {
		if ( ! $this->is_openai_compatible_url( $url ) ) {
			return $args;
		}

		// Disable rejecting unsafe URLs for requests matching the OpenAI-compatible endpoint.
		$args['reject_unsafe_urls'] = false;

		return $args;
	}

	/**
	 * Checks whether a URL points to the configured or previewed OpenAI-compatible endpoint.
	 *
	 * @since 1.2.0
	 *
	 * @param string $url The URL to check.
	 * @return bool True if the URL matches an allowed endpoint.
	 */
	public function is_openai_compatible_url( string $url ): bool {
		if ( '' === trim( $url ) ) {
			return false;
		}

		foreach ( $this->get_candidate_endpoints() as $endpoint ) {
			if ( $this->matches_endpoint( $url, $endpoint ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Retrieves candidate endpoint URLs.
	 *
	 * Returns both the saved endpoint URL and any preview endpoint URL sent during an
	 * admin AJAX model listing request.
	 *
	 * @since 1.2.0
	 *
	 * @return array<string> Unique list of candidate endpoint URLs.
	 */
	private function get_candidate_endpoints(): array {
		$endpoints = [];

		$configured = OpenAiCompatibleSettings::get_endpoint_url();
		if ( '' !== trim( $configured ) ) {
			$endpoints[] = $configured;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Preview URL only read for host/port matching.
		if ( isset( $_POST['endpoint_url'] ) && is_string( $_POST['endpoint_url'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$preview = trim( sanitize_text_field( wp_unslash( $_POST['endpoint_url'] ) ) );
			if ( '' !== $preview ) {
				$endpoints[] = $preview;
			}
		}

		return array_unique( $endpoints );
	}

	/**
	 * Checks whether a request URL matches a candidate endpoint.
	 *
	 * Compares the host, port, and ensures the request path falls under the endpoint path.
	 *
	 * @since 1.2.0
	 *
	 * @param string $url      The request URL to check.
	 * @param string $endpoint The candidate endpoint URL.
	 * @return bool True if the request URL matches the endpoint.
	 */
	private function matches_endpoint( string $url, string $endpoint ): bool {
		if ( '' === trim( $endpoint ) ) {
			return false;
		}

		$request_url  = wp_parse_url( $url );
		$endpoint_url = wp_parse_url( $endpoint );

		if ( ! is_array( $request_url ) || ! is_array( $endpoint_url ) ) {
			return false;
		}

		if ( empty( $request_url['host'] ) || empty( $endpoint_url['host'] ) ) {
			return false;
		}

		// Verify host matches.
		if ( ! $this->is_matching_host( (string) $request_url['host'], (string) $endpoint_url['host'] ) ) {
			return false;
		}

		// Verify port matches.
		$request_port  = $this->get_url_port( $request_url );
		$endpoint_port = $this->get_url_port( $endpoint_url );
		if ( $request_port !== $endpoint_port ) {
			return false;
		}

		// Verify path matches.
		$endpoint_path = isset( $endpoint_url['path'] ) ? rtrim( (string) $endpoint_url['path'], '/' ) : '';
		if ( '' === $endpoint_path ) {
			return true;
		}

		$request_path = isset( $request_url['path'] ) ? (string) $request_url['path'] : '';

		// Reject path traversal attempts with dot segments.
		if ( $this->has_dot_segment( $request_path ) ) {
			return false;
		}

		return $request_path === $endpoint_path || 0 === strpos( $request_path, $endpoint_path . '/' );
	}

	/**
	 * Checks if two hostnames match, treating localhost variants interchangeably.
	 *
	 * @since 1.2.0
	 *
	 * @param string $host_a First hostname.
	 * @param string $host_b Second hostname.
	 * @return bool True if hostnames are considered equivalent.
	 */
	private function is_matching_host( string $host_a, string $host_b ): bool {
		$host_a = strtolower( trim( $host_a ) );
		$host_b = strtolower( trim( $host_b ) );

		if ( $host_a === $host_b ) {
			return true;
		}

		$local_hosts = [ 'localhost', '127.0.0.1', '::1' ];
		return in_array( $host_a, $local_hosts, true ) && in_array( $host_b, $local_hosts, true );
	}

	/**
	 * Gets the port of a parsed URL, falling back to standard scheme defaults.
	 *
	 * @since 1.2.0
	 *
	 * @param array<string, int|string> $parsed_url The parsed URL array from wp_parse_url.
	 * @return int|null Port number or null if undetermined.
	 */
	private function get_url_port( array $parsed_url ): ?int {
		if ( isset( $parsed_url['port'] ) ) {
			return (int) $parsed_url['port'];
		}

		$scheme = isset( $parsed_url['scheme'] ) ? strtolower( (string) $parsed_url['scheme'] ) : 'http';
		if ( 'https' === $scheme ) {
			return 443;
		}
		if ( 'http' === $scheme ) {
			return 80;
		}

		return null;
	}

	/**
	 * Checks whether a URL path contains dot segments (e.g. . or ..).
	 *
	 * @since 1.2.0
	 *
	 * @param string $path Path to check.
	 * @return bool True if dot segment is present.
	 */
	private function has_dot_segment( string $path ): bool {
		do {
			$previous = $path;
			$path     = rawurldecode( $path );
		} while ( $path !== $previous );

		$segments = explode( '/', str_replace( '\\', '/', $path ) );

		foreach ( $segments as $segment ) {
			$segment = explode( ';', $segment )[0];
			if ( '.' === $segment || '..' === $segment ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Backwards-compatibility wrapper to register HTTP request filters.
	 *
	 * @since 1.0.0
	 * @deprecated 1.2.0 Use setup_http_request_filters() instead.
	 */
	public function allow_localhost_requests(): void {
		$this->setup_http_request_filters();
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
