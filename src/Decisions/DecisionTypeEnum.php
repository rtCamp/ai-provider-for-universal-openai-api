<?php
/**
 * Enum for decision question types.
 *
 * @package rtCamp\AIProviderUniversalOpenAI\Decisions
 * @since   1.2.0
 */

declare( strict_types=1 );

namespace rtCamp\AIProviderUniversalOpenAI\Decisions;

use WordPress\AiClient\Common\AbstractEnum;

/**
 * Enum for the question types an OpenAI-compatible decision model answers.
 *
 * @since 1.2.0
 *
 * @method static self predicate() Creates an instance for the PREDICATE type.
 * @method static self choice() Creates an instance for the CHOICE type.
 * @method static self score() Creates an instance for the SCORE type.
 * @method static self noul() Creates an instance for the NOUL type (alias for predicate).
 * @method bool isPredicate() Checks if the type is PREDICATE.
 * @method bool isChoice() Checks if the type is CHOICE.
 * @method bool isScore() Checks if the type is SCORE.
 * @method bool isNoul() Checks if the type is NOUL.
 */
class DecisionTypeEnum extends AbstractEnum {

	/**
	 * Evaluates whether a condition is true or false.
	 */
	public const PREDICATE = 'predicate';

	/**
	 * Picks one option from a named set.
	 */
	public const CHOICE = 'choice';

	/**
	 * Rates against ordered levels or rubric.
	 */
	public const SCORE = 'score';

	/**
	 * Alias for predicate, evaluating probability that a statement is true.
	 */
	public const NOUL = 'noul';
}
