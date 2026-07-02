<?php declare(strict_types=1);

use Tribe\Plugin\Components\Blocks\FacetWP_Filter_Bar_Controller;

/**
 * @var object $args
 */

$c = $args['controller'] ?? null;

if ( ! $c instanceof FacetWP_Filter_Bar_Controller ) {
	return;
}
?>
<?php foreach ( $c->get_facets() as $facet ) : ?>
	<?php if ( $c->should_wrap_facet_in_accordion( $facet ) ) : ?>
		<details <?php echo $c->get_facet_wrapper_attributes( $facet ); ?>>
			<summary class="b-facetwp-filter-bar__facet-summary"><?php echo esc_html( $facet['display_label'] ); ?></summary>
			<div class="b-facetwp-filter-bar__facet-content">
				<?php if ( function_exists( 'facetwp_display' ) ) : ?>
					<?php echo facetwp_display( 'facet', $facet['slug'] ); ?>
				<?php endif; ?>
			</div>
		</details>
	<?php else : ?>
		<div <?php echo $c->get_facet_wrapper_attributes( $facet ); ?>>
			<?php if ( ! $c->should_hide_facet_label( $facet ) ) : ?>
				<label for="facet-<?php echo esc_attr( $facet['slug'] ); ?>" class="b-facetwp-filter-bar__facet-label"><?php echo esc_html( $facet['display_label'] ); ?></label>
			<?php endif; ?>
			<?php if ( function_exists( 'facetwp_display' ) ) : ?>
				<?php echo facetwp_display( 'facet', $facet['slug'] ); ?>
			<?php endif; ?>
		</div>
	<?php endif; ?>
<?php endforeach; ?>
