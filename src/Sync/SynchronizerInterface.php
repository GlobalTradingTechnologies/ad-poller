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

namespace Gtt\ADPoller\Sync;

use Exception;

/**
 * Base interface for all poll synchronizers
 *
 * @author fduch <alex.medwedew@gmail.com>
 */
interface SynchronizerInterface
{
    /**
     * Incremental sync (aware update on consumer side)
     *
     * @param string  $pollerName poller name
     * @param integer $syncId     sequential id of sync
     * @param array   $changed    list of changed entries
     * @param array   $deleted    list of deleted entries
     *
     * @throws Exception in case of failure
     */
    public function incrementalSync($pollerName, $syncId, array $changed, array $deleted);

    /**
     * Full sync (aware refill on consumer side)
     *
     * @param string  $pollerName poller name
     * @param integer $syncId     sequential id of sync
     * @param array   $changed    list of entries
     *
     * @throws Exception in case of failure
     */
    public function fullSync($pollerName, $syncId, array $entries);
}
