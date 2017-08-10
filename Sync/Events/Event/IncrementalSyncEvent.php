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

namespace Gtt\ADPoller\Sync\Events\Event;

/**
 * Incremental sync event
 *
 * @author fduch <alex.medwedew@gmail.com>
 */
class IncrementalSyncEvent extends AbstractSyncEvent
{
    /**
     * List of changed AD entries
     *
     * @var array
     */
    private $changed;

    /**
     * List of deleted AD entries
     *
     * @var array
     */
    private $deleted;

    /**
     * IncrementalSyncEvent constructor.
     *
     * @param string $pollerName
     * @param int    $syncId
     * @param array  $changed
     * @param array  $deleted
     */
    public function __construct($pollerName, $syncId, array $changed, array $deleted)
    {
        parent::__construct($pollerName, $syncId);
        $this->changed = $changed;
        $this->deleted = $deleted;
    }

    /**
     * @return array
     */
    public function getChanged()
    {
        return $this->changed;
    }

    /**
     * @return array
     */
    public function getDeleted()
    {
        return $this->deleted;
    }
}
