<?php
/**
 * A question for an OpenAI-compatible decision model.
 *
 * @package rtCamp\AIProviderUniversalOpenAI\Decisions
 * @since   1.3.0
 */

declare( strict_types=1 );

namespace rtCamp\AIProviderUniversalOpenAI\Decisions;

// phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- camelCase, matching the AI Client DTOs.


/**
 * Class for a single typed question asked of a decision model.
 *
 * @since 1.3.0
 */
class DecisionQuestion {

	/**
	 * The minimum number of options or levels a choice or score question takes.
	 *
	 * @since 1.3.0
	 *
	 * @var int
	 */
	public const MIN_CRITERIA = 2;

	/**
	 * The maximum number of options or levels a choice or score question takes.
	 *
	 * @since 1.3.0
	 *
	 * @var int
	 */
	public const MAX_CRITERIA = 26;

	/**
	 * The question type.
	 *
	 * @since 1.3.0
	 *
	 * @var \rtCamp\AIProviderUniversalOpenAI\Decisions\DecisionTypeEnum
	 */
	private DecisionTypeEnum $type;

	/**
	 * The question instructions.
	 *
	 * @since 1.3.0
	 *
	 * @var string
	 */
	private string $instructions;

	/**
	 * The criteria in the shape the API expects, or null when none are sent.
	 *
	 * @since 1.3.0
	 *
	 * @var array<string, string>|list<string>|null
	 */
	private ?array $criteria;

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 *
	 * @param \rtCamp\AIProviderUniversalOpenAI\Decisions\DecisionTypeEnum $type         The question type.
	 * @param string                                                       $instructions The question instructions.
	 * @param array<string, string>|list<string>|null                      $criteria     The criteria, if any.
	 * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If instructions are empty.
	 */
	private function __construct( DecisionTypeEnum $type, string $instructions, ?array $criteria ) {
		if ( '' === trim( $instructions ) ) {
			throw new \WordPress\AiClient\Common\Exception\InvalidArgumentException( 'Decision question instructions must not be empty.' );
		}

		$this->type         = $type;
		$this->instructions = $instructions;
		$this->criteria     = $criteria;
	}

	/**
	 * Creates a question that evaluates whether a statement is true or false.
	 *
	 * @since 1.3.0
	 *
	 * @param string      $instructions      The question instructions.
	 * @param string|null $false_description Optional. What a false outcome means. Default null.
	 * @param string|null $true_description  Optional. What a true outcome means. Default null.
	 * @return self The question.
	 * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If a description is empty.
	 */
	public static function predicate( string $instructions, ?string $false_description = null, ?string $true_description = null ): self {
		$criteria = [];
		if ( null !== $false_description ) {
			$criteria['false'] = self::readDescription( $false_description, 'The false description' );
		}
		if ( null !== $true_description ) {
			$criteria['true'] = self::readDescription( $true_description, 'The true description' );
		}

		return new self( DecisionTypeEnum::predicate(), $instructions, empty( $criteria ) ? null : $criteria );
	}

	/**
	 * Creates a question that estimates the probability that a statement is true (alias for predicate).
	 *
	 * @since 1.3.0
	 *
	 * @param string      $instructions      The question instructions.
	 * @param string|null $false_description Optional. What a false outcome means. Default null.
	 * @param string|null $true_description  Optional. What a true outcome means. Default null.
	 * @return self The question.
	 * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If a description is empty.
	 */
	public static function noul( string $instructions, ?string $false_description = null, ?string $true_description = null ): self {
		return self::predicate( $instructions, $false_description, $true_description );
	}

	/**
	 * Creates a question that picks one option from a named set.
	 *
	 * @since 1.3.0
	 *
	 * @param string|array<string, string> $instructions The question instructions, or options map when called with one argument.
	 * @param array<string, string>|null   $options      Optional. Map of option key to its description, 2 to 26 entries.
	 * @return self The question.
	 * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If the options are invalid.
	 */
	public static function choice( $instructions, ?array $options = null ): self {
		if ( is_array( $instructions ) && null === $options ) {
			$options      = $instructions;
			$instructions = 'Choose the option that best matches.';
		}

		if ( ! is_string( $instructions ) || ! is_array( $options ) ) {
			throw new \WordPress\AiClient\Common\Exception\InvalidArgumentException( 'Choice question requires valid instructions and options.' );
		}

		self::assertCriteriaCount( 'choice', count( $options ) );

		$criteria = [];
		foreach ( $options as $key => $description ) {
			$key = (string) $key;
			if ( '' === $key ) {
				throw new \WordPress\AiClient\Common\Exception\InvalidArgumentException( 'Choice option keys must not be empty.' );
			}

			$criteria[ $key ] = self::readDescription( $description, 'Choice option "' . $key . '"' );
		}

		return new self( DecisionTypeEnum::choice(), $instructions, $criteria );
	}

	/**
	 * Creates a question that rates against ordered rubric levels.
	 *
	 * @since 1.3.0
	 *
	 * @param string|list<string> $instructions The question instructions, or levels array when called with one argument.
	 * @param list<string>|null   $levels       Optional. Level descriptions from lowest to highest, 2 to 26 entries.
	 * @return self The question.
	 * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If the levels are invalid.
	 */
	public static function score( $instructions, ?array $levels = null ): self {
		if ( is_array( $instructions ) && null === $levels ) {
			$levels       = $instructions;
			$instructions = 'Rate the state against the ordered levels.';
		}

		if ( ! is_string( $instructions ) || ! is_array( $levels ) ) {
			throw new \WordPress\AiClient\Common\Exception\InvalidArgumentException( 'Score question requires valid instructions and levels.' );
		}

		self::assertCriteriaCount( 'score', count( $levels ) );

		$criteria = [];
		foreach ( array_values( $levels ) as $index => $description ) {
			$criteria[] = self::readDescription( $description, 'Score level ' . $index );
		}

		return new self( DecisionTypeEnum::score(), $instructions, $criteria );
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
	 * Returns the question instructions.
	 *
	 * @since 1.3.0
	 *
	 * @return string The question instructions.
	 */
	public function getInstructions(): string {
		return $this->instructions;
	}

	/**
	 * Returns the criteria in the shape the API expects.
	 *
	 * @since 1.3.0
	 *
	 * @return array<string, string>|list<string>|null The option map for choice questions,
	 *                                                 level list for score questions, or null.
	 */
	public function getCriteria(): ?array {
		return $this->criteria;
	}

	/**
	 * Returns the question in the shape the OpenAI-compatible /v1/decisions endpoint expects.
	 *
	 * @since 1.3.0
	 *
	 * @param string $name The question name/identifier.
	 * @return array<string, mixed> The question data.
	 */
	public function toOpenAIDecisionArray( string $name ): array {
		if ( $this->type->isPredicate() || $this->type->isNoul() ) {
			return [
				'type'         => 'predicate',
				'name'         => $name,
				'instructions' => $this->instructions,
			];
		}

		if ( $this->type->isChoice() ) {
			return [
				'type'         => 'choice',
				'name'         => $name,
				'instructions' => $this->instructions,
				'options'      => is_array( $this->criteria ) ? array_keys( $this->criteria ) : [],
			];
		}

		return [
			'type'         => 'score',
			'name'         => $name,
			'instructions' => $this->instructions,
			'options'      => is_array( $this->criteria ) ? array_values( $this->criteria ) : [],
		];
	}

	/**
	 * Returns the question as an array formatted for the OpenAI Decisions specification.
	 *
	 * @since 1.3.0
	 *
	 * @return array<string, mixed> The question data.
	 */
	public function toArray(): array {
		return $this->toOpenAIDecisionArray( '' );
	}

	/**
	 * Checks that a choice or score question has an accepted number of criteria.
	 *
	 * @since 1.3.0
	 *
	 * @param string $type  The question type, for the error message.
	 * @param int    $count The number of criteria given.
	 * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If the count is out of range.
	 */
	private static function assertCriteriaCount( string $type, int $count ): void {
		if ( $count >= self::MIN_CRITERIA && $count <= self::MAX_CRITERIA ) {
			return;
		}

		// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		throw new \WordPress\AiClient\Common\Exception\InvalidArgumentException(
			sprintf(
				'A %1$s question needs between %2$d and %3$d criteria, %4$d given.',
				$type,
				self::MIN_CRITERIA,
				self::MAX_CRITERIA,
				$count
			)
		);
		// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
	}

	/**
	 * Reads a criterion description, rejecting anything but a non-empty string.
	 *
	 * @since 1.3.0
	 *
	 * @param mixed  $description The description.
	 * @param string $label       What the description belongs to, for the error message.
	 * @return string The description.
	 * @throws \WordPress\AiClient\Common\Exception\InvalidArgumentException If the description is invalid.
	 */
	private static function readDescription( $description, string $label ): string {
		if ( ! is_string( $description ) || '' === trim( $description ) ) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new \WordPress\AiClient\Common\Exception\InvalidArgumentException( $label . ' must have a non-empty description.' );
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		return $description;
	}
}
