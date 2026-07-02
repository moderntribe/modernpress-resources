<?php declare(strict_types=1);

use Tribe\Plugin\Components\Blocks\FacetWP_Filter_Bar_Controller;

/**
 * @var object $args
 */

$c = $args['controller'] ?? null;

if ( ! $c instanceof FacetWP_Filter_Bar_Controller ) {
	return;
}

$flyout_id       = $args['flyout_id'] ?? '';
$flyout_title_id = $args['flyout_title_id'] ?? '';

if ( '' === $flyout_id || '' === $flyout_title_id ) {
	return;
}
?>
<div class="b-facetwp-filter-bar__mobile-trigger" data-js="facetwp-filter-trigger">
	<button
		type="button"
		class="b-facetwp-filter-bar__trigger-btn"
		data-js="facetwp-filter-open"
		aria-expanded="false"
		aria-controls="<?php echo esc_attr( $flyout_id ); ?>"
		aria-haspopup="dialog"
	>
		<span class="b-facetwp-filter-bar__trigger-icon" aria-hidden="true"></span>
		<span class="b-facetwp-filter-bar__trigger-text"><?php esc_html_e( 'Search & Refine', 'tribe' ); ?></span>
	</button>
	<span class="b-facetwp-filter-bar__clear-wrap" data-js="facetwp-filter-clear-wrap" hidden>
		<button type="button" class="a-btn-link" data-js="facetwp-filter-clear-all"><?php esc_html_e( 'Clear all', 'tribe' ); ?></button>
	</span>
</div>
<div
	id="<?php echo esc_attr( $flyout_id ); ?>"
	class="b-facetwp-filter-bar__flyout"
	role="dialog"
	aria-modal="true"
	aria-labelledby="<?php echo esc_attr( $flyout_title_id ); ?>"
	aria-hidden="true"
	data-js="facetwp-filter-flyout"
>
	<div class="b-facetwp-filter-bar__flyout-inner">
		<header class="b-facetwp-filter-bar__flyout-header">
			<h2 id="<?php echo esc_attr( $flyout_title_id ); ?>" class="b-facetwp-filter-bar__flyout-title t-display-x-small"><?php esc_html_e( 'Search & Refine', 'tribe' ); ?></h2>
			<button
				type="button"
				class="b-facetwp-filter-bar__flyout-close"
				data-js="facetwp-filter-close"
				aria-label="<?php esc_attr_e( 'Close', 'tribe' ); ?>"
			>
				<span class="b-facetwp-filter-bar__flyout-close-icon" aria-hidden="true"></span>
				<span class="b-facetwp-filter-bar__flyout-close-text"><?php esc_html_e( 'Close', 'tribe' ); ?></span>
			</button>
		</header>
		<div class="b-facetwp-filter-bar__flyout-body">
			<div class="b-facetwp-filter-bar__grid">
				<?php get_template_part( 'components/filter-bar/facets', null, [
					'controller' => $c,
				] ); ?>
			</div>
		</div>
		<footer class="b-facetwp-filter-bar__flyout-footer">
			<button type="button" class="a-btn" data-js="facetwp-filter-show-results"><?php esc_html_e( 'Show results', 'tribe' ); ?></button>
		</footer>
	</div>
</div>
