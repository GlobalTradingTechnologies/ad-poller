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

namespace Gtt\ADPoller\Fetch;

use Gtt\ADPoller\Exception\UnsupportedRootDseException;
use InvalidArgumentException;
use Zend\Ldap\Filter\AbstractFilter;
use Zend\Ldap\Filter;
use Zend\Ldap\Ldap;
use Zend\Ldap\Node\RootDse\ActiveDirectory;

/**
 * Fetches Active Directory resource using ldap API
 *
 * TODO perform paginated requests after resolving of https://github.com/zendframework/zend-ldap/issues/41
 *
 * @author fduch <alex.medwedew@gmail.com>
 */
class LdapFetcher
{
    /**
     * Well known GUID used to fetch deleted objects
     *
     * @see https://msdn.microsoft.com/en-us/library/ms675565(v=vs.85).aspx
     */
    const WK_GUID_DELETED_OBJECTS_CONTAINER_W = '18E2EA80684F11D2B9AA00C04F79F805';

    /**
     * Ldap instance
     *
     * @var Ldap
     */
    private $ldap;

    /**
     * Base ldap search filter used to define objects during full fetch
     *
     * @var AbstractFilter
     */
    private $fullSyncEntryFilter;

    /**
     * Base ldap search filter used to define objects during incremental fetch
     *
     * @var AbstractFilter
     */
    private $incrementalSyncEntryFilter;

    /**
     * Base ldap search filter used to define objects during deleted fetch
     *
     * @var AbstractFilter
     */
    private $deletedSyncEntryFilter;

    /**
     * Base list of AD attributes to fetch
     *
     * @var array
     */
    private $entryAttributesToFetch;

    /**
     * Current AD root dse
     *
     * @var ActiveDirectory
     */
    private $rootDse;

    /**
     * Additional ldap search options
     *
     * @see http://php.net/manual/en/function.ldap-set-option.php for details
     *
     * @var array
     */
    private $ldapSearchOptions;

    /**
     * Fetcher constructor.
     *
     * @param Ldap                       $ldap
     * @param AbstractFilter|string|null $fullSyncEntryFilter
     * @param AbstractFilter|string|null $incrementalSyncEntryFilter
     * @param AbstractFilter|string|null $deletedSyncEntryFilter
     * @param array                      $entryAttributesToFetch
     */
    public function __construct(
        Ldap $ldap,
        $fullSyncEntryFilter = null,
        $incrementalSyncEntryFilter = null,
        $deletedSyncEntryFilter = null,
        array $entryAttributesToFetch = [])
    {
        $this->ldap                       = $ldap;
        $this->fullSyncEntryFilter        = $this->setupEntryFilter($fullSyncEntryFilter);
        $this->incrementalSyncEntryFilter = $this->setupEntryFilter($incrementalSyncEntryFilter);
        $this->deletedSyncEntryFilter     = $this->setupEntryFilter($deletedSyncEntryFilter);
        $this->entryAttributesToFetch     = $entryAttributesToFetch;
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
     * Returns current root dse dns host name
     *
     * @return string
     */
    public function getRootDseDnsHostName()
    {
        return $this->getRootDse()->getDnsHostName();
    }

    /**
     * Returns current root dse highest committed usn
     *
     * @return string
     */
    public function getHighestCommittedUSN()
    {
        return $this->getRootDse()->getHighestCommittedUSN();
    }

    /**
     * Returns current invocation id
     *
     * @return string
     */
    public function getInvocationId()
    {
        $dsServiceNode = $this->ldap->getNode($this->getRootDse()->getDsServiceName());
        $invocationId  = bin2hex($dsServiceNode->getAttribute('invocationId', 0));

        return $invocationId;
    }

    /**
     * Performs full fetch
     *
     * @param integer $uSNChangedTo max usnChanged value to restrict objects search
     *
     * @return array list of fetched entries
     */
    public function fullFetch($uSNChangedTo)
    {
        $searchFilter = Filter::andFilter(
            Filter::greaterOrEqual('uSNChanged', 0),
            Filter::lessOrEqual('uSNChanged', $uSNChangedTo)
        );

        if ($this->fullSyncEntryFilter) {
            $searchFilter = $searchFilter->addAnd($this->fullSyncEntryFilter);
        }

        $entries = $this->search($searchFilter);

        return $entries;
    }

    /**
     * Performs incremental fetch
     *
     * @param integer $uSNChangedFrom min usnChanged value to restrict objects search
     * @param integer $uSNChangedTo   max usnChanged value to restrict objects search
     * @param bool    $detectDeleted  whether deleted entries should be fetched or not
     *
     * @return array with following structure:
     *    [
     *        $changed, // list of changed entries
     *        $deleted  // list of deleted entries
     *    ]
     */
    public function incrementalFetch($uSNChangedFrom, $uSNChangedTo, $detectDeleted = false)
    {
        // fetch changed
        $changedFilter = Filter::andFilter(
            Filter::greaterOrEqual('uSNChanged', $uSNChangedFrom),
            Filter::lessOrEqual('uSNChanged', $uSNChangedTo)
        );
        if ($this->incrementalSyncEntryFilter) {
            $changedFilter = $changedFilter->addAnd($this->incrementalSyncEntryFilter);
        }
        $changed = $this->search($changedFilter);

        // fetch deleted
        $deleted = [];
        if ($detectDeleted) {
            $defaultNamingContext = $this->getRootDse()->getDefaultNamingContext();
            $options              = $this->ldap->getOptions();
            $originalHost         = isset($options['host']) ? $options['host'] : '';
            $hostForDeleted = rtrim($originalHost, "/") . "/" .urlencode(
                    sprintf("<WKGUID=%s,%s>", self::WK_GUID_DELETED_OBJECTS_CONTAINER_W, $defaultNamingContext)
                )
            ;

            // hack that workarounds zendframework/zend-ldap connection issues.
            // should be removed after resolution of https://github.com/zendframework/zend-ldap/pull/69
            if (!preg_match('~^ldap(?:i|s)?://~', $hostForDeleted)) {
                $schema = "ldap://";
                if ((isset($options['port']) && $options['port'] == 636) || (isset($options['useSsl']) && $options['useSsl'] == true)) {
                    $schema = "ldaps://";
                }
                $hostForDeleted = $schema.$hostForDeleted;
            }
            // end of hack

            $options['host'] = $hostForDeleted;

            // force reconnection for search of deleted entries
            $this->ldap->setOptions($options);
            $this->ldap->disconnect();

            $deletedFilter = Filter::andFilter(
                Filter::equals('isDeleted', 'TRUE'),
                Filter::greaterOrEqual('uSNChanged', $uSNChangedFrom),
                Filter::lessOrEqual('uSNChanged', $uSNChangedTo)
            );
            if ($this->deletedSyncEntryFilter) {
                $deletedFilter = $deletedFilter->addAnd($this->deletedSyncEntryFilter);
            }
            $deleted = $this->search($deletedFilter);
        }

        return [$changed, $deleted];
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
     * Validates specified zend ldap filter and cast it to AbstractFilter implementation
     *
     * @param string|AbstractFilter $entryFilter zend ldap filter
     *
     * @return AbstractFilter|null
     *
     * @throws InvalidArgumentException in case of invalid filter
     */
    private function setupEntryFilter($entryFilter)
    {
        if ($entryFilter == null) {
            return null;
        }

        if (is_string($entryFilter)) {
            $filter = new Filter\StringFilter($entryFilter);
        } elseif ($entryFilter instanceof AbstractFilter) {
            $filter = $entryFilter;
        } else {
            throw new InvalidArgumentException(
                sprintf(
                    'baseEntryFilter argument must be either instance of %s or string. %s given',
                    AbstractFilter::class,
                    gettype($entryFilter)
                )
            );
        }

        return $filter;
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

        return $this->ldap->searchEntries($filter, null, Ldap::SEARCH_SCOPE_SUB, $this->entryAttributesToFetch);
    }
}
