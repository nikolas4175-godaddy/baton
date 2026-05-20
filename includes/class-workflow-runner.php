<?php
/**
 * Workflow execution engine.
 *
 * @package AbilityWorkflows
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs workflow steps sequentially via the Abilities API.
 */
final class Ability_Workflows_Runner {

	/**
	 * Run a workflow definition.
	 *
	 * @param array<string, mixed> $definition Workflow definition.
	 * @param int                  $workflow_id Optional workflow post ID for hooks.
	 * @return array<string, mixed>
	 */
	public static function run( array $definition, int $workflow_id = 0 ): array {
		$definition = wp_parse_args( $definition, Ability_Workflows_CPT::default_definition() );

		$steps         = $definition['steps'] ?? array();
		$initial_input = is_array( $definition['initial_input'] ?? null ) ? $definition['initial_input'] : array();

		$report = array(
			'success' => true,
			'steps'   => array(),
		);

		if ( empty( $steps ) ) {
			$report['success'] = false;
			$report['error']   = __( 'Workflow has no steps.', 'ability-workflows' );
			return $report;
		}

		$previous_output = null;

		foreach ( $steps as $index => $step ) {
			if ( ! is_array( $step ) ) {
				continue;
			}

			$ability_slug = isset( $step['ability'] ) ? sanitize_text_field( (string) $step['ability'] ) : '';
			$static_input   = isset( $step['input'] ) && is_array( $step['input'] ) ? $step['input'] : array();
			$use_previous   = ! empty( $step['use_previous_output'] );
			$mappings       = Ability_Workflows_Input_Mapper::sanitize_mappings( $step['input_mappings'] ?? array() );

			$step_report = array(
				'index'   => (int) $index,
				'ability' => $ability_slug,
				'success' => false,
			);

			if ( '' === $ability_slug ) {
				$step_report['error'] = __( 'Step is missing an ability.', 'ability-workflows' );
				$report['steps'][]    = $step_report;
				$report['success']    = false;
				$report['error']      = $step_report['error'];
				break;
			}

			$ability = wp_get_ability( $ability_slug );
			if ( ! $ability ) {
				$step_report['error'] = sprintf(
					/* translators: %s: ability slug */
					__( 'Ability "%s" not found.', 'ability-workflows' ),
					$ability_slug
				);
				$report['steps'][] = $step_report;
				$report['success'] = false;
				$report['error']   = $step_report['error'];
				break;
			}

			$resolved = self::resolve_step_input(
				$static_input,
				$use_previous,
				$previous_output,
				0 === (int) $index ? $initial_input : array(),
				$mappings
			);

			$step_report['input']    = $resolved['input'];
			$step_report['warnings'] = $resolved['warnings'];

			/**
			 * Fires before a workflow step executes.
			 *
			 * @param int                  $workflow_id Workflow post ID.
			 * @param int                  $step_index  Step index.
			 * @param mixed                $input       Resolved input.
			 * @param array<string, mixed> $step        Step definition.
			 */
			do_action( 'ability_workflows_before_step', $workflow_id, (int) $index, $resolved['input'], $step );

			$result = self::execute_ability( $ability, $resolved['input'] );

			if ( is_wp_error( $result ) ) {
				$step_report['error'] = $result->get_error_message();
				$report['steps'][]    = $step_report;
				$report['success']    = false;
				$report['error']      = $step_report['error'];
				break;
			}

			$step_report['success'] = true;
			$step_report['output']  = $result;
			$report['steps'][]      = $step_report;
			$previous_output        = $result;

			/**
			 * Fires after a workflow step executes successfully.
			 *
			 * @param int                  $workflow_id Workflow post ID.
			 * @param int                  $step_index  Step index.
			 * @param mixed                $input       Input passed to the ability.
			 * @param mixed                $output      Ability output.
			 * @param array<string, mixed> $step        Step definition.
			 */
			do_action( 'ability_workflows_after_step', $workflow_id, (int) $index, $resolved['input'], $result, $step );
		}

		return $report;
	}

	/**
	 * Resolve input for a step.
	 *
	 * @param array<string, mixed> $static_input     Static step input.
	 * @param bool                 $use_previous     Whether to merge previous output.
	 * @param mixed                $previous_output  Previous step output.
	 * @param array<string, mixed>              $initial_input    Workflow initial input (first step only).
	 * @param array<int, array<string, string>> $mappings         Field-level input mappings.
	 * @return array{input: mixed, warnings: array<int, string>}
	 */
	public static function resolve_step_input(
		array $static_input,
		bool $use_previous,
		$previous_output,
		array $initial_input = array(),
		array $mappings = array()
	): array {
		$warnings     = array();
		$has_mappings = ! empty( $mappings );
		$input        = array();

		if ( $has_mappings ) {
			$mapped = Ability_Workflows_Input_Mapper::apply_mappings(
				array(),
				$mappings,
				$previous_output,
				$initial_input
			);

			$input    = $mapped['input'];
			$warnings = array_merge( $warnings, $mapped['warnings'] );

			if ( is_array( $static_input ) && ! empty( $static_input ) ) {
				$input = array_merge( $input, $static_input );
			}
		} else {
			$input = $static_input;
		}

		if ( ! $has_mappings && $use_previous && null !== $previous_output ) {
			if ( is_array( $previous_output ) ) {
				$input = array_merge( $previous_output, $static_input );
			} elseif ( empty( $static_input ) ) {
				$input = $previous_output;
			} else {
				$warnings[] = __(
					'Previous step output is not an array; static input was used instead.',
					'ability-workflows'
				);
			}
		}

		if ( ! $has_mappings && ! empty( $initial_input ) && is_array( $input ) ) {
			$input = array_merge( $initial_input, $input );
		} elseif ( ! $has_mappings && ! empty( $initial_input ) && empty( $input ) ) {
			$input = $initial_input;
		}

		return array(
			'input'    => $input,
			'warnings' => $warnings,
		);
	}

	/**
	 * Execute an ability with appropriate input.
	 *
	 * @param WP_Ability $ability Ability instance.
	 * @param mixed      $input   Resolved input.
	 * @return mixed|WP_Error
	 */
	private static function execute_ability( WP_Ability $ability, $input ) {
		$input_schema = $ability->get_input_schema();

		if ( empty( $input_schema ) ) {
			return $ability->execute();
		}

		if ( is_array( $input ) && array() === $input ) {
			return $ability->execute( null );
		}

		return $ability->execute( $input );
	}
}
