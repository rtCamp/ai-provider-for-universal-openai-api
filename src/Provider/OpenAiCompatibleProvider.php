<?php
/**
 * OpenAI Compatible provider.
 *
 * @since 1.0.0
 * @package rtCamp\AIProviderUniversalOpenAI
 */

declare( strict_types=1 );

namespace rtCamp\AIProviderUniversalOpenAI\Provider;

use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use rtCamp\AIProviderUniversalOpenAI\Metadata\OpenAiCompatibleModelMetadataDirectory;
use rtCamp\AIProviderUniversalOpenAI\Models\OpenAiCompatibleImageGenerationModel;
use rtCamp\AIProviderUniversalOpenAI\Models\OpenAiCompatibleTextGenerationModel;
use rtCamp\AIProviderUniversalOpenAI\Settings\OpenAiCompatibleSettings;

/**
 * Class OpenAiCompatibleProvider.
 */
class OpenAiCompatibleProvider extends AbstractApiProvider {

	private const DEFAULT_BASE_URL = 'https://api.openai.com/v1';

	/**
	 * {@inheritDoc}
	 */
	protected static function baseUrl(): string {
		$env_url = getenv( 'OPENAI_COMPATIBLE_BASE_URL' );
		if ( false !== $env_url && '' !== trim( $env_url ) ) {
			return rtrim( (string) $env_url, '/' );
		}

		$settings_url = OpenAiCompatibleSettings::get_endpoint_url();
		if ( '' !== $settings_url ) {
			return rtrim( $settings_url, '/' );
		}

		return self::DEFAULT_BASE_URL;
	}

	/**
	 * {@inheritDoc}
	 *
	 * Overrides the base URL construction to support dynamic custom routing filters.
	 *
	 * @since 1.0.2
	 *
	 * @param string $path Optional path to append to the base URL. Default empty string.
	 * @return string The complete URL.
	 */
	public static function url( string $path = '' ): string {
		$base_url = static::baseUrl();
		$url      = parent::url( $path );

		$url = apply_filters( 'ai_provider_for_universal_openai_api_url', $url, $path, $base_url );

		return apply_filters( 'universal_openai_connector_url', $url, $path, $base_url );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param \WordPress\AiClient\Providers\Models\DTO\ModelMetadata $model_metadata    The model metadata.
	 * @param \WordPress\AiClient\Providers\DTO\ProviderMetadata     $provider_metadata The provider metadata.
	 * @return \WordPress\AiClient\Providers\Models\Contracts\ModelInterface The created model.
	 *
	 * @throws \WordPress\AiClient\Common\Exception\RuntimeException If the model capabilities are unsupported.
	 */
	protected static function createModel( ModelMetadata $model_metadata, ProviderMetadata $provider_metadata ): ModelInterface {
		$capabilities_string_list = $model_metadata->toArray()[ ModelMetadata::KEY_SUPPORTED_CAPABILITIES ];

		if ( in_array( 'image_generation', $capabilities_string_list, true ) ) {
			return new OpenAiCompatibleImageGenerationModel( $model_metadata, $provider_metadata );
		}

		$capabilities = $model_metadata->getSupportedCapabilities();
		foreach ( $capabilities as $capability ) {
			if ( $capability->isTextGeneration() ) {
				return new OpenAiCompatibleTextGenerationModel( $model_metadata, $provider_metadata );
			}
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message only.
		throw new \WordPress\AiClient\Common\Exception\RuntimeException( 'Unsupported model capabilities for OpenAI compatible model: ' . $model_metadata->getId() );
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function createProviderMetadata(): ProviderMetadata {
		return new ProviderMetadata(
			'openai_compatible',
			__( 'AI Provider for Universal OpenAI API', 'ai-provider-for-universal-openai-api' ),
			ProviderTypeEnum::cloud(),
			'',
			RequestAuthenticationMethod::apiKey(),
			__( 'Access any AI service supporting the OpenAI API for text and image creation.', 'ai-provider-for-universal-openai-api' ),
			AI_PROVIDER_FOR_UNIVERSAL_OPENAI_API_PLUGIN_DIR . 'assets/images/openai-api.svg'
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function createProviderAvailability(): ProviderAvailabilityInterface {
		return new OpenAiCompatibleProviderAvailability();
	}

	/**
	 * {@inheritDoc}
	 */
	protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface {
		return new OpenAiCompatibleModelMetadataDirectory();
	}
}
