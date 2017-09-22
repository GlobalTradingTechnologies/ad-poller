<?php
/**
 * This file is part of the Global Trading Technologies Ltd ad-poller package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * (c) fduch <alex.medwedew@gmail.com>
 *
 * Date: 20.09.17
 */

namespace Gtt\ADPoller;

use Doctrine\ORM\EntityManagerInterface;
use Gtt\ADPoller\Entity\PollTask;
use Gtt\ADPoller\Fetch\LdapFetcher;
use Gtt\ADPoller\ORM\Repository\PollTaskRepository;
use Gtt\ADPoller\Sync\SynchronizerInterface;
use PHPUnit_Framework_TestCase;
use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;

class PollerTest extends PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider fullSyncParamsProvider
     */
    public function testFullSyncDecisions($forceFullSync, array $currentParams, array $lastSyncParams = [])
    {
        list($fetcher, $synchronizer, $resultTask, $poller) = $this->setupPoller($currentParams, $lastSyncParams);

        // check full sync mode
        $fetcher->fullFetch(Argument::any())->willReturn([['entry1'], ['entry2']]);
        $synchronizer->fullSync(Argument::any(), Argument::any(), Argument::any())->shouldBeCalled();
        $resultTask->expects($this->once())->method('succeed')->with($this->anything(), 2);

        $this->assertEquals(2, $poller->poll($forceFullSync));
    }

    public function fullSyncParamsProvider()
    {
        return [
            // force full sync
            [true, ['host1', 'invocation1']],

            // there is no last successful sync task
            [false, ['host1', 'invocation1']],

            // dse dns host differs
            [false, ['host1', 'invocation1'], ['host2', 'invocation1']],

            // invocation id differs
            [false, ['host1', 'invocation1'], ['host2', 'invocation1']],
        ];
    }

    public function testIncrementalSyncDecisionsWithDetectDeleted()
    {
        list($fetcher, $synchronizer, $resultTask, $poller) = $this->setupPoller(
            ['host1', 'invocation1'],
            ['host1', 'invocation1', 100500],
            123);

        // check inc sync mode
        $fetcher->incrementalFetch(100500 + 1, 123, true)->willReturn([[['entry1']], [['entry2'], ['entry3']]]);
        $synchronizer->incrementalSync(Argument::any(), Argument::any(), [['entry1']], [['entry2'], ['entry3']])->shouldBeCalled();
        $resultTask->expects($this->once())->method('succeed')->with($this->anything(), 3);

        $this->assertEquals(3, $poller->poll());
    }

    public function testIncrementalSyncDecisionsWithoutDetectDeleted()
    {
        list($fetcher, $synchronizer, $resultTask, $poller) = $this->setupPoller(
            ['host1', 'invocation1'],
            ['host1', 'invocation1', 100500],
            123,
            false
        );

        // check inc sync mode
        $fetcher->incrementalFetch(100500 + 1, 123, false)->willReturn([[['entry1']], [['entry2']]]);
        $synchronizer->incrementalSync(Argument::any(), Argument::any(), [['entry1']], [['entry2']])->shouldBeCalled();
        $resultTask->expects($this->once())->method('succeed')->with($this->anything(), 2);

        $this->assertEquals(2, $poller->poll());
    }

    /**
     * @expectedException \Exception
     */
    public function testFetchFailure()
    {
        list($fetcher, , $resultTask, $poller) = $this->setupPoller(['host1', 'invocation1'], ['host1', 'invocation1', 100500], 123);

        // check inc sync mode
        $fetcher->incrementalFetch(Argument::any(), Argument::any(), [['entry1']], [['entry2']])->willThrow(new \Exception());
        $resultTask->expects($this->once())->method('fail');

        $poller->poll();
    }

    /**
     * @expectedException \Exception
     */
    public function testSynchronizerFailure()
    {
        list(, $synchronizer, $resultTask, $poller) = $this->setupPoller(['host1', 'invocation1'], ['host1', 'invocation1', 100500], 123);

        // check inc sync mode
        $synchronizer->incrementalSync(100500 + 1, 123, Argument::any())->willThrow(new \Exception());
        $resultTask->expects($this->once())->method('fail');

        $poller->poll();
    }

    /**
     * @param array $currentParams
     * @param array $lastSyncParams
     * @param int   $currentHighestCommittedUsn
     *
     * @return array
     */
    private function setupPoller(array $currentParams, array $lastSyncParams, $currentHighestCommittedUsn = 100, $detectDeleted = true)
    {
        /** @var EntityManagerInterface|ObjectProphecy $em */
        $em = $this->prophesize(EntityManagerInterface::class);
        /** @var PollTaskRepository|ObjectProphecy $taskRepository */
        $taskRepository = $this->prophesize(PollTaskRepository::class);
        if ($lastSyncParams) {
            /** @var PollTask|ObjectProphecy $lastSuccessfullTask */
            $lastSuccessfullTask = $this->prophesize(PollTask::class);
            $lastSuccessfullTask->getRootDseDnsHostName()->willReturn($lastSyncParams[0]);
            $lastSuccessfullTask->getInvocationId()->willReturn($lastSyncParams[1]);
            if (isset($lastSyncParams[2])) {
                $lastSuccessfullTask->getMaxUSNChangedValue()->willReturn($lastSyncParams[2]);
            }
        } else {
            $lastSuccessfullTask = null;
        }
        $taskRepository->findLastSuccessful()->willReturn($lastSuccessfullTask);

        $em->getRepository(PollTask::class)->willReturn($taskRepository);
        $em->persist(Argument::type(PollTask::class))->shouldBeCalled();
        $em->flush()->shouldBeCalled();

        /** @var LdapFetcher|ObjectProphecy $fetcher */
        $fetcher = $this->prophesize(LdapFetcher::class);
        /** @var SynchronizerInterface|ObjectProphecy $synchronizer */
        $synchronizer = $this->prophesize(SynchronizerInterface::class);

        $fetcher->getRootDseDnsHostName()->willReturn($currentParams[0]);
        $fetcher->getInvocationId()->willReturn($currentParams[1]);
        $fetcher->getHighestCommittedUSN()->willReturn($currentHighestCommittedUsn);

        /** @var PollTask|\PHPUnit_Framework_MockObject_MockObject $resultTask */
        $resultTask = $this
            ->getMockBuilder(PollTask::class)
            ->disableOriginalConstructor()
            ->getMock();
        /** @var Poller|\PHPUnit_Framework_MockObject_MockObject $poller */
        $poller = $this
            ->getMockBuilder(Poller::class)
            ->setConstructorArgs([
                $fetcher->reveal(),
                $synchronizer->reveal(),
                $em->reveal(),
                $detectDeleted
            ])
            ->setMethods(['createCurrentPollTask'])
            ->getMock();
        $poller->method('createCurrentPollTask')->will($this->returnValue($resultTask));

        return [$fetcher, $synchronizer, $resultTask, $poller];
    }
}
