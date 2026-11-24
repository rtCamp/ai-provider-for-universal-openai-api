<?php
/**
 * An answer from an OpenAI-compatible decision model.
 *
 * @package rtCamp\AIProviderUniversalOpenAI\Decisions
 * @since   1.3.0
 */

declare( strict_types=1 );

namespace rtCamp\AIProviderUniversalOpenAI\Decisions;

// phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- camelCase, matching the AI Client DTOs.


/**
 * Class for the typed answer to a single decision question.
 *
 * Which getters return a value depends on the question type:
 *  - predicate/noul: getProbability(), getValue().
 *  - choice:         getChoice(), getProbabilities(), getConfidence().
 *  - score:          getScore(), getProbabilities(), getConfidence(), getLegend().
 *
 * @since 1.3.0
 */
class DecisionAnswer {

	/**
	 * The question type.
	 *
	 * @since 1.3.0
	 *
	 * @var \rtCamp\AIProviderUniversalOpenAI\Decisions\DecisionTypeEnum
	 */
	private DecisionTypeEnum $type;

	/**
	 * The answer value: the option key for choice, a probability for predicate, a position/rating for score.
	 *
	 * @since 1.3.0
	 *
	 * @var string|float
	 */
	private $value;

	/**
	 * The probability of each option or level.
	 *
	 * @since 1.3.0
	 *
	 * @var array<string, float>
	 */
	private array $probabilities;

	/**
	 * Confidence distribution score from 0 to 1, or null when unavailable.
	 *
	 * @since 1.3.0
	 *
	 * @var float|null
	 */
	private ?float $confidence;

	/**
	 * The score level descriptions, keyed by level index or name.
	 *
	 * @since 1.3.0
	 *
	 * @var array<string, string>
	 */
	private array $legend;

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 *
	 * @param \rtCamp\AIProviderUniversalOpenAI\Decisions\DecisionTypeEnum $type          The question type.
	 * @param string|float                                                 $value         The answer value.
	 * @param array<string, float>                                         $probabilities The probability of each option or level.
	 * @param float|null                                                   $confidence    The confidence metric, or null.
	 * @param array<string, string>                                        $legend        The score level descriptions.
	 */
	public function __construct(
		DecisionTypeEnum $type,
		$value,
		array $probabilities = [],
		?float $confidence = null,
		array $legend = []
	) {
		$this->type          = $type;
		$this->value         = $value;
		$this->probabilities = $probabilities;
		$this->confidence    = $confidence;
		$this->legend        = $legend;
	}

	/**
	 * Creates an answer from the API response data for one question.
	 *
	 * Handles both OpenAI-compatible format and structured dictionary payloads.
	 *
	 * @since 1.3.0
	 *
	 * @param \rtCamp\AIProviderUniversalOpenAI\Decisions\DecisionTypeEnum $type The type of the question asked.
	 * @param mixed                                                        $data The answer data from the response.
	 * @param string                                                       $path Where the answer sits in the response, for error messages.
	 * @return self The answer instance.
	 * @throws \WordPress\AiClient\Providers\Http\Exception\ResponseException If the data does not match the type.
	 */
	public static function fromResponseData( DecisionTypeEnum $type, $data, string $path ): self {
		if ( $type->isPredicate() || $type->isNoul() ) {
			// Handle direct boolean (e.g. true / false).
			if ( is_bool( $data ) ) {
				$prob = $data ? 1.0 : 0.0;
				return new self(
					$type,
					$prob,
					[
						'true'  => $prob,
						'false' => 1.0 - $prob,
					],
					1.0
				);
			}

			// Handle direct numeric probability.
			if ( is_numeric( $data ) ) {
				$prob = (float) $data;
				return new self(
					$type,
					$prob,
					[
						'true'  => $prob,
						'false' => 1.0 - $prob,
					],
					1.0
				);
			}

			// Handle structured object/array.
			if ( is_array( $data ) ) {
				if ( isset( $data['probability'] ) && is_numeric( $data['probability'] ) ) {
					$prob = (float) $data['probability'];
					return new self( $type, $prob, self::readProbabilities( $data ), self::readConfidence( $data ) );
				}
				if ( isset( $data['decision'] ) ) {
					$decision = $data['decision'];
					if ( is_bool( $decision ) ) {
						$prob = $decision ? 1.0 : 0.0;
						return new self( $type, $prob, self::readProbabilities( $data ), 1.0 );
					}
					if ( is_numeric( $decision ) ) {
						return new self( $type, (float) $decision, self::readProbabilities( $data ), self::readConfidence( $data ) );
					}
				}
				if ( isset( $data['noul'] ) && is_numeric( $data['noul'] ) ) {
					return new self( $type, (float) $data['noul'], self::readProbabilities( $data ), self::readConfidence( $data ) );
				}
			}

			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw \WordPress\AiClient\Providers\Http\Exception\ResponseException::fromInvalidData( 'OpenAI Compatible', $path, 'The value must be a boolean, number, or object with probability.' );
		}

		if ( $type->isChoice() ) {
			// Handle direct string choice (e.g. "billing").
			if ( is_string( $data ) ) {
				return new self( $type, $data, [ $data => 1.0 ], 1.0 );
			}

			if ( is_array( $data ) ) {
				$choice = $data['choice'] ?? $data['decision'] ?? $data['value'] ?? null;
				if ( is_string( $choice ) ) {
					$confidence    = isset( $data['confidence'] ) && is_numeric( $data['confidence'] ) ? (float) $data['confidence'] : 1.0;
					$probabilities = self::readProbabilities( $data );
					if ( empty( $probabilities ) ) {
						$probabilities = [ $choice => $confidence ];
					}
					return new self( $type, $choice, $probabilities, $confidence );
				}
			}

			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw \WordPress\AiClient\Providers\Http\Exception\ResponseException::fromInvalidData( 'OpenAI Compatible', $path, 'The value must be a string or choice object.' );
		}

		// SCORE type.
		if ( is_numeric( $data ) ) {
			return new self( $type, (float) $data );
		}

		if ( is_array( $data ) ) {
			$score_val = $data['score'] ?? $data['decision'] ?? $data['value'] ?? null;
			if ( is_numeric( $score_val ) ) {
				$confidence    = isset( $data['confidence'] ) && is_numeric( $data['confidence'] ) ? (float) $data['confidence'] : null;
				$probabilities = self::readProbabilities( $data );
				$legend        = [];
				if ( isset( $data['legend'] ) && is_array( $data['legend'] ) ) {
					foreach ( $data['legend'] as $level => $desc ) {
						if ( ! is_string( $desc ) ) {
							continue;
						}

						$legend[ (string) $level ] = $desc;
					}
				}
				return new self( $type, (float) $score_val, $probabilities, $confidence, $legend );
			}
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		throw \WordPress\AiClient\Providers\Http\Exception\ResponseException::fromInvalidData( 'OpenAI Compatible', $path, 'The value must be a number or score object.' );
	}

	/**
	 * Returns the question type.
	 *
	 * @since 1.3.0
	 *
	 * @return \rtCamp\AIProviderUniversalOpenAI\Decisions\DecisionTypeEnum The question type.
	 */
	public function getType(): DecisionTypeEnum {
		return $this->type;
	}

	/**
	 * Returns the raw answer value.
	 *
	 * @since 1.3.0
	 *
	 * @return string|float The option key for choice, probability for predicate, position for score.
	 */
	public function getValue() {
		return $this->value;
	}

	/**
	 * Returns the chosen option for a choice answer.
	 *
	 * @since 1.3.0
	 *
	 * @return string|null The option key, or null if not a choice answer.
	 */
	public function getChoice(): ?string {
		return $this->type->isChoice() ? (string) $this->value : null;
	}

	/**
	 * Returns the probability for a predicate answer.
	 *
	 * @since 1.3.0
	 *
	 * @return float|null The probability from 0.0 to 1.0, or null if not a predicate answer.
	 */
	public function getProbability(): ?float {
		return $this->type->isPredicate() || $this->type->isNoul() ? (float) $this->value : null;
	}

	/**
	 * Returns the numerical score for a score answer.
	 *
	 * @since 1.3.0
	 *
	 * @return float|null The numeric score, or null if not a score answer.
	 */
	public function getScore(): ?float {
		return $this->type->isScore() ? (float) $this->value : null;
	}

	/**
	 * Returns the probability distribution across choices or rubric levels.
	 *
	 * @since 1.3.0
	 *
	 * @return array<string, float> The probability map.
	 */
	public function getProbabilities(): array {
		return $this->probabilities;
	}

	/**
	 * Returns the confidence metric.
	 *
	 * @since 1.3.0
	 *
	 * @return float|null The confidence from 0.0 to 1.0, or null.
	 */
	public function getConfidence(): ?float {
		return $this->confidence;
	}

	/**
	 * Returns the score rubric level descriptions.
	 *
	 * @since 1.3.0
	 *
	 * @return array<string, string> The legend map.
	 */
	public function getLegend(): array {
		return $this->legend;
	}

	/**
	 * Reads probabilities map from response array.
	 *
	 * @since 1.3.0
	 *
	 * @param array<string, mixed> $data The response data array.
	 * @return array<string, float> The parsed probabilities.
	 */
	private static function readProbabilities( array $data ): array {
		$raw = $data['probabilities'] ?? null;
		if ( ! is_array( $raw ) ) {
			return [];
		}

		$probabilities = [];
		foreach ( $raw as $k => $v ) {
			if ( ! is_numeric( $v ) ) {
				continue;
			}

			$probabilities[ (string) $k ] = (float) $v;
		}

		return $probabilities;
	}

	/**
	 * Reads confidence float from response array.
	 *
	 * @since 1.3.0
	 *
	 * @param array<string, mixed> $data The response data array.
	 * @return float|null The confidence float or null.
	 */
	private static function readConfidence( array $data ): ?float {
		if ( isset( $data['confidence'] ) && is_numeric( $data['confidence'] ) ) {
			return (float) $data['confidence'];
		}

		return null;
	}
}
