<?php

namespace Tribe\Plugin\CLI;

use Tribe\Plugin\CLI\Command;

/**
 * This is example CLI command
 */
class Command_Example extends Command {

    public function run(array $args, array $assoc_args): void {
        /**
         * Command run example:
         * wp tribe example command
         * wp tribe example command --post_id=1
         * wp tribe example command --exclude_ids=1,2,3,7
         */
    }

    protected function command(): string {
        return 'example command';
    }

    protected function description(): string {
        return esc_html__( 'Example description', 'tribe' );
    }

    protected function arguments(): array {
        return [
            [
                'type'        => 'assoc',
                'name'        => 'post_id',
                'optional'    => true,
                'description' => esc_html__( 'Single post id', 'tribe' ),
            ],
            [
                'type'        => 'assoc',
                'name'        => 'exclude_ids',
                'optional'    => true,
                'description' => esc_html__( 'Exclude post ids. Separate ids by comma', 'tribe' ),
            ],
        ];
    }

}
