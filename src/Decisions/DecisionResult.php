<?php
/**
 * The result of a decision request.
 *
 * @package rtCamp\AIProviderUniversalOpenAI\Decisions
 * @since   1.3.0
 */

declare( strict_types=1 );

namespace rtCamp\AIProviderUniversalOpenAI\Decisions;

// phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- camelCase, matching the AI Client DTOs.

use WordPress\AiClient\Results\DTO\TokenUsage;

/**
 * Class for the answers a decision model gave to a set of questions.
 *
 * @since 1.3.0
 */
class DecisionResult {

	/**
	 * The model that answered.
	 *
	 * @since 1.3.0
	 *
	 * @var string
	 */
	private string $model_id;

	/**
	 * The answers, keyed by question name.
	 *
	 * @since 1.3.0
	 *
	 * @var array<string, \rtCamp\AIProviderUniversalOpenAI\Decisions\DecisionAnswer>
	 */
	private array $answers;

	/**
	 * The token usage.
	 *
	 * @since 1.3.0
	 *
	 * @var \WordPress\AiClient\Results\DTO\TokenUsage
	 */
	private TokenUsage $token_usage;

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 *
	 * @param string                                                                    $model_id    The model that answered.
	 * @param array<string, \rtCamp\AIProviderUniversalOpenAI\Decisions\DecisionAnswer> $answers     The answers, keyed by question name.
	 * @param \WordPress\AiClient\Results\DTO\TokenUsage                                $token_usage The token usage.
	 */
	public function __construct( string $model_id, array $answers, TokenUsage $token_usage ) {
		$this->model_id    = $model_id;
		$this->answers     = $answers;
		$this->token_usage = $token_usage;
	}

	/**
	 * Returns the model that answered.
	 *
	 * @since 1.3.0
	 *
	 * @return string The model ID.
	 */
	public function getModelId(): string {
		return $this->model_id;
	}

	/**
	 * Returns every answer, keyed by question name.
	 *
	 * @since 1.3.0
	 *
	 * @return array<string, \rtCamp\AIProviderUniversalOpenAI\Decisions\DecisionAnswer> The answers.
	 */
	public function getAnswers(): array {
		return $this->answers;
	}

	/**
	 * Checks whether there is an answer for the given question.
	 *
	 * @since 1.3.0
	 *
	 * @param string $name The question name.
	 * @return bool True if the question was answered.
	 */
	public function hasAnswer( string $name ): bool {
		return isset( $this->answers[ $name ] );
	}

	/**
	 * Returns the answer for the given question.
	 *
	 * @since 1.3.0
	 *
	 * @param string $name The question name.
	 * @return \rtCamp\AIProviderUniversalOpenAI\Decisions\DecisionAnswer The answer.
	 * @throws \OutOfBoundsException If no answer exists for the question.
	 */
	public function getAnswer( string $name ): DecisionAnswer {
		if ( ! isset( $this->answers[ $name ] ) ) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new \OutOfBoundsException(
				sprintf( 'No decision answer exists for question "%s".', $name )
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		return $this->answers[ $name ];
	}

	/**
	 * Returns the token usage for this decision request.
	 *
	 * @since 1.3.0
	 *
	 * @return \WordPress\AiClient\Results\DTO\TokenUsage The token usage.
	 */
	public function getTokenUsage(): TokenUsage {
		return $this->token_usage;
	}
}
