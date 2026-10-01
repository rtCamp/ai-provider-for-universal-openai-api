<?php
/**
 * Image generation model for OpenAI-compatible endpoints.
 *
 * @since 1.0.0
 * @package rtCamp\AIProviderUniversalOpenAI
 */

declare( strict_types=1 );

namespace rtCamp\AIProviderUniversalOpenAI\Models;

use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleImageGenerationModel;
use rtCamp\AIProviderUniversalOpenAI\Provider\OpenAiCompatibleProvider;
use rtCamp\AIProviderUniversalOpenAI\Settings\OpenAiCompatibleSettings;

/**
 * Class OpenAiCompatibleImageGenerationModel.
 */
class OpenAiCompatibleImageGenerationModel extends AbstractOpenAiCompatibleImageGenerationModel {

	/**
	 * {@inheritDoc}
	 *
	 * @param array<\WordPress\AiClient\Messages\DTO\Message> $prompt The prompt array to prepare parameters for.
	 * @phpstan-param list<\WordPress\AiClient\Messages\DTO\Message> $prompt
	 *
	 * @return array<string, mixed> The prepared parameters for image generation.
	 */
	protected function prepareGenerateImageParams( array $prompt ): array {
		$params = parent::prepareGenerateImageParams( $prompt );

		$selected_model = OpenAiCompatibleSettings::get_selected_image_model();
		if ( '' !== $selected_model ) {
			$params['model'] = $selected_model;
		}

		// Many strict OpenAI-compatible backends reject OpenAI-specific parameters.
		// Only send them when talking to the real OpenAI API.
		$endpoint = OpenAiCompatibleSettings::get_endpoint_url();
		if ( strpos( $endpoint, 'api.openai.com' ) === false ) {
			unset( $params['response_format'] );
			unset( $params['n'] );
			unset( $params['quality'] );
			unset( $params['style'] );
		}

		return apply_filters( 'openai_compatible_image_generation_params', $params );
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
		$options->setTimeout( 300.0 ); // Image generation can take a while, so we set a longer timeout.

		return new Request(
			$method,
			OpenAiCompatibleProvider::url( '/' . ltrim( $path, '/' ) ),
			$headers,
			$data,
			$options
		);
	}
}
