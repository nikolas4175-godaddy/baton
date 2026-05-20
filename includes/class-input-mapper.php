<?php
/**
 * Resolves field-level mappings from workflow / previous-step data.
 *
 * @package AbilityWorkflows
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dot-path extraction and input mapping for workflow steps.
 */
final class Ability_Workflows_Input_Mapper {

	/**
	 * Get a value from nested data using a dot path (e.g. "id", "user.email", "items.0.id").
	 *
	 * @param mixed  $data Source data.
	 * @param string $path Dot-separated path.
	 * @return mixed|null Value or null when path cannot be resolved.
	 */
	public static function get_value_at_path( $data, string $path ) {
		$path = trim( $path );
		if ( '' === $path ) {
			return $data;
		}

		$segments = explode( '.', $path );
		$current  = $data;

		foreach ( $segments as $segment ) {
			if ( '' === $segment ) {
				continue;
			}

			if ( is_array( $current ) ) {
				if ( ! array_key_exists( $segment, $current ) ) {
					return null;
				}
				$current = $current[ $segment ];
				continue;
			}

			if ( is_object( $current ) ) {
				if ( ! isset( $current->$segment ) ) {
					return null;
				}
				$current = $current->$segment;
				continue;
			}

			return null;
		}

		return $current;
	}

	/**
	 * Apply field mappings onto a base input array.
	 *
	 * @param array<string, mixed> $input          Base input (typically static JSON).
	 * @param array<int, array<string, string>> $mappings Mapping definitions.
	 * @param mixed                $previous_output Previous step output.
	 * @param array<string, mixed> $initial_input   Workflow-level input.
	 * @return array{input: array<string, mixed>, warnings: array<int, string>}
	 */
	public static function apply_mappings(
		array $input,
		array $mappings,
		$previous_output,
		array $initial_input = array()
	): array {
		$warnings = array();

		foreach ( $mappings as $mapping ) {
			if ( ! is_array( $mapping ) ) {
				continue;
			}

			$target = isset( $mapping['target'] ) ? self::sanitize_field_name( (string) $mapping['target'] ) : '';
			$path   = isset( $mapping['path'] ) ? self::sanitize_path( (string) $mapping['path'] ) : '';
			$source = isset( $mapping['source'] ) ? (string) $mapping['source'] : 'previous';

			if ( '' === $target || '' === $path ) {
				continue;
			}

			$source_data = 'initial' === $source ? $initial_input : $previous_output;

			if ( null === $source_data ) {
				$warnings[] = sprintf(
					/* translators: 1: target field, 2: source label */
					__( 'Mapping for "%1$s" skipped: no %2$s data available.', 'ability-workflows' ),
					$target,
					'initial' === $source ? __( 'workflow input', 'ability-workflows' ) : __( 'previous step', 'ability-workflows' )
				);
				continue;
			}

			$value = self::get_value_at_path( $source_data, $path );

			if ( null === $value ) {
				$warnings[] = sprintf(
					/* translators: 1: dot path, 2: target field */
					__( 'Mapping for "%2$s" skipped: path "%1$s" not found in source.', 'ability-workflows' ),
					$path,
					$target
				);
				continue;
			}

			$input[ $target ] = self::coerce_value( $value );
		}

		return array(
			'input'    => $input,
			'warnings' => $warnings,
		);
	}

	/**
	 * Sanitize a mapping list from stored/POST data.
	 *
	 * @param mixed $raw Raw mappings.
	 * @return array<int, array<string, string>>
	 */
	public static function sanitize_mappings( $raw ): array {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$sanitized = array();

		foreach ( $raw as $mapping ) {
			if ( ! is_array( $mapping ) ) {
				continue;
			}

			$target = isset( $mapping['target'] ) ? self::sanitize_field_name( (string) $mapping['target'] ) : '';
			$path   = isset( $mapping['path'] ) ? self::sanitize_path( (string) $mapping['path'] ) : '';
			$source = isset( $mapping['source'] ) ? sanitize_text_field( (string) $mapping['source'] ) : 'previous';

			if ( '' === $target || '' === $path ) {
				continue;
			}

			if ( ! in_array( $source, array( 'previous', 'initial' ), true ) ) {
				$source = 'previous';
			}

			$sanitized[] = array(
				'source' => $source,
				'path'   => $path,
				'target' => $target,
			);
		}

		return $sanitized;
	}

	/**
	 * Sanitize an input field / target name.
	 *
	 * @param string $name Raw field name.
	 * @return string
	 */
	public static function sanitize_field_name( string $name ): string {
		$name = trim( $name );
		return preg_replace( '/[^a-zA-Z0-9_-]/', '', $name ) ?? '';
	}

	/**
	 * Sanitize a dot path segment chain.
	 *
	 * @param string $path Raw path.
	 * @return string
	 */
	public static function sanitize_path( string $path ): string {
		$path = trim( $path );
		if ( '' === $path ) {
			return '';
		}

		$segments = explode( '.', $path );
		$clean    = array();

		foreach ( $segments as $segment ) {
			$segment = preg_replace( '/[^a-zA-Z0-9_-]/', '', $segment );
			if ( '' !== $segment ) {
				$clean[] = $segment;
			}
		}

		return implode( '.', $clean );
	}

	/**
	 * Coerce mapped values to sensible scalar types (e.g. numeric strings to int).
	 *
	 * @param mixed $value Raw mapped value.
	 * @return mixed
	 */
	public static function coerce_value( $value ) {
		if ( is_string( $value ) && is_numeric( $value ) ) {
			return str_contains( $value, '.' ) ? (float) $value : (int) $value;
		}

		return $value;
	}

	/**
	 * List top-level property keys from a JSON schema object.
	 *
	 * @param array<string, mixed> $schema JSON Schema fragment.
	 * @return array<int, string>
	 */
	public static function schema_property_keys( array $schema ): array {
		if ( ! isset( $schema['properties'] ) || ! is_array( $schema['properties'] ) ) {
			return array();
		}

		return array_keys( $schema['properties'] );
	}
}
