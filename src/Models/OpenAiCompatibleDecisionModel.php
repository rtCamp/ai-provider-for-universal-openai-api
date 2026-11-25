<?php
/**
 * Decision model for OpenAI-compatible endpoints.
 *
 * @since 1.2.0
 * @package rtCamp\AIProviderUniversalOpenAI
 */

declare( strict_types=1 );

namespace rtCamp\AIProviderUniversalOpenAI\Models;

use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiBasedModel;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Util\ResponseUtil;
use WordPress\AiClient\Results\DTO\TokenUsage;
use rtCamp\AIProviderUniversalOpenAI\Decisions\DecisionAnswer;
use rtCamp\AIProviderUniversalOpenAI\Decisions\DecisionQuestion;
use rtCamp\AIProviderUniversalOpenAI\Decisions\DecisionResult;
use rtCamp\AIProviderUniversalOpenAI\Provider\OpenAiCompatibleProvider;
use rtCamp\AIProviderUniversalOpenAI\Settings\OpenAiCompatibleSettings;

/**
 * Class OpenAiCompatibleDecisionModel.
 *
 * Evaluates inputs against typed questions using the OpenAI-compatible /v1/decisions endpoint.
 *
 * @since 1.2.0
 *
 * @phpstan-type ResponseData array{
 *     model?: string,
 *     answers?: array<string, mixed>,
 *     output?: list<array<string, mixed>>,
 *     questions?: list<array<string, mixed>>,
 *     usage?: array{input_tokens?: int, output_tokens?: int, total_tokens?: int}
 * }
 */
class OpenAiCompatibleDecisionModel extends AbstractApiBasedModel {

	/**
	 * The maximum number of questions accepted in one decision request.
	 *
	 * @since 1.2.0
	 *
	 * @var int
	 */
	public const MAX_QUESTIONS = 64;

	/**
	 * The maximum request body size in bytes (20 MB).
	 *
	 * @since 1.2.0
	 *
	 * @var int
	 */
	public const MAX_BODY_BYTES = 20971520;

	/**
	 * Evaluates state against typed questions and returns structured decisions.
	 *
	 * @since 1.2.0
	 *
	 * @param string|array<mixed>                                                         $state     The input to evaluate: a non-empty string or array.
	 * @param array<string, \rtCamp\AIProviderUniversalOpenAI\Decisions\DecisionQuestion> $questions Between 1 and 64 questions, keyed by question name.
	 * @return \rtCamp\AIProviderUniversalOpenAI\Decisions\DecisionResult The structured answers and token usage.
	 * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If state or questions are invalid.
	 */
	public function decide( $state, array $questions ): DecisionResult {
		$params  = $this->prepareDecideParams( $state, $questions );
		$request = new Request(
			HttpMethodEnum::POST(),
			OpenAiCompatibleProvider::url( 'decisions' ),
			[ 'Content-Type' => 'application/json' ],
			$params,
			$this->getRequestOptions()
		);

		$body_bytes = strlen( (string) $request->getBody() );
		if ( $body_bytes > self::MAX_BODY_BYTES ) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new \WordPress\AiClient\Common\Exception\InvalidArgumentException(
				sprintf(
					'The decision request is %1$d bytes; maximum accepted is %2$d.',
					$body_bytes,
					self::MAX_BODY_BYTES
				)
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$request  = $this->getRequestAuthentication()->authenticateRequest( $request );
		$response = $this->getHttpTransporter()->send( $request );

		ResponseUtil::throwIfNotSuccessful( $response );

		return $this->parseResponseToDecisionResult( $response, $questions );
	}

	/**
	 * Prepares request parameters for the /v1/decisions endpoint.
	 *
	 * @since 1.2.0
	 *
	 * @param mixed                                                                       $state     The input to evaluate.
	 * @param array<string, \rtCamp\AIProviderUniversalOpenAI\Decisions\DecisionQuestion> $questions The questions keyed by name.
	 * @return array<string, mixed> The prepared API parameters.
	 * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If inputs are invalid.
	 */
	private function prepareDecideParams( $state, array $questions ): array {
		if ( is_string( $state ) ? '' === trim( $state ) : ( ! is_array( $state ) || [] === $state ) ) {
			throw new \WordPress\AiClient\Common\Exception\InvalidArgumentException( 'Decision state must be a non-empty string or array.' );
		}

		$question_count = count( $questions );
		if ( 0 === $question_count || $question_count > self::MAX_QUESTIONS ) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new \WordPress\AiClient\Common\Exception\InvalidArgumentException(
				sprintf( 'A decision request needs between 1 and %1$d questions, %2$d given.', self::MAX_QUESTIONS, $question_count )
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		foreach ( $questions as $name => $question ) {
			if ( ! is_string( $name ) || '' === trim( $name ) ) {
				throw new \WordPress\AiClient\Common\Exception\InvalidArgumentException( 'Decision questions must be keyed by non-empty string names.' );
			}

			if ( ! $question instanceof DecisionQuestion ) {
				// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
				throw new \WordPress\AiClient\Common\Exception\InvalidArgumentException(
					sprintf( 'The decision question "%s" must be an instance of DecisionQuestion.', $name )
				);
				// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
		}

		$model_id = $this->metadata()->getId();
		if ( '' === $model_id || 'default' === $model_id ) {
			$model_id = OpenAiCompatibleSettings::get_effective_decision_model();
		}
		if ( '' === $model_id ) {
			$model_id = OpenAiCompatibleSettings::get_effective_text_model();
		}
		if ( '' === $model_id ) {
			$model_id = 'gpt-6-luna';
		}

		$questions_list = [];
		foreach ( $questions as $name => $question ) {
			$questions_list[] = $question->toOpenAIDecisionArray( $name );
		}

		$params = [
			'model'     => $model_id,
			'input'     => $state,
			'questions' => $questions_list,
		];

		// Apply custom options configured for this model.
		foreach ( $this->getConfig()->getCustomOptions() as $key => $value ) {
			if ( isset( $params[ $key ] ) ) {
				continue;
			}

			$params[ $key ] = $value;
		}

		$params = apply_filters( 'ai_provider_for_universal_openai_api_decision_params', $params );

		return apply_filters( 'openai_compatible_decision_params', $params );
	}

	/**
	 * Parses the API response into a DecisionResult.
	 *
	 * @since 1.2.0
	 *
	 * @param \WordPress\AiClient\Providers\Http\DTO\Response                             $response  The HTTP response.
	 * @param array<string, \rtCamp\AIProviderUniversalOpenAI\Decisions\DecisionQuestion> $questions The map of questions asked.
	 * @return \rtCamp\AIProviderUniversalOpenAI\Decisions\DecisionResult The parsed decision result.
	 * @throws \WordPress\AiClient\Providers\Http\Exception\ResponseException If response is missing answer data.
	 */
	private function parseResponseToDecisionResult( Response $response, array $questions ): DecisionResult {
		/** @var ResponseData $response_data */
		$response_data = $response->getData();
		$raw_answers   = $this->extractRawAnswers( $response_data );

		if ( ! is_array( $raw_answers ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw \WordPress\AiClient\Providers\Http\Exception\ResponseException::fromMissingData( $this->providerMetadata()->getName(), 'answers' );
		}

		$answers = [];
		foreach ( $questions as $name => $question ) {
			if ( ! array_key_exists( $name, $raw_answers ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				throw \WordPress\AiClient\Providers\Http\Exception\ResponseException::fromMissingData( $this->providerMetadata()->getName(), 'answers.' . $name );
			}

			$answers[ $name ] = DecisionAnswer::fromResponseData(
				$question->getType(),
				$raw_answers[ $name ],
				'answers.' . $name
			);
		}

		$usage         = isset( $response_data['usage'] ) && is_array( $response_data['usage'] ) ? $response_data['usage'] : [];
		$input_tokens  = isset( $usage['input_tokens'] ) && is_int( $usage['input_tokens'] ) ? $usage['input_tokens'] : 0;
		$output_tokens = isset( $usage['output_tokens'] ) && is_int( $usage['output_tokens'] ) ? $usage['output_tokens'] : 0;
		$total_tokens  = isset( $usage['total_tokens'] ) && is_int( $usage['total_tokens'] )
			? $usage['total_tokens']
			: $input_tokens + $output_tokens;

		$model_id = isset( $response_data['model'] ) && is_string( $response_data['model'] )
			? $response_data['model']
			: $this->metadata()->getId();

		return new DecisionResult(
			$model_id,
			$answers,
			new TokenUsage( $input_tokens, $output_tokens, $total_tokens )
		);
	}

	/**
	 * Extracts answers dictionary from the API response payload.
	 *
	 * Supports answers object, output list, and questions list.
	 *
	 * @since 1.2.0
	 *
	 * @param array<string, mixed> $response_data The response data array.
	 * @return array<string, mixed>|null The extracted answers map, or null if none found.
	 */
	private function extractRawAnswers( array $response_data ): ?array {
		if ( isset( $response_data['answers'] ) && is_array( $response_data['answers'] ) ) {
			// If answers is an associative map keyed by question name, return directly.
			if ( ! $this->isArrayList( $response_data['answers'] ) ) {
				return $response_data['answers'];
			}

			// If answers is a list of answer objects [ {"name": "...", ...}, ... ], re-key by name.
			$answers = [];
			foreach ( $response_data['answers'] as $item ) {
				if ( ! is_array( $item ) || ! isset( $item['name'] ) || ! is_string( $item['name'] ) ) {
					continue;
				}

				$answers[ $item['name'] ] = $item;
			}
			return $answers;
		}

		$list = null;
		if ( isset( $response_data['output'] ) && is_array( $response_data['output'] ) ) {
			$list = $response_data['output'];
		} elseif ( isset( $response_data['questions'] ) && is_array( $response_data['questions'] ) ) {
			$list = $response_data['questions'];
		}

		if ( null === $list ) {
			return null;
		}

		$answers = [];
		foreach ( $list as $item ) {
			if ( ! is_array( $item ) || ! isset( $item['name'] ) || ! is_string( $item['name'] ) ) {
				continue;
			}

			$answers[ $item['name'] ] = $item['decision'] ?? $item['answer'] ?? null;
		}

		return $answers;
	}

	/**
	 * Determines if an array is a zero-indexed list of sequential integers.
	 *
	 * Polyfill-safe replacement for PHP 8.1 array_is_list() to ensure PHP 7.4 compatibility.
	 *
	 * @since 1.2.0
	 *
	 * @param array<mixed> $arr The array to check.
	 * @return bool True if list, false otherwise.
	 */
	private function isArrayList( array $arr ): bool {
		if ( [] === $arr ) {
			return true;
		}

		if ( function_exists( 'array_is_list' ) ) {
			return array_is_list( $arr );
		}

		return array_keys( $arr ) === range( 0, count( $arr ) - 1 );
	}
}
