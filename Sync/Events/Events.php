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

/**
 * List of sync events
 *
 * @author fduch <alex.medwedew@gmail.com>
 */
final class Events
{
    /**
     * Incremental sync event name
     */
    const INCREMENTAL_SYNC = 'gtt.ad_poller.incremental_sync';

    /**
     * Full sync event name
     */
    const FULL_SYNC = 'gtt.ad_poller.full_sync';
}