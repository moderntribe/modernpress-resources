<?php declare(strict_types=1);

namespace Tribe\Plugin\Components\Blocks;

use Tribe\Plugin\Components\Abstracts\Abstract_Block_Controller;

class FacetWP_Filter_Bar_Controller extends Abstract_Block_Controller {

	/**
	 * Facet types that are not wrapped in accordions when filter bar position is sidebar.
	 *
	 * @var array<string>
	 */
	private const ACCORDION_EXCLUDED_TYPES = [ 'search', 'reset' ];

	/**
	 * Facet types that should not have a label displayed.
	 *
	 * @var array<string>
	 */
	private const NO_LABEL_TYPES = [ 'reset' ];

	/**
	 * Block context key for filter bar position from the parent archive block.
	 */
	private const string FILTER_BAR_POSITION_CONTEXT = 'tribe/facetwp-archive/filterBarPosition';

	/**
	 * @var array <mixed>
	 */
	protected array $facets;
	protected string $filter_bar_position;

	public function __construct( array $args = [] ) {
		parent::__construct( $args );

		$this->facets              = $this->attributes['facets'] ?? [];
		$this->filter_bar_position = $this->context[ self::FILTER_BAR_POSITION_CONTEXT ] ?? 'top';
	}

	/**
	 * Get the facets array with display labels.
	 */
	public function get_facets(): array {
		return array_map( static function ( array $facet ): array {
			$facet['display_label'] = $facet['displayLabel'] ?? $facet['label'];

			return $facet;
		}, $this->facets );
	}

	/**
	 * Get the filter bar position.
	 */
	public function get_filter_bar_position(): string {
		return $this->filter_bar_position;
	}

	/**
	 * Whether this facet should have a label displayed.
	 *
	 * @param array $facet
	 */
	public function should_hide_facet_label( array $facet ): bool {
		$type = strtolower( $facet['type'] ?? '' );

		return in_array( $type, self::NO_LABEL_TYPES, true );
	}

	/**
	 * Whether this facet should be wrapped in a details/summary accordion (sidebar, excluding ACCORDION_EXCLUDED_TYPES).
	 *
	 * @param array $facet
	 */
	public function should_wrap_facet_in_accordion( array $facet ): bool {
		if ( $this->get_filter_bar_position() !== 'sidebar' ) {
			return false;
		}

		$type = strtolower( $facet['type'] ?? '' );

		return ! in_array( $type, self::ACCORDION_EXCLUDED_TYPES, true );
	}

	/**
	 * Grid slot for top filter bar layout: facet-1, facet-2, facet-3, search, or reset.
	 * Based on facet type and order in the facets list (not DOM order).
	 *
	 * @param array $current_facet
	 */
	public function get_grid_slot( array $current_facet ): ?string {
		$current_facet_type = strtolower( $current_facet['type'] ?? '' );

		if ( $current_facet_type === 'search' ) {
			return 'search';
		}

		if ( $current_facet_type === 'reset' ) {
			return 'reset';
		}

		$content_index = 0;
		foreach ( $this->get_facets() as $facet ) {
			$facet_type = strtolower( $facet['type'] ?? '' );

			/**
			 * The "top" layout doesn't wrap these facet types in an accordion, but
			 * we can use the same types to determine if the current facet should
			 * receive a grid slot.
			 */
			if ( in_array( $facet_type, self::ACCORDION_EXCLUDED_TYPES, true ) ) {
				continue;
			}

			if ( ( $current_facet['slug'] ?? '' ) === ( $facet['slug'] ?? '' ) ) {
				/**
				 * We are limiting the grid to 3 facets (that aren't the "search"
				 * or "reset" facet types) for the "top" layout.
				 */
				if ( $content_index >= 3 ) {
					return null;
				}

				return 'facet-' . ( $content_index + 1 );
			}
			$content_index++;
		}

		return null;
	}

	/**
	 * HTML attributes string for a facet wrapper (class and optional data-grid-slot).
	 *
	 * @param array $facet
	 */
	public function get_facet_wrapper_attributes( array $facet ): string {
		$classes = [ 'b-facetwp-filter-bar__facet' ];

		if ( $this->should_wrap_facet_in_accordion( $facet ) ) {
			$classes[] = 'b-facetwp-filter-bar__facet--accordion';
		}

		$attrs = [ 'class' => implode( ' ', $classes ) ];

		$grid_slot = $this->get_grid_slot( $facet );

		if ( $grid_slot !== null ) {
			$attrs['data-grid-slot'] = $grid_slot;
		}

		return implode( ' ', array_map(
			static fn ( string $key, string $value ): string => $key . '="' . esc_attr( $value ) . '"',
			array_keys( $attrs ),
			$attrs
		) );
	}

}
