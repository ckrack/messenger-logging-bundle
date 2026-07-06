<?php

declare(strict_types=1);

namespace C10k\MessengerLoggingBundle\Logging;

enum MessengerLogEvent: string
{
    /**
     * @see \Symfony\Component\Messenger\Event\MessageSentToTransportsEvent vendor/symfony/messenger/Event/MessageSentToTransportsEvent.php
     */
    case Queued = 'queued';

    /**
     * @see \Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent vendor/symfony/messenger/Event/WorkerMessageReceivedEvent.php
     */
    case Received = 'received';

    /**
     * @see \Symfony\Component\Messenger\Event\WorkerMessageHandledEvent vendor/symfony/messenger/Event/WorkerMessageHandledEvent.php
     */
    case Handled = 'handled';

    /**
     * @see \Symfony\Component\Messenger\Event\WorkerMessageFailedEvent vendor/symfony/messenger/Event/WorkerMessageFailedEvent.php
     */
    case Failed = 'failed';

    /**
     * @see \Symfony\Component\Messenger\Event\WorkerMessageRetriedEvent vendor/symfony/messenger/Event/WorkerMessageRetriedEvent.php
     */
    case Retried = 'retry_scheduled';
}
