<?php declare(strict_types=1);

namespace Tribe\Plugin\CLI;

abstract class Command extends \WP_CLI_Command {

    abstract public function run( array $args, array $assoc_args ): void;

    abstract protected function command(): string;

    abstract protected function description(): string;

    abstract protected function arguments(): array;

    public function register(): void {
        if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
            return;
        }

        \WP_CLI::add_command( 'tribe ' . $this->command(), [ $this, 'run' ], [
            'shortdesc' => $this->description(),
            'synopsis'  => $this->arguments(),
        ] );
    }

}
