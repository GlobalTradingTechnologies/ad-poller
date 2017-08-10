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

use Symfony\Component\EventDispatcher\Event;

/**
 * Base sync event
 *
 * @author fduch <alex.medwedew@gmail.com>
 */
abstract class AbstractSyncEvent extends Event
{
    /**
     * Poller name
     *
     * @var string
     */
    protected $pollerName;

    /**
     * Sync sequential id
     *
     * @var int
     */
    protected $syncId;

    /**
     * AbstractSyncEvent constructor.
     *
     * @param string  $pollerName
     * @param int $syncId
     */
    public function __construct($pollerName, $syncId)
    {
        $this->pollerName = $pollerName;
        $this->syncId     = $syncId;
    }

    /**
     * @return string
     */
    public function getPollerName()
    {
        return $this->pollerName;
    }

    /**
     * @return int
     */
    public function getSyncId()
    {
        return $this->syncId;
    }
}
