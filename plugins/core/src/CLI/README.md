# Core CLI

Status: Alpha
Version: 0.1-alpha
Last Updated: 2026-09
Component Created By: Mykhailo Los

## What does this component do?

This is a WP-CLI command template for the Tribe Core plugin. It provides an abstract `Command` base class, a `CLI_Subscriber` for registration, and an example command you can copy when adding new `wp tribe …` commands.

## Example Usage

### Moose Implementation

1. Copy the `CLI/` directory to `plugins/core/src/CLI/` in your project (or copy individual files into an existing `CLI` directory).
1. Register `CLI_Subscriber` in `plugins/core/src/Core.php` with the other subscribers.
1. Ensure new command classes are available to the PHP-DI container (auto-wiring is enough when they have no constructor dependencies).
1. Implement your command by extending `Tribe\Plugin\CLI\Command` (see `Command_Example.php`), then register it from `CLI_Subscriber` via `$this->container->get( Your_Command::class )->register();`.

### Creating a command

Extend `Command` and implement:

- `command()` — the subcommand path after `tribe` (e.g. `example command` → `wp tribe example command`)
- `description()` — short description shown in WP-CLI help
- `arguments()` — WP-CLI synopsis array for positional/assoc args
- `run( array $args, array $assoc_args )` — command logic

### Example commands

```bash
wp tribe example command
wp tribe example command --post_id=1
wp tribe example command --exclude_ids=1,2,3,7
```
