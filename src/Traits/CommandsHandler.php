<?php

namespace Telegram\Bot\Traits;

use Telegram\Bot\Commands\CommandBus;
use Telegram\Bot\Objects\Update;

/**
 * CommandsHandler
 */
trait CommandsHandler
{
    /**
     * Return Command Bus.
     *
     * @return $this
     */
    protected function getCommandBus()
    {
        return CommandBus::Instance()->setTelegram($this);
    }

    /**
     * Get all registered commands.
     *
     * @return array
     */
    public function getCommands(): array
    {
        return $this->getCommandBus()->getCommands();
    }

    /**
     * Processes Inbound Commands.
     *
     * @param bool $webhook
     *
     * @return Update|Update[]
     */
    public function commandsHandler($webhook = false)
    {
        if ($webhook) {
            $update = $this->getWebhookUpdate();
            $this->processCommand($update);

            return $update;
        }

        $updates = $this->getUpdates();
        $highestId = -1;

        foreach ($updates as $update) {
            $highestId = $update->updateId;
            $this->processCommand($update);
        }

        //An update is considered confirmed as soon as getUpdates is called with an offset higher than its update_id.
        if ($highestId != -1) {
            $this->markUpdateAsRead($highestId);
        }

        return $updates;
    }

    /**
     * An alias for getUpdates that helps readability.
     *
     * @param $highestId
     *
     * @return Update[]
     */
    protected function markUpdateAsRead($highestId): array
    {
        $params = [];
        $params['offset'] = $highestId + 1;
        $params['limit'] = 1;

        return $this->getUpdates($params, false);
    }

    /**
     * Check update object for a command and process.
     *
     * @param Update $update
     */
    public function processCommand(Update $update)
    {
        $message = $update->getMessage();

        if ($message !== null && $message->has('entities')) {
            foreach ($message->entities as $entity) {
                if ($entity['type'] === 'bot_command') {
                    $command = substr(
                        $this->getMessageText($update),
                        $entity['offset'] + 1,
                        $entity['length']
                    );

                    $this->triggerCommand($command, $update);
                }
            }
        }
    }

    /**
     * Helper to Trigger Commands.
     *
     * @param string $name   Command Name
     * @param Update $update Update Object
     *
     * @return mixed
     */
    public function triggerCommand($name, Update $update)
    {
        return $this->getCommandBus()->execute($name, $this->getMessageText($update), $update);
    }

    /**
     * Get Message Text from Update.
     *
     * @param Update $update
     *
     * @return mixed|null
     */
    protected function getMessageText(Update $update)
    {
        $message = $update->getMessage();

        if ($message !== null && $message->has('text')) {
            return $message->text;
        }

        return null;
    }
}
