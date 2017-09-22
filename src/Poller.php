<?php
/**
 * This file is part of the Global Trading Technologies Ltd ad-poller package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * (c) fduch <alex.medwedew@gmail.com>
 *
 * Date: 11.08.17
 */

namespace Gtt\ADPoller;

use Exception;
use Doctrine\ORM\EntityManagerInterface;
use Gtt\ADPoller\Entity\PollTask;
use Gtt\ADPoller\Fetch\LdapFetcher;
use Gtt\ADPoller\ORM\Repository\PollTaskRepository;
use Gtt\ADPoller\Sync\SynchronizerInterface;

/**
 * Active Directory poller
 *
 * @see https://msdn.microsoft.com/en-us/library/ms677627.aspx
 *
 * @author fduch <alex.medwedew@gmail.com>
 */
class Poller
{
    /**
     * AD fetcher
     *
     * @var LdapFetcher
     */
    private $fetcher;

    /**
     * Synchronizer
     *
     * @var SynchronizerInterface
     */
    private $synchronizer;

    /**
     * Entity Manager
     *
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * Flag defines whether to fetch deleted entries during incremental poll or not
     *
     * @var boolean
     */
    private $detectDeleted;

    /**
     * Poller name
     *
     * @var string
     */
    private $name;

    /**
     * Poller constructor.
     *
     * @param LdapFetcher            $fetcher
     * @param SynchronizerInterface  $synchronizer
     * @param EntityManagerInterface $em
     * @param bool                   $detectDeleted
     * @param string                 $pollerName
     */
    public function __construct(
        LdapFetcher $fetcher,
        SynchronizerInterface $synchronizer,
        EntityManagerInterface $em,
        $detectDeleted = false,
        $pollerName = 'default')
    {
        $this->fetcher       = $fetcher;
        $this->synchronizer  = $synchronizer;
        $this->entityManager = $em;
        $this->detectDeleted = $detectDeleted;
        $this->name          = $pollerName;
    }

    /**
     * Returns poller name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Make a poll request to Active Directory and return amount of processed entries
     *
     * @param bool $forceFullSync force full synchronization instead of incremental one
     *
     * @return integer
     *
     * @throws Exception
     */
    public function poll($forceFullSync = false)
    {
        /** @var PollTaskRepository $pollTaskRepository */
        $pollTaskRepository     = $this->entityManager->getRepository(PollTask::class);
        $lastSuccessfulPollTask = $pollTaskRepository->findLastSuccessful();

        $rootDseDnsHostName = $this->fetcher->getRootDseDnsHostName();
        $invocationId       = $this->fetcher->getInvocationId();

        $isFullSyncRequired = $this->isFullSyncRequired($forceFullSync, $lastSuccessfulPollTask, $rootDseDnsHostName, $invocationId);
        $highestCommitedUSN = $this->fetcher->getHighestCommittedUSN();

        $currentTask = $this->createCurrentPollTask(
            $invocationId,
            $highestCommitedUSN,
            $rootDseDnsHostName,
            $lastSuccessfulPollTask,
            $isFullSyncRequired
        );

        $this->entityManager->persist($currentTask);
        $this->entityManager->flush();
        try {
            if ($isFullSyncRequired) {
                $fetchedEntriesCount = $this->fullSync($currentTask, $highestCommitedUSN);
            } else {
                $usnChangedStartFrom = $lastSuccessfulPollTask->getMaxUSNChangedValue() + 1;
                $fetchedEntriesCount = $this->incrementalSync($currentTask, $usnChangedStartFrom, $highestCommitedUSN);
            }
            $currentTask->succeed($this->entityManager, $fetchedEntriesCount);

            return $fetchedEntriesCount;
        } catch (Exception $e) {
            $currentTask->fail($this->entityManager, $e->getMessage());
            throw $e;
        } finally {
            $this->entityManager->flush();
        }
    }

    /**
     * Creates poll task entity for current sync
     *
     * @param string   $invocationId
     * @param integer  $highestCommitedUSN
     * @param string   $rootDseDnsHostName
     * @param PollTask $lastSuccessfulPollTask
     * @param bool     $isFullSync
     *
     * @return PollTask
     */
    protected function createCurrentPollTask($invocationId, $highestCommitedUSN, $rootDseDnsHostName, $lastSuccessfulPollTask, $isFullSync)
    {
        $currentTask = new PollTask(
            $this->entityManager,
            $this->name,
            $invocationId,
            $highestCommitedUSN,
            $rootDseDnsHostName,
            $lastSuccessfulPollTask,
            $isFullSync
        );

        return $currentTask;
    }

    /**
     * Returns true is full AD sync required and false otherwise
     *
     * @param bool          $forceFullSync
     * @param PollTask|null $lastSuccessfulSyncTask
     * @param string        $currentRootDseDnsHostName
     * @param string        $currentInvocationId
     *
     * @return bool
     */
    private function isFullSyncRequired($forceFullSync, PollTask $lastSuccessfulSyncTask = null, $currentRootDseDnsHostName, $currentInvocationId)
    {
        if ($forceFullSync) {
            return true;
        }

        if (!$lastSuccessfulSyncTask) {
            return true;
        }

        if ($lastSuccessfulSyncTask->getRootDseDnsHostName() != $currentRootDseDnsHostName) {
            return true;
        }

        if ($lastSuccessfulSyncTask->getInvocationId() != $currentInvocationId) {
            return true;
        }

        return false;
    }

    /**
     * Performs full sync
     *
     * @param PollTask $currentTask
     * @param integer  $highestCommitedUSN max usnChanged value to restrict objects search
     *
     * @return int count of synced entries
     */
    private function fullSync(PollTask $currentTask, $highestCommitedUSN)
    {
        $entries = $this->fetcher->fullFetch($highestCommitedUSN);
        $this->synchronizer->fullSync($this->name, $currentTask->getId(), $entries);

        return count($entries);
    }

    /**
     * Performs incremental sync
     *
     * @param PollTask $currentTask
     * @param integer  $usnChangedStartFrom min usnChanged value to restrict objects search
     * @param integer  $highestCommitedUSN  max usnChanged value to restrict objects search
     *
     * @return int
     */
    private function incrementalSync(PollTask $currentTask, $usnChangedStartFrom, $highestCommitedUSN)
    {
        list($changed, $deleted) = $this->fetcher->incrementalFetch($usnChangedStartFrom, $highestCommitedUSN, $this->detectDeleted);
        $this->synchronizer->incrementalSync($this->name, $currentTask->getId(), $changed, $deleted);

        return count($changed) + count($deleted);
    }
}

