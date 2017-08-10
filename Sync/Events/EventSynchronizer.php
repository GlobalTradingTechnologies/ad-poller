<?php
/**
 * This file is part of the Global Trading Technologies Ltd ad-poller package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * (c) fduch <alex.medwedew@gmail.com>
 *
 * Date: 21.08.17
 */

namespace Gtt\ADPoller\Sync\Events;

use Gtt\ADPoller\Sync\Events\Event\FullSyncEvent;
use Gtt\ADPoller\Sync\Events\Event\IncrementalSyncEvent;
use Gtt\ADPoller\Sync\SynchronizerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Synchronizer that publishes events with payload from poller
 *
 * @author fduch <alex.medwedew@gmail.com>
 */
class EventSynchronizer implements SynchronizerInterface
{
    /**
     * Event Dispatcher
     *
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * EventSynchronizer constructor.
     *
     * @param EventDispatcherInterface $ed event dispatcher
     */
    public function __construct(EventDispatcherInterface $ed)
    {
        $this->eventDispatcher = $ed;
    }

    /**
     * {@inheritdoc}
     */
    public function incrementalSync($pollerName, $syncId, array $changed, array $deleted)
    {
        $changedEntriesToDeliver = $this->prepareEntriesToDeliver($changed);
        $deletedEntriesToDeliver = $this->prepareEntriesToDeliver($deleted);
        $entriesToDeliverCount   = count($changedEntriesToDeliver) + count($deletedEntriesToDeliver);

        if ($entriesToDeliverCount) {
            $event = new IncrementalSyncEvent($pollerName, $syncId, $changedEntriesToDeliver, $deletedEntriesToDeliver);
            $this->eventDispatcher->dispatch(Events::INCREMENTAL_SYNC, $event);
        }

        return $entriesToDeliverCount;
    }

    /**
     * {@inheritdoc}
     */
    public function fullSync($pollerName, $syncId, array $entries)
    {
        $entriesToDeliver      = $this->prepareEntriesToDeliver($entries);
        $entriesToDeliverCount = count($entriesToDeliver);

        if ($entriesToDeliverCount) {
            $event = new FullSyncEvent($pollerName, $syncId, $entriesToDeliver);
            $this->eventDispatcher->dispatch(Events::FULL_SYNC, $event);
        }

        return $entriesToDeliverCount;
    }

    /**
     * Replaces bin values with hex representations in order to be able to deliver entries safely
     *
     * @param array $entries list of entries
     *
     * @return array
     */
    private function prepareEntriesToDeliver(array $entries)
    {
        $binaryAttributes = ['objectguid', 'objectsid'];

        foreach ($entries as $key => &$entry) {
            foreach ($binaryAttributes as $binaryAttribute) {
                if (array_key_exists($binaryAttribute, $entry)) {
                    foreach ($entry[$binaryAttribute] as &$value) {
                        $value = bin2hex($value);
                    }
                }
            }
        }

        return $entries;
    }
}
