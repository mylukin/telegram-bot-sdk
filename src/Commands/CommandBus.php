<?php

namespace Telegram\Bot\Commands;

use Telegram\Bot\Answers\AnswerBus;
use Telegram\Bot\Api;
use Telegram\Bot\Objects\Update;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\Traits\Singleton;

/**
 * Class CommandBus.
 */
class CommandBus extends AnswerBus
{
    use Singleton;

    /**
     * @var Command[] Holds all commands.
     */
    protected $commands = [];

    /**
     * @var Command[] Holds all commands' aliases.
     */
    protected $commandAliases = [];

    /**
     * Instantiate Command Bus.
     *
     * @param Api|null $telegram
     *
     * @throws TelegramSDKException
     */
    public function __construct(Api $telegram = null)
    {
        $this->telegram = $telegram;
    }

    /**
     * Returns the list of commands.
     *
     * @return array
     */
    public function getCommands(): array
    {
        return $this->commands;
    }

    /**
     * Add a list of commands.
     *
     * @param array $commands
     *
     * @return CommandBus
     */
    public function addCommands(array $commands): CommandBus
    {
        foreach ($commands as $command) {
            $this->addCommand($command);
        }

        return $this;
    }

    /**
     * Add a command to the commands list.
     *
     * @param CommandInterface|string $command Either an object or full path to the command class.
     *
     * @throws TelegramSDKException
     *
     * @return CommandBus
     */
    public function addCommand($command): CommandBus
    {
        $command = $this->resolveCommandObject($command);

        /*
         * At this stage we definitely have a proper command to use.
         *
         * @var Command $command
         */
        $this->commands[$command->getName()] = $command;

        $aliases = $command->getAliases();

        if (empty($aliases)) {
            return $this;
        }

        foreach ($command->getAliases() as $alias) {
            if (isset($this->commands[$alias])) {
                throw new TelegramSDKException(sprintf(
                    '[Error] Alias [%s] conflicts with command name of "%s" try with another name or remove this alias from the list.',
                    $alias,
                    get_class($command)
                ));
            }

            if (isset($this->commandAliases[$alias])) {
                throw new TelegramSDKException(sprintf(
                    '[Error] Alias [%s] conflicts with another command\'s alias list: "%s", try with another name or remove this alias from the list.',
                    $alias,
                    get_class($command)
                ));
            }

            $this->commandAliases[$alias] = $command;
        }

        return $this;


    }

    /**
     * Remove a command from the list.
     *
     * @param $name
     *
     * @return CommandBus
     */
    public function removeCommand($name): CommandBus
    {
        unset($this->commands[$name]);

        return $this;
    }

    /**
     * Removes a list of commands.
     *
     * @param array $names
     *
     * @return CommandBus
     */
    public function removeCommands(array $names): CommandBus
    {
        foreach ($names as $name) {
            $this->removeCommand($name);
        }

        return $this;
    }

    /**
     * Parse a Command for a Match.
     *
     * @param $text
     *
     * @throws \InvalidArgumentException
     *
     * @return array
     */
    public function parseCommand($text): array
    {
        if (trim($text) === '') {
            throw new \InvalidArgumentException('Message is empty, Cannot parse for command');
        }

        preg_match('/^\/([^\s@]+)@?(\S+)?\s?(.*)$/s', $text, $matches);

        return $matches;
    }

    /**
     * Handles Inbound Messages and Executes Appropriate Command.
     *
     * @param $message
     * @param $update
     *
     * @throws TelegramSDKException
     *
     * @return Update
     */
    protected function handler($message, Update $update): Update
    {
        if ($message === null) {
            return $update;
        }

        $match = $this->parseCommand($message);
        if (!empty($match)) {
            $command = strtolower($match[1]); // All commands must be lowercase.
            $arguments = $match[3];

            $this->execute($command, $arguments, $update);
        }

        return $update;
    }

    /**
     * Execute the command.
     *
     * @param $name
     * @param $arguments
     * @param $message
     *
     * @return mixed
     */
    protected function execute($name, $arguments, $message)
    {
        if (array_key_exists($name, $this->commands)) {
            return $this->commands[$name]->make($this->telegram, $arguments, $message);
        } elseif (array_key_exists($name, $this->commandAliases)) {
            return $this->commandAliases[$name]->make($this->telegram, $arguments, $message);
        } elseif (array_key_exists('help', $this->commands)) {
            return $this->commands['help']->make($this->telegram, $arguments, $message);
        }

        return 'Ok';
    }

    /**
     * @param $command
     * @return object
     * @throws \Telegram\Bot\Exceptions\TelegramSDKException
     */
    private function resolveCommandObject($command)
    {
        if (! is_object($command)) {
            if (! class_exists($command)) {
                throw new TelegramSDKException(sprintf('Command class "%s" not found! Please make sure the class exists.', $command));
            }

            if ($this->telegram->hasContainer()) {
                $command = $this->buildDependencyInjectedAnswer($command);
            } else {
                $command = new $command();
            }
        }

        if (!($command instanceof CommandInterface)) {

            throw new TelegramSDKException(
                sprintf(
                    'Command class "%s" should be an instance of "Telegram\Bot\Commands\CommandInterface"',
                    get_class($command)
                )
            );
        }

        return $command;
    }
}
