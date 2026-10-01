<?php
/**
 * Plugin Name:       AI Provider for Universal OpenAI API
 * Plugin URI:        https://github.com/rtcamp/ai-provider-for-universal-openai-api
 * Description:       OpenAI-compatible provider for the WordPress AI Client with configurable endpoint and default text/image models.
 * Requires at least: 7.0
 * Requires PHP:      7.4
 * Version:           1.1.0
 * Author:            rtCamp
 * Author URI:        https://rtcamp.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ai-provider-for-universal-openai-api
 *
 * @package rtCamp\AIProviderUniversalOpenAI
 */

declare( strict_types=1 );

namespace rtCamp\AIProviderUniversalOpenAI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AI_PROVIDER_FOR_UNIVERSAL_OPENAI_API_VERSION', '1.1.0' );
define( 'AI_PROVIDER_FOR_UNIVERSAL_OPENAI_API_MIN_PHP_VERSION', '7.4' );
define( 'AI_PROVIDER_FOR_UNIVERSAL_OPENAI_API_MIN_WP_VERSION', '7.0' );
define( 'AI_PROVIDER_FOR_UNIVERSAL_OPENAI_API_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AI_PROVIDER_FOR_UNIVERSAL_OPENAI_API_PLUGIN_FILE', __FILE__ );
define( 'AI_PROVIDER_FOR_UNIVERSAL_OPENAI_API_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once AI_PROVIDER_FOR_UNIVERSAL_OPENAI_API_PLUGIN_DIR . 'src/autoload.php';

/**
 * Displays admin notice for requirement failures.
 *
 * @since 1.0.0
 *
 * @param string $message Error message.
 */
function requirement_notice( string $message ): void {
	if ( ! is_admin() ) {
		return;
	}
	?>
	<div class="notice notice-error">
		<p><?php echo wp_kses_post( $message ); ?></p>
	</div>
	<?php
}

/**
 * Checks the PHP requirement.
 *
 * @since 1.0.0
 */
function check_php_version(): bool {
	if ( version_compare( phpversion(), AI_PROVIDER_FOR_UNIVERSAL_OPENAI_API_MIN_PHP_VERSION, '<' ) ) {
		add_action(
			'admin_notices',
			static function () {
				requirement_notice(
					sprintf(
						/* translators: 1: required PHP version, 2: current PHP version */
						__( 'The AI Provider for Universal OpenAI API plugin requires PHP version %1$s or higher. You are running PHP version %2$s.', 'ai-provider-for-universal-openai-api' ),
						AI_PROVIDER_FOR_UNIVERSAL_OPENAI_API_MIN_PHP_VERSION,
						PHP_VERSION
					)
				);
			}
		);

		return false;
	}

	return true;
}

/**
 * Checks the WordPress requirement.
 *
 * @since 1.0.0
 */
function check_wp_version(): bool {
	if ( ! is_wp_version_compatible( AI_PROVIDER_FOR_UNIVERSAL_OPENAI_API_MIN_WP_VERSION ) ) {
		add_action(
			'admin_notices',
			static function () {
				global $wp_version;
				requirement_notice(
					sprintf(
						/* translators: 1: required WordPress version, 2: current WordPress version */
						__( 'The AI Provider for Universal OpenAI API plugin requires WordPress version %1$s or higher. You are running WordPress version %2$s.', 'ai-provider-for-universal-openai-api' ),
						AI_PROVIDER_FOR_UNIVERSAL_OPENAI_API_MIN_WP_VERSION,
						$wp_version
					)
				);
			}
		);

		return false;
	}

	return true;
}

/**
 * Checks if AI Client SDK is available.
 *
 * @since 1.0.0
 */
function check_ai_client(): bool {
	if ( ! class_exists( \WordPress\AiClient\AiClient::class ) ) {
		add_action(
			'admin_notices',
			static function () {
				requirement_notice(
					__( 'The AI Provider for Universal OpenAI API plugin requires the WordPress AI Client (php-ai-client) to be installed.', 'ai-provider-for-universal-openai-api' )
				);
			}
		);

		return false;
	}

	return true;
}

/**
 * Loads the plugin.
 *
 * @since 1.0.0
 */
function load(): void {
	if ( ! check_php_version() || ! check_wp_version() ) {
		return;
	}

	if ( ! check_ai_client() ) {
		return;
	}

	$plugin = new Plugin();
	$plugin->init();
}

load();
