<?php
/**
 * Model metadata directory for OpenAI-compatible provider.
 *
 * @since 1.0.0
 * @package rtCamp\AIProviderUniversalOpenAI
 */

declare( strict_types=1 );

namespace rtCamp\AIProviderUniversalOpenAI\Metadata;

use WordPress\AiClient\Files\Enums\FileTypeEnum;
use WordPress\AiClient\Files\Enums\MediaOrientationEnum;
use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModelMetadataDirectory;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;
use rtCamp\AIProviderUniversalOpenAI\Settings\OpenAiCompatibleSettings;

/**
 * Class OpenAiCompatibleModelMetadataDirectory.
 */
class OpenAiCompatibleModelMetadataDirectory extends AbstractApiBasedModelMetadataDirectory {

	/**
	 * Returns metadata for available models.
	 *
	 * Fetches all models returned by the configured endpoint and maps each model
	 * to its appropriate ModelMetadata object (text, image, or combined).
	 * Also ensures any configured effective text and image models are included.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, \WordPress\AiClient\Providers\Models\DTO\ModelMetadata>
	 */
	protected function sendListModelsRequest(): array {
		$models_map = [];

		$all_models = OpenAiCompatibleSettings::fetch_all_models();
		foreach ( $all_models as $model ) {
			if ( ! is_array( $model ) ) {
				continue;
			}

			$model_id = isset( $model['id'] ) ? trim( (string) $model['id'] ) : '';
			if ( '' === $model_id ) {
				continue;
			}

			$is_image  = ! empty( $model['is_image'] );
			$is_speech = ! empty( $model['is_speech'] ) || OpenAiCompatibleSettings::is_likely_speech_model( $model_id );
			if ( $is_image ) {
				$models_map[ $model_id ] = $this->createImageModelMetadata( $model_id );
			} elseif ( $is_speech ) {
				$models_map[ $model_id ] = $this->createTtsModelMetadata( $model_id );
			} else {
				$models_map[ $model_id ] = $this->createTextModelMetadata( $model_id );
			}
		}

		$text_model = trim( OpenAiCompatibleSettings::get_effective_text_model() );
		if ( '' !== $text_model ) {
			if ( ! isset( $models_map[ $text_model ] ) ) {
				$models_map[ $text_model ] = $this->createTextModelMetadata( $text_model );
			}
		}

		$image_model = trim( OpenAiCompatibleSettings::get_effective_image_model() );
		if ( '' !== $image_model ) {
			if ( isset( $models_map[ $image_model ] ) && $image_model === $text_model ) {
				$models_map[ $image_model ] = $this->createCombinedModelMetadata( $image_model );
			} elseif ( ! isset( $models_map[ $image_model ] ) ) {
				$models_map[ $image_model ] = $this->createImageModelMetadata( $image_model );
			}
		}

		$speech_model = trim( OpenAiCompatibleSettings::get_effective_speech_model() );
		if ( '' !== $speech_model ) {
			if ( ! isset( $models_map[ $speech_model ] ) ) {
				$models_map[ $speech_model ] = $this->createTtsModelMetadata( $speech_model );
			}
		}

		ksort( $models_map );

		return $models_map;
	}

	/**
	 * Creates text generation metadata.
	 *
	 * @since 1.0.0
	 *
	 * @param string $model_id Model identifier.
	 */
	private function createTextModelMetadata( string $model_id ): ModelMetadata {
		return new ModelMetadata(
			$model_id,
			$model_id,
			[
				CapabilityEnum::textGeneration(),
				CapabilityEnum::chatHistory(),
			],
			[
				new SupportedOption( OptionEnum::systemInstruction() ),
				new SupportedOption( OptionEnum::candidateCount() ),
				new SupportedOption( OptionEnum::maxTokens() ),
				new SupportedOption( OptionEnum::temperature() ),
				new SupportedOption( OptionEnum::topP() ),
				new SupportedOption( OptionEnum::stopSequences() ),
				new SupportedOption( OptionEnum::frequencyPenalty() ),
				new SupportedOption( OptionEnum::presencePenalty() ),
				new SupportedOption( OptionEnum::outputMimeType(), [ 'text/plain', 'application/json' ] ),
				new SupportedOption( OptionEnum::outputSchema() ),
				new SupportedOption( OptionEnum::functionDeclarations() ),
				new SupportedOption( OptionEnum::customOptions() ),
				new SupportedOption( OptionEnum::outputModalities(), [ [ ModalityEnum::text() ] ] ),
				new SupportedOption( OptionEnum::inputModalities(), [ [ ModalityEnum::text() ], [ ModalityEnum::text(), ModalityEnum::image() ] ] ),
			]
		);
	}

	/**
	 * Creates image generation metadata.
	 *
	 * @since 1.0.0
	 *
	 * @param string $model_id Model identifier.
	 */
	private function createImageModelMetadata( string $model_id ): ModelMetadata {
		return new ModelMetadata(
			$model_id,
			$model_id,
			[
				CapabilityEnum::imageGeneration(),
			],
			[
				new SupportedOption( OptionEnum::inputModalities(), [ [ ModalityEnum::text() ] ] ),
				new SupportedOption( OptionEnum::outputModalities(), [ [ ModalityEnum::image() ] ] ),
				new SupportedOption( OptionEnum::candidateCount() ),
				new SupportedOption( OptionEnum::outputMimeType(), [ 'image/png' ] ),
				new SupportedOption( OptionEnum::outputFileType(), [ FileTypeEnum::inline(), FileTypeEnum::remote() ] ),
				new SupportedOption(
					OptionEnum::outputMediaOrientation(),
					[
						MediaOrientationEnum::square(),
						MediaOrientationEnum::landscape(),
						MediaOrientationEnum::portrait(),
					]
				),
				new SupportedOption( OptionEnum::outputMediaAspectRatio(), [ '1:1', '3:2', '2:3', '7:4', '4:7' ] ),
				new SupportedOption( OptionEnum::customOptions() ),
			]
		);
	}

	/**
	 * Creates metadata for a model selected as both text and image model.
	 *
	 * @since 1.0.0
	 *
	 * @param string $model_id Model identifier.
	 */
	private function createCombinedModelMetadata( string $model_id ): ModelMetadata {
		return new ModelMetadata(
			$model_id,
			$model_id,
			[
				CapabilityEnum::textGeneration(),
				CapabilityEnum::chatHistory(),
				CapabilityEnum::imageGeneration(),
			],
			[
				new SupportedOption( OptionEnum::systemInstruction() ),
				new SupportedOption( OptionEnum::candidateCount() ),
				new SupportedOption( OptionEnum::maxTokens() ),
				new SupportedOption( OptionEnum::temperature() ),
				new SupportedOption( OptionEnum::topP() ),
				new SupportedOption( OptionEnum::stopSequences() ),
				new SupportedOption( OptionEnum::frequencyPenalty() ),
				new SupportedOption( OptionEnum::presencePenalty() ),
				new SupportedOption( OptionEnum::outputMimeType(), [ 'text/plain', 'application/json' ] ),
				new SupportedOption( OptionEnum::outputSchema() ),
				new SupportedOption( OptionEnum::functionDeclarations() ),
				new SupportedOption( OptionEnum::customOptions() ),
				new SupportedOption( OptionEnum::outputModalities(), [ [ ModalityEnum::text() ] ] ),
				new SupportedOption( OptionEnum::inputModalities(), [ [ ModalityEnum::text() ], [ ModalityEnum::text(), ModalityEnum::image() ] ] ),
			]
		);
	}

	/**
	 * Creates text-to-speech conversion metadata.
	 *
	 * Defines supported input/output modalities, MIME formats, and voice options
	 * for synthesizing speech using OpenAI-compatible speech models.
	 *
	 * @since 1.2.0
	 *
	 * @param string $model_id Model identifier.
	 * @return \WordPress\AiClient\Providers\Models\DTO\ModelMetadata The configured model metadata.
	 */
	private function createTtsModelMetadata( string $model_id ): ModelMetadata {
		$supported_voices = array_keys( OpenAiCompatibleSettings::get_supported_voices() );

		return new ModelMetadata(
			$model_id,
			$model_id,
			[
				CapabilityEnum::textToSpeechConversion(),
			],
			[
				new SupportedOption( OptionEnum::inputModalities(), [ [ ModalityEnum::text() ] ] ),
				new SupportedOption( OptionEnum::outputModalities(), [ [ ModalityEnum::audio() ] ] ),
				new SupportedOption(
					OptionEnum::outputMimeType(),
					[
						'audio/mpeg',
						'audio/ogg',
						'audio/wav',
						'audio/flac',
						'audio/aac',
					]
				),
				new SupportedOption( OptionEnum::outputFileType(), [ FileTypeEnum::inline() ] ),
				new SupportedOption( OptionEnum::outputSpeechVoice(), $supported_voices ),
				new SupportedOption( OptionEnum::customOptions() ),
			]
		);
	}
}
