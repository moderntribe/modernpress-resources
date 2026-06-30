<?php declare(strict_types=1);

/**
 * Merge the changes below into `wp-content/plugins/core/src/Components/Abstracts/Abstract_Block_Controller.php`.
 *
 * Required for `tribe/facetwp-filter-bar`, which reads block context from the parent archive block.
 */

// Add this property alongside the existing `$attributes` property:
/**
 * @var array <mixed>
 */
protected array $context;

// Add this assignment in `__construct()` alongside the existing `$attributes` assignment:
$this->context = $args['context'] ?? [];
