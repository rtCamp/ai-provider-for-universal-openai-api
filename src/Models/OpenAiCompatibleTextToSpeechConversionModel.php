<?php
/**
 * Text-to-speech conversion model for OpenAI-compatible endpoints.
 *
 * @since 1.2.0
 * @package rtCamp\AIProviderUniversalOpenAI
 */

declare( strict_types=1 );

namespace rtCamp\AIProviderUniversalOpenAI\Models;

use WordPress\AiClient\Files\DTO\File;
use WordPress\AiClient\Messages\DTO\Message;
use WordPress\AiClient\Messages\DTO\MessagePart;
use WordPress\AiClient\Messages\Enums\MessageRoleEnum;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModel;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Util\ResponseUtil;
use WordPress\AiClient\Providers\Models\TextToSpeechConversion\Contracts\TextToSpeechConversionModelInterface;
use WordPress\AiClient\Results\DTO\Candidate;
use WordPress\AiClient\Results\DTO\GenerativeAiResult;
use WordPress\AiClient\Results\DTO\TokenUsage;
use WordPress\AiClient\Results\Enums\FinishReasonEnum;
use rtCamp\AIProviderUniversalOpenAI\Provider\OpenAiCompatibleProvider;
use rtCamp\AIProviderUniversalOpenAI\Settings\OpenAiCompatibleSettings;

/**
 * Class OpenAiCompatibleTextToSpeechConversionModel.
 *
 * Handles speech synthesis requests sent to OpenAI-compatible audio speech endpoints.
 * Supports standard models like tts-1, tts-1-hd, and gpt-4o-mini-tts.
 *
 * @since 1.2.0
 */
class OpenAiCompatibleTextToSpeechConversionModel extends AbstractApiBasedModel implements
	TextToSpeechConversionModelInterface {

	/**
	 * Default fallback voice when none is configured or requested.
	 *
	 * @var string
	 */
	private const DEFAULT_VOICE = 'alloy';

	/**
	 * Default output audio MIME type.
	 *
	 * @var string
	 */
	private const DEFAULT_MIME_TYPE = 'audio/mpeg';

	/**
	 * Maps supported output MIME types to OpenAI-compatible response_format values.
	 *
	 * @var array<string, string>
	 */
	private const MIME_TYPE_TO_RESPONSE_FORMAT = [
		'audio/mpeg' => 'mp3',
		'audio/mp3'  => 'mp3',
		'audio/ogg'  => 'opus',
		'audio/wav'  => 'wav',
		'audio/flac' => 'flac',
		'audio/aac'  => 'aac',
	];

	/**
	 * Converts the provided text prompt into speech audio.
	 *
	 * Takes the incoming prompt messages, extracts the plain text, builds the
	 * POST request for the /audio/speech endpoint, sends it, and packages the
	 * returned audio bytes into a GenerativeAiResult.
	 *
	 * @since 1.2.0
	 *
	 * @param array<\WordPress\AiClient\Messages\DTO\Message> $prompt The prompt messages containing the text to synthesize.
	 * @phpstan-param list<\WordPress\AiClient\Messages\DTO\Message> $prompt
	 *
	 * @return \WordPress\AiClient\Results\DTO\GenerativeAiResult The speech generation result containing the synthesized audio.
	 */
	public function convertTextToSpeechResult( array $prompt ): GenerativeAiResult {
		$http_transporter = $this->getHttpTransporter();

		// Determine the desired audio format and prepare the request body parameters.
		$mime_type = $this->prepareOutputMimeType();
		$params    = $this->prepareConvertParams( $prompt, $mime_type );

		// Prepare the speech synthesis request directed at the /audio/speech endpoint.
		$request = new Request(
			HttpMethodEnum::POST(),
			OpenAiCompatibleProvider::url( 'audio/speech' ),
			[ 'Content-Type' => 'application/json' ],
			$params,
			$this->getRequestOptions()
		);

		// Add authentication credentials (API key header) to the request.
		$request = $this->getRequestAuthentication()->authenticateRequest( $request );

		// Send the request and make sure the server responded with a success status code.
		$response = $http_transporter->send( $request );
		ResponseUtil::throwIfNotSuccessful( $response );

		return $this->parseResponseToGenerativeAiResult( $response, $mime_type );
	}

	/**
	 * Prepares the request parameters for the Audio Speech API.
	 *
	 * Gathers the model name, text input, selected voice, output format, and
	 * any custom developer options (such as speed or instructions).
	 *
	 * @since 1.2.0
	 *
	 * @param array<\WordPress\AiClient\Messages\DTO\Message> $prompt    The prompt messages containing the text.
	 * @phpstan-param list<\WordPress\AiClient\Messages\DTO\Message> $prompt
	 * @param string                                          $mime_type The resolved output audio MIME type.
	 *
	 * @return array<string, mixed> The prepared request body parameters.
	 *
	 * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If a custom option clashes with a core parameter.
	 */
	protected function prepareConvertParams( array $prompt, string $mime_type ): array {
		$config = $this->getConfig();

		// Use the user's selected speech model from plugin settings if configured,
		// otherwise fall back to the model ID requested from metadata.
		$selected_model = OpenAiCompatibleSettings::get_selected_speech_model();
		$model_id       = '' !== $selected_model ? $selected_model : $this->metadata()->getId();

		$params = [
			'model'           => $model_id,
			'input'           => $this->preparePromptText( $prompt ),
			'voice'           => $this->prepareVoice(),
			'response_format' => self::MIME_TYPE_TO_RESPONSE_FORMAT[ $mime_type ],
		];

		// Include any extra custom options supplied by developers (e.g. 'speed' or 'instructions').
		// We guard against accidental overrides of primary parameters.
		$custom_options = $config->getCustomOptions();
		foreach ( $custom_options as $key => $value ) {
			if ( isset( $params[ $key ] ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message only.
				throw new \WordPress\AiClient\Common\Exception\InvalidArgumentException(
					sprintf(
						'The custom option "%s" conflicts with an existing parameter.',
						$key
					)
				);
			}
			$params[ $key ] = $value;
		}

		/**
		 * Filters the text-to-speech conversion parameters.
		 *
		 * @since 1.2.0
		 *
		 * @param array<string, mixed> $params The prepared API parameters.
		 */
		$params = apply_filters( 'ai_provider_for_universal_openai_api_tts_params', $params );

		return apply_filters( 'openai_compatible_tts_params', $params );
	}

	/**
	 * Extracts plain text to be converted to speech from prompt messages.
	 *
	 * Walks through all prompt messages and concatenates their text parts
	 * with newlines. Throws an error if no text is present.
	 *
	 * @since 1.2.0
	 *
	 * @param array<\WordPress\AiClient\Messages\DTO\Message> $prompt The prompt messages.
	 * @phpstan-param list<\WordPress\AiClient\Messages\DTO\Message> $prompt
	 *
	 * @return string The combined text ready for synthesis.
	 *
	 * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If the prompt contains no text content.
	 */
	protected function preparePromptText( array $prompt ): string {
		$text = '';
		foreach ( $prompt as $message ) {
			foreach ( $message->getParts() as $part ) {
				$part_text = $part->getText();
				if ( null === $part_text ) {
					continue;
				}

				$text .= ( '' === $text ? '' : "\n" ) . $part_text;
			}
		}

		if ( '' === $text ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message only.
			throw new \WordPress\AiClient\Common\Exception\InvalidArgumentException(
				'The prompt must contain text to convert to speech.'
			);
		}

		return $text;
	}

	/**
	 * Resolves which voice identifier to use for the speech synthesis.
	 *
	 * Checks in order:
	 * 1. Voice explicitly specified on the current request config.
	 * 2. Default voice saved in plugin settings by the user.
	 * 3. Fallback default voice ('alloy') provided by the API.
	 *
	 * @since 1.2.0
	 *
	 * @return string The voice name (e.g. alloy, ash, echo, etc.).
	 */
	protected function prepareVoice(): string {
		// First preference: voice specified in runtime request configuration.
		$voice = $this->getConfig()->getOutputSpeechVoice();
		if ( null !== $voice && '' !== $voice ) {
			return $voice;
		}

		// Second preference: default voice configured in the plugin settings screen.
		$configured_voice = OpenAiCompatibleSettings::get_selected_speech_voice();
		if ( '' !== $configured_voice ) {
			return $configured_voice;
		}

		// Fallback to standard OpenAI API default voice.
		return self::DEFAULT_VOICE;
	}

	/**
	 * Resolves and validates the desired output audio MIME type.
	 *
	 * Defaults to audio/mpeg (MP3) if unspecified, and validates against
	 * formats supported by the Audio Speech API.
	 *
	 * @since 1.2.0
	 *
	 * @return string Supported audio MIME type.
	 *
	 * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If an unsupported audio MIME type was configured.
	 */
	protected function prepareOutputMimeType(): string {
		$mime_type = $this->getConfig()->getOutputMimeType();
		if ( null === $mime_type || '' === $mime_type ) {
			return self::DEFAULT_MIME_TYPE;
		}

		if ( ! isset( self::MIME_TYPE_TO_RESPONSE_FORMAT[ $mime_type ] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception message only.
			throw new \WordPress\AiClient\Common\Exception\InvalidArgumentException(
				sprintf(
					'The output MIME type "%s" is not supported for text to speech.',
					$mime_type
				)
			);
		}

		return $mime_type;
	}

	/**
	 * Parses the raw binary audio response into a GenerativeAiResult.
	 *
	 * The OpenAI Audio Speech API returns raw binary audio data rather than JSON.
	 * We base64-encode the audio bytes, encapsulate them into an inline File DTO,
	 * wrap it inside a model message, and return the final GenerativeAiResult.
	 *
	 * @since 1.2.0
	 *
	 * @param \WordPress\AiClient\Providers\Http\DTO\Response $response  The HTTP response containing the audio bytes.
	 * @param string                                          $mime_type The resolved audio MIME type.
	 *
	 * @return \WordPress\AiClient\Results\DTO\GenerativeAiResult The generated AI result containing the audio file.
	 *
	 * @throws \WordPress\AiClient\Providers\Http\Exception\ResponseException If the response body is empty.
	 */
	protected function parseResponseToGenerativeAiResult( Response $response, string $mime_type ): GenerativeAiResult {
		$body = $response->getBody();
		if ( null === $body || '' === $body ) {
			throw \WordPress\AiClient\Providers\Http\Exception\ResponseException::fromMissingData(
				$this->providerMetadata()->getName(),
				'body'
			);
		}

		// Wrap raw audio bytes into an inline File DTO.
		$audio_file = new File( base64_encode( $body ), $mime_type );

		// Assemble candidate and result message holding the generated audio.
		$message   = new Message( MessageRoleEnum::model(), [ new MessagePart( $audio_file ) ] );
		$candidate = new Candidate( $message, FinishReasonEnum::stop() );

		return new GenerativeAiResult(
			$this->generateResultId(),
			[ $candidate ],
			new TokenUsage( 0, 0, 0 ),
			$this->providerMetadata(),
			$this->metadata()
		);
	}

	/**
	 * Generates a unique result identifier for the synthesis output.
	 *
	 * @since 1.2.0
	 *
	 * @return string A unique result ID.
	 */
	protected function generateResultId(): string {
		return uniqid( 'openai-compatible-tts-', true );
	}
}
