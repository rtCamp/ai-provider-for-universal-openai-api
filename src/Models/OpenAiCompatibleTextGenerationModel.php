<?php
/**
 * Text generation model for OpenAI-compatible endpoints.
 *
 * @since 1.0.0
 * @package rtCamp\AIProviderUniversalOpenAI
 */

declare( strict_types=1 );

namespace rtCamp\AIProviderUniversalOpenAI\Models;

use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleTextGenerationModel;
use rtCamp\AIProviderUniversalOpenAI\Provider\OpenAiCompatibleProvider;
use rtCamp\AIProviderUniversalOpenAI\Settings\OpenAiCompatibleSettings;

/**
 * Class OpenAiCompatibleTextGenerationModel.
 */
class OpenAiCompatibleTextGenerationModel extends AbstractOpenAiCompatibleTextGenerationModel {

	/**
	 * {@inheritDoc}
	 *
	 * @param array<\WordPress\AiClient\Messages\DTO\Message> $prompt The prompt array to prepare parameters for.
	 * @phpstan-param list<\WordPress\AiClient\Messages\DTO\Message> $prompt
	 *
	 * @return array<string, mixed> The prepared parameters for text generation.
	 */
	protected function prepareGenerateTextParams( array $prompt ): array {
		$params = parent::prepareGenerateTextParams( $prompt );

		$selected_model = OpenAiCompatibleSettings::get_selected_text_model();
		if ( '' !== $selected_model ) {
			$params['model'] = $selected_model;
		}

		// The `n` parameter (number of completions) is OpenAI-specific and rejected
		// by many strict OpenAI-compatible backends. Strip it unless targeting the real OpenAI API.
		$endpoint = OpenAiCompatibleSettings::get_endpoint_url();
		if ( strpos( $endpoint, 'api.openai.com' ) === false ) {
			unset( $params['n'] );
		}

		return apply_filters( 'openai_compatible_text_generation_params', $params );
	}

	/**
	 * {@inheritDoc}
	 *
	 * Wraps the output schema in the name/schema/strict envelope required by
	 * OpenAI-compatible structured-output APIs (which OpenRouter mirrors).
	 *
	 * @since 1.0.1
	 *
	 * @param array<string, mixed>|null $output_schema The output schema.
	 * @return array<string, mixed>
	 */
	protected function prepareResponseFormatParam( ?array $output_schema ): array {
		if ( is_array( $output_schema ) ) {
			return [
				'type'        => 'json_schema',
				'json_schema' => [
					'name'   => 'result',
					'schema' => $output_schema,
					'strict' => true,
				],
			];
		}
		return [ 'type' => 'json_object' ];
	}

	/**
	 * {@inheritDoc}
	 *
	 * Flatten text-only typed content arrays to plain strings so that strict
	 * OpenAI-compatible backends that do not accept the typed-content format
	 * (e.g. `[{"type":"text","text":"..."}]`) receive a simple string instead.
	 *
	 * @param array<\WordPress\AiClient\Messages\DTO\Message> $messages The messages array to prepare.
	 * @phpstan-param list<\WordPress\AiClient\Messages\DTO\Message> $messages
	 * @param string|null                                     $system_instruction Optional system instruction.
	 * @return array<string, mixed> The prepared messages array.
	 * @phpstan-return list<array<string, mixed>>
	 */
	protected function prepareMessagesParam( array $messages, ?string $system_instruction = null ): array {
		$messages = parent::prepareMessagesParam( $messages, $system_instruction );

		foreach ( $messages as &$message ) {
			if ( ! isset( $message['content'] ) || ! is_array( $message['content'] ) ) {
				continue;
			}

			$content = $message['content'];

			// Only flatten if all parts are plain text (no images, etc.).
			$all_text = true;
			foreach ( $content as $key => $part ) {
				if ( ! is_int( $key ) || ! is_array( $part ) || ( $part['type'] ?? '' ) !== 'text' ) {
					$all_text = false;
					break;
				}
			}

			if ( ! $all_text || count( $content ) <= 0 ) {
				continue;
			}

			$message['content'] = implode(
				'',
				array_map(
					static function ( $part ) {
						return $part['text'] ?? '';
					},
					$content
				)
			);
		}
		unset( $message );

		return $messages;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param \WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum $method The HTTP method.
	 * @param string                                                  $path The request path.
	 * @param array<string, string|list<string>>                      $headers Optional headers.
	 * @param mixed                                                   $data Optional data.
	 * @return \WordPress\AiClient\Providers\Http\DTO\Request The created request.
	 */
	protected function createRequest( HttpMethodEnum $method, string $path, array $headers = [], $data = null ): Request {
		$existing = $this->getRequestOptions();
		$options  = null !== $existing
			? RequestOptions::fromArray( $existing->toArray() )
			: new RequestOptions();

		// Derive the effective base URL from the same source used to build the request,
		// so that env-var overrides (OPENAI_COMPATIBLE_BASE_URL) are respected.
		$effective_base_url = OpenAiCompatibleProvider::url( '/' );
		$is_local           = preg_match( '#^https?://(localhost|127\.0\.0\.1|\[::1\])(:\d+)?(/|$)#i', $effective_base_url ) === 1;

		// Local inference is slow; force a generous timeout. Remote: only set if absent.
		if ( $is_local || $options->getTimeout() === null ) {
			$options->setTimeout( $is_local ? 120.0 : 60.0 );
		}

		// Local servers connect instantly; force a short connect timeout to detect misconfig fast.
		if ( $is_local || $options->getConnectTimeout() === null ) {
			$options->setConnectTimeout( $is_local ? 5.0 : 60.0 );
		}

		return new Request(
			$method,
			OpenAiCompatibleProvider::url( '/' . ltrim( $path, '/' ) ),
			$headers,
			$data,
			$options
		);
	}
}
