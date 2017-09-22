<?php
/**
 * This file is part of the Global Trading Technologies Ltd ad-poller package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * (c) fduch <alex.medwedew@gmail.com>
 *
 * Date: 21.09.17
 */

namespace Gtt\ADPoller\Sync\Events;

use Gtt\ADPoller\Sync\Events\Event\FullSyncEvent;
use Gtt\ADPoller\Sync\Events\Event\IncrementalSyncEvent;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class EventSynchronizerTest extends \PHPUnit_Framework_TestCase
{
    public function testIncrementalSync()
    {
        /** @var EventDispatcherInterface|ObjectProphecy $ed */
        $ed = $this->prophesize(EventDispatcherInterface::class);
        $ed->dispatch(
            Argument::is(Events::INCREMENTAL_SYNC),
            Argument::exact(new IncrementalSyncEvent(
                'poller',
                123,
                [[1, 'objectguid' => ['111abc']]],
                [[2, 'objectsid'  => '121abc']]
                )
            )
        )->shouldBeCalled();
        $sync = new EventSynchronizer($ed->reveal());
        $this->assertEquals(2, $sync->incrementalSync(
            'poller',
            123,
            [[1, 'objectguid' => [hex2bin("111abc")]]],
            [[2, 'objectsid' => hex2bin("121abc")]]
            )
        );
    }

    public function testEmptyIncrementalSync()
    {
        /** @var EventDispatcherInterface|ObjectProphecy $ed */
        $ed = $this->prophesize(EventDispatcherInterface::class);
        $ed->dispatch()->shouldNotBeCalled();
        $sync = new EventSynchronizer($ed->reveal());
        $this->assertEquals(0, $sync->incrementalSync('poller', 123, [], []));
    }

    public function testFullSync()
    {
        /** @var EventDispatcherInterface|ObjectProphecy $ed */
        $ed = $this->prophesize(EventDispatcherInterface::class);
        $ed->dispatch(
            Argument::is(Events::FULL_SYNC),
            Argument::exact(new FullSyncEvent(
                'poller',
                123,
                [[1, 'objectguid' => ['111abc'], 'objectsid'  => '121abc']]
                )
            )
        )->shouldBeCalled();
        $sync = new EventSynchronizer($ed->reveal());
        $this->assertEquals(1, $sync->fullSync(
            'poller',
            123,
            [[1, 'objectguid' => [hex2bin('111abc')], 'objectsid'  => hex2bin('121abc')]]
            )
        );
    }

    public function testEmptyFullSync()
    {
        /** @var EventDispatcherInterface|ObjectProphecy $ed */
        $ed = $this->prophesize(EventDispatcherInterface::class);
        $ed->dispatch()->shouldNotBeCalled();
        $sync = new EventSynchronizer($ed->reveal());
        $this->assertEquals(0, $sync->fullSync('poller', 123, []));
    }
}
