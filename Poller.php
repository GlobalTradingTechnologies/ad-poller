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
use Gtt\ADPoller\Exception\UnsupportedRootDseException;
use Gtt\ADPoller\ORM\Repository\PollTaskRepository;
use Gtt\ADPoller\Sync\SynchronizerInterface;
use InvalidArgumentException;
use Zend\Ldap\Filter;
use Zend\Ldap\Filter\AbstractFilter;
use Zend\Ldap\Ldap;
use Zend\Ldap\Node\RootDse\ActiveDirectory;

/**
 * Active Directory LDAP poller
 *
 * TODO perform paginated requests
 *
 * @see https://msdn.microsoft.com/en-us/library/ms677627.aspx
 *
 * @author fduch <alex.medwedew@gmail.com>
 */
class Poller
{
    /**
     * Well known GUID used to fetch deleted objects
     *
     * @see https://msdn.microsoft.com/en-us/library/ms675565(v=vs.85).aspx
     */
    const WK_GUID_DELETED_OBJECTS_CONTAINER_W = '18E2EA80684F11D2B9AA00C04F79F805';

    /**
     * Synchronizer
     *
     * @var SynchronizerInterface
     */
    private $synchronizer;

    /**
     * Ldap instance
     *
     * @var Ldap
     */
    private $ldap;

    /**
     * Entity Manager
     *
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * Base ldap search filter used to describe objects to fetch
     *
     * @var AbstractFilter
     */
    private $baseEntryFilter;

    /**
     * Base list of AD attributes to fetch
     *
     * @var array
     */
    private $entryAttributesToFetch;

    /**
     * Additional ldap search options
     *
     * @see http://php.net/manual/en/function.ldap-set-option.php for details
     *
     * @var array
     */
    private $ldapSearchOptions;

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
     * Current AD root dse
     *
     * @var ActiveDirectory
     */
    private $rootDse;

    /**
     * Poller constructor.
     *
     * @param SynchronizerInterface  $synchronizer
     * @param Ldap                   $ldap
     * @param EntityManagerInterface $em
     * @param AbstractFilter         $entryFilter
     * @param array                  $entryAttributesToFetch
     * @param bool                   $detectDeleted
     * @param string                 $pollerName
     */
    public function __construct(
        SynchronizerInterface $synchronizer,
        Ldap $ldap,
        EntityManagerInterface $em,
        $entryFilter,
        array $entryAttributesToFetch = [],
        $detectDeleted = true,
        $pollerName = 'default')
    {
        $this->synchronizer           = $synchronizer;
        $this->ldap                   = $ldap;
        $this->entityManager          = $em;
        $this->detectDeleted          = $detectDeleted;
        $this->name                   = $pollerName;
        $this->entryAttributesToFetch = $entryAttributesToFetch;

        if (is_string($entryFilter)) {
            $this->baseEntryFilter = new Filter\StringFilter($entryFilter);
        } elseif ($entryFilter instanceof AbstractFilter) {
            $this->baseEntryFilter = $entryFilter;
        } else {
            throw new InvalidArgumentException(
                sprintf(
                    'baseEntryFilter argument must be either instance of %s or string. %s given',
                    AbstractFilter::class,
                    gettype($entryFilter)
                )
            );
        }
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

        $rootDse            = $this->getRootDse();
        $rootDseDnsHostName = $rootDse->getDnsHostName();
        $dsServiceName      = $rootDse->getDsServiceName();

        $dsServiceNode = $this->ldap->getNode($dsServiceName);
        $invocationId  = bin2hex($dsServiceNode->getAttribute('invocationId', 0));

        $isFullSyncRequired = $this->isFullSyncRequired($forceFullSync, $lastSuccessfulPollTask, $rootDseDnsHostName, $invocationId);
        $highestCommitedUSN = $rootDse->getHighestCommittedUSN();

        $currentTask = new PollTask(
            $this->entityManager,
            $this->name,
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
     * Sets additional ldap search options
     *
     * @param string $name    name of the options
     * @param array  $options list of options
     */
    public function setLdapSearchOptions($name, array $options)
    {
        $this->ldapSearchOptions[$name] = $options;
    }

    /**
     * Returns current AD root dse
     *
     * @return ActiveDirectory
     */
    private function getRootDse()
    {
        if (!$this->rootDse) {
            $rootDse = $this->ldap->getRootDse();
            if (!$rootDse instanceof ActiveDirectory) {
                throw new UnsupportedRootDseException($rootDse);
            }
            $this->rootDse = $rootDse;
        }

        return $this->rootDse;
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
        $searchFilter = Filter::andFilter(
            $this->baseEntryFilter,
            Filter::greaterOrEqual('uSNChanged', 0),
            Filter::lessOrEqual('uSNChanged', $highestCommitedUSN)
        );
        $entries = $this->search($searchFilter);

        $this->synchronizer->fullSync($this->name, $currentTask->getId(), $entries);

        return count($entries);
    }

    /**
     * Performs incremental sync
     *
     * @param PollTask $currentTask
     * @param integer  $usnChangedStartFrom max usnChanged value to restrict objects search
     * @param integer  $highestCommitedUSN  max usnChanged value to restrict objects search
     *
     * @return int
     */
    private function incrementalSync(PollTask $currentTask, $usnChangedStartFrom, $highestCommitedUSN)
    {
        // fetch changed
        $changedFilter = Filter::andFilter(
            $this->baseEntryFilter,
            Filter::greaterOrEqual('uSNChanged', $usnChangedStartFrom),
            Filter::lessOrEqual('uSNChanged', $highestCommitedUSN)
        );
        $changed = $this->search($changedFilter);

        // fetch deleted
        $deleted = [];
        if ($this->detectDeleted) {
            $defaultNamingContext = $this->getRootDse()->getDefaultNamingContext();
            $options              = $this->ldap->getOptions();
            $originalHost         = isset($options['host']) ? $options['host'] : '';
            $hostForDeleted = rtrim($originalHost, "/") . "/" . urlencode(
                sprintf("<WKGUID=%s,%s>", self::WK_GUID_DELETED_OBJECTS_CONTAINER_W, $defaultNamingContext)
            );
            $options['host'] = $hostForDeleted;
            // force reconnection for search of deleted entries
            $this->ldap->setOptions($options);
            $this->ldap->disconnect();

            $deleted = $this->search(Filter::andFilter(
                Filter::equals('isEntryDeactivated', 'TRUE'),
                Filter::greaterOrEqual('uSNChanged', $usnChangedStartFrom),
                Filter::lessOrEqual('uSNChanged', $highestCommitedUSN)
            ));
        }

        $this->synchronizer->incrementalSync($this->name, $currentTask->getId(), $changed, $deleted);

        return count($changed) + count($deleted);
    }

    /**
     * Performs ldap search by filter
     *
     * @param AbstractFilter $filter ldap filter
     *
     * @return array list of fetched entries
     */
    private function search(AbstractFilter $filter)
    {
        if ($this->ldapSearchOptions) {
            $ldapResource = $this->ldap->getResource();
            foreach ($this->ldapSearchOptions as $name => $options) {
                ldap_set_option($ldapResource, $name, $options);
            }
        }

        // TODO check that ldap could not return empty entries (eg. if there is no required attribute)
        return $this->ldap->searchEntries($filter, null, Ldap::SEARCH_SCOPE_SUB, $this->entryAttributesToFetch);
    }
}

