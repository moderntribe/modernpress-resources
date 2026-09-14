<?php declare(strict_types=1);

namespace Tribe\Plugin\CLI;

use Tribe\Plugin\Core\Abstract_Subscriber;

/**
 * Add CLI_Subscriber to the Core.php
 */
class CLI_Subscriber extends Abstract_Subscriber {

    public function register(): void {
        add_action( 'init', function (): void {
            $this->container->get( Command_Example::class )->register();
        }, 10, 0 );
    }

}
