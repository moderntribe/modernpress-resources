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
<div class="b-facetwp-filter-bar__grid">
	<?php get_template_part( 'components/filter-bar/facets', null, [
		'controller' => $c,
	] ); ?>
</div>
