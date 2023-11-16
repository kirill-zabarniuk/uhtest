<?php

namespace Underhood\Application;

use Symfony\Component\Console;
use Underhood\Command\Parse;

/**
 * SingleCommandApplication
 */
class SingleCommandApplication extends Console\Application
{
    const DEFAULT_COMMAND_NAME = 'parse';

    /**
     * Gets the name of the command based on input.
     *
     * @param Console\Input\InputInterface $input The input interface
     *
     * @return string The command name
     */
    protected function getCommandName(Console\Input\InputInterface $input): string
    {
        // This should return the name of your command.
        return self::DEFAULT_COMMAND_NAME;
    }

    /**
     * Gets the default commands that should always be available.
     *
     * @return array An array of default Command instances
     */
    protected function getDefaultCommands(): array
    {
        // Keep the core default commands to have the HelpCommand
        // which is used when using the --help option
        $defaultCommands = parent::getDefaultCommands();

        $defaultCommands[] = new Parse(self::DEFAULT_COMMAND_NAME);

        return $defaultCommands;
    }

    /**
     * Overridden so that the application doesn't expect the command name to be the first argument.
     *
     * @return Console\Input\InputDefinition
     */
    public function getDefinition(): Console\Input\InputDefinition
    {
        $inputDefinition = parent::getDefinition();
        // clear out the normal first argument, which is the command name
        $inputDefinition->setArguments();

        return $inputDefinition;
    }
}
