<?php
/**
 * OpenAI Compatible provider.
 *
 * @since 1.0.0
 * @package rtCamp\AIProviderUniversalOpenAI
 */

declare( strict_types=1 );

namespace rtCamp\AIProviderUniversalOpenAI\Provider;

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelConfig;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;
use rtCamp\AIProviderUniversalOpenAI\Metadata\OpenAiCompatibleModelMetadataDirectory;
use rtCamp\AIProviderUniversalOpenAI\Models\OpenAiCompatibleDecisionModel;
use rtCamp\AIProviderUniversalOpenAI\Models\OpenAiCompatibleImageGenerationModel;
use rtCamp\AIProviderUniversalOpenAI\Models\OpenAiCompatibleTextGenerationModel;
use rtCamp\AIProviderUniversalOpenAI\Models\OpenAiCompatibleTextToSpeechConversionModel;
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

		if ( in_array( 'text_to_speech_conversion', $capabilities_string_list, true ) ) {
			return new OpenAiCompatibleTextToSpeechConversionModel( $model_metadata, $provider_metadata );
		}

		$capabilities = $model_metadata->getSupportedCapabilities();
		foreach ( $capabilities as $capability ) {
			if ( $capability->isTextToSpeechConversion() ) {
				return new OpenAiCompatibleTextToSpeechConversionModel( $model_metadata, $provider_metadata );
			}
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
			__( 'Access any AI service supporting the OpenAI API for text, image, and speech generation.', 'ai-provider-for-universal-openai-api' ),
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

	/**
	 * Returns a decision model, ready to answer questions.
	 *
	 * The WordPress AI Client has no built-in decision capability in CapabilityEnum,
	 * so decision models are resolved directly through the provider:
	 *
	 *     $result = OpenAiCompatibleProvider::decisionModel( 'gpt-6-luna' )->decide(
	 *         $input_text,
	 *         [
	 *             'urgency' => DecisionQuestion::predicate( 'Is this urgent?' ),
	 *         ]
	 *     );
	 *
	 * @since 1.3.0
	 *
	 * @param string|null                                               $model_id     Optional. Model ID. If null, uses the default
	 *                                                                                decision model from settings.
	 * @param \WordPress\AiClient\Providers\Models\DTO\ModelConfig|null $model_config Optional. Model configuration.
	 * @return \rtCamp\AIProviderUniversalOpenAI\Models\OpenAiCompatibleDecisionModel The decision model.
	 */
	public static function decisionModel( ?string $model_id = null, ?ModelConfig $model_config = null ): OpenAiCompatibleDecisionModel {
		$effective_model_id = null !== $model_id && '' !== trim( $model_id )
			? trim( $model_id )
			: OpenAiCompatibleSettings::get_effective_decision_model();

		if ( '' === $effective_model_id ) {
			$effective_model_id = OpenAiCompatibleSettings::get_effective_text_model();
		}

		if ( '' === $effective_model_id ) {
			$effective_model_id = 'gpt-6-luna';
		}

		$model_metadata = new ModelMetadata(
			$effective_model_id,
			$effective_model_id,
			[],
			[ new SupportedOption( OptionEnum::customOptions() ) ]
		);

		$model = new OpenAiCompatibleDecisionModel( $model_metadata, static::metadata() );
		if ( null !== $model_config ) {
			$model->setConfig( $model_config );
		}

		$registry = AiClient::defaultRegistry();
		if ( $registry->hasProvider( static::class ) ) {
			$registry->bindModelDependencies( $model );
		}

		return $model;
	}

	/**
	 * Lists the models on the configured endpoint that support decisions.
	 *
	 * @since 1.3.0
	 *
	 * @return list<string> The model IDs, sorted.
	 */
	public static function decisionModelIds(): array {
		$all_models      = OpenAiCompatibleSettings::fetch_all_models();
		$decision_models = [];

		foreach ( $all_models as $model ) {
			if ( ! is_array( $model ) || empty( $model['id'] ) ) {
				continue;
			}
			$model_id = (string) $model['id'];
			if ( ! OpenAiCompatibleSettings::is_likely_decision_model( $model_id ) ) {
				continue;
			}

			$decision_models[] = $model_id;
		}

		$decision_models = apply_filters( 'openai_compatible_decision_models', $decision_models );
		if ( ! is_array( $decision_models ) ) {
			$decision_models = [];
		}

		$decision_models = array_values( array_unique( $decision_models ) );
		sort( $decision_models );

		return $decision_models;
	}
}
