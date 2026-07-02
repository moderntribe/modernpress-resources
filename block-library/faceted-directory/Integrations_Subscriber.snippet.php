<?php declare(strict_types=1);

/**
 * Merge the hooks below into `wp-content/plugins/core/src/Integrations/Integrations_Subscriber.php`
 * inside the `register()` method, before the closing brace of that method.
 *
 * The `FacetWP` class is in the same namespace and does not require an additional `use` statement.
 */

add_filter( 'block_editor_settings_all', function ( $settings ): array {
	$settings['facetwpFacets'] = $this->container->get( FacetWP::class )->get_facets();

	return $settings;
}, 10, 1 );

// Add id to the first <select> or <input> so label[for] targets the focusable control.
add_filter( 'facetwp_facet_html', function ( $output, $params ): string {
	return $this->container->get( FacetWP::class )->add_facet_control_id( $output, $params );
}, 10, 2 );

// Remove counts from certain facet types.
add_filter( 'facetwp_facet_html', function ( $output, $params ): string {
	return $this->container->get( FacetWP::class )->remove_facetwp_counts( $output, $params );
}, 10, 2 );

// Hide the counts in the fSelect facet dropdown.
add_filter( 'facetwp_facet_dropdown_show_counts', '__return_false' );

// Register the pagination facet.
add_filter( 'facetwp_facets', function ( $facets ): array {
	return $this->container->get( FacetWP::class )->register_custom_facets( $facets );
}, 10, 1 );

// Rewrite the pagination link tags.
add_filter( 'facetwp_facet_pager_link', function ( $html, $params ): string {
	return $this->container->get( FacetWP::class )->rewrite_pagination_link_tags( $html, $params );
}, 10, 2 );
