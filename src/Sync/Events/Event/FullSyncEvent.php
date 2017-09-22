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
 * Full sync event
 *
 * @author fduch <alex.medwedew@gmail.com>
 */
class FullSyncEvent extends AbstractSyncEvent
{
    /**
     * Full list of AD entries
     *
     * @var array
     */
    private $entries;

    /**
     * FullSyncEvent constructor.
     *
     * @param string $pollerName
     * @param int    $syncId
     * @param array  $entries
     */
    public function __construct($pollerName, $syncId, array $entries)
    {
        parent::__construct($pollerName, $syncId);
        $this->entries = $entries;
    }

    /**
     * @return array
     */
    public function getEntries()
    {
        return $this->entries;
    }
}
