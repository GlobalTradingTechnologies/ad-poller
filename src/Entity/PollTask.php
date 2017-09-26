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

namespace Gtt\ADPoller\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;

/**
 * Active directory poll task
 *
 * @author fduch <alex.medwedew@gmail.com>
 *
 * @ORM\Table(name="poll_task", options={"comment": "Poll task"})
 * @ORM\Entity(repositoryClass="Gtt\ADPoller\ORM\Repository\PollTaskRepository")
 */
class PollTask
{
    /**
     * Status for the running state
     */
    const STATUS_RUNNING = 1;

    /**
     * Status for the state when task was successfully finished
     */
    const STATUS_SUCCEED = 2;

    /**
     * Status for the state when task was failed
     */
    const STATUS_FAILED = 3;

    /**
     * @var int
     *
     * @ORM\Id
     * @ORM\Column(type="integer", options={"unsigned"=true})
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * Name of current poller
     *
     * @var string
     *
     * @ORM\Column(name="poller_name", type="string", length=255, nullable=false,
     *     options={"comment": "Name of the poller"})
     */
    private $pollerName;

    /**
     * Invocation ID
     *
     * @var string
     *
     * @ORM\Column(name="invocation_id", type="string", length=40, nullable=false,
     *     options={"comment": "Invocation ID"})
     */
    private $invocationId;

    /**
     * Maximum value of uSNChanged attribute of the AD entities being processed during current task
     *
     * @var integer
     *
     * @ORM\Column(name="max_usnchanged_value", type="integer", nullable=false,
     *     options={"comment": "Invocation ID"})
     */
    private $maxUSNChangedValue;

    /**
     * Root DSE DNS host name used to perform the sync
     *
     * @var string
     *
     * @ORM\Column(name="root_dse_dns_host_name", type="string", length=255, nullable=false,
     *     options={"comment": "Root DSE DNS host name used to perform the sync"})
     */
    private $rootDseDnsHostName;

    /**
     * Flag holds information about full/partial sync provided by current task
     *
     * @ORM\Column(type="boolean", name="is_full_sync", options={"comment": "Full or partial sync processing"})
     *
     * @var boolean
     */
    private $isFullSync;

    /**
     * Status of the task
     *
     * @var integer
     *
     * @ORM\Column(name="status_id", type="integer", nullable=false,
     *     options={"comment": "Task status"})
     */
    private $status;

    /**
     * Amount of entities fetched by current task
     *
     * @var integer
     *
     * @ORM\Column(name="fetched_entities_amount", type="integer", nullable=true,
     *     options={"comment": "Amount of successfully fetched entries"})
     */
    private $fetchedEntitiesAmount;

    /**
     * Error message in case of failure
     *
     * @var string
     *
     * @ORM\Column(name="error_message", type="text", nullable=true,
     *     options={"comment": "Error message in case of failure"})
     */
    private $errorMessage;

    /**
     * Created timestamp
     *
     * @var \Datetime
     *
     * @ORM\Column(type="datetime", name="created_ts", columnDefinition="TIMESTAMP NULL DEFAULT NULL")
     */
    private $created;

    /**
     * Closed timestamp
     *
     * @var \Datetime
     *
     * @ORM\Column(type="datetime", name="closed_ts", columnDefinition="TIMESTAMP NULL DEFAULT NULL")
     */
    private $closed;

    /**
     * Parent branch
     *
     * @var PollTask|null
     *
     * @ORM\ManyToOne(targetEntity="PollTask")
     * @ORM\JoinColumn(name="parent_id", referencedColumnName="id")
     */
    private $parent;

    /**
     * PollTask constructor.
     *
     * @param string                 $pollerName
     * @param string                 $invocationId
     * @param int                    $maxUSNChangedValue
     * @param string                 $rootDseDnsHostName
     * @param PollTask|null          $parent
     * @param bool                   $isFullSync
     */
    public function __construct(
        $pollerName,
        $invocationId,
        $maxUSNChangedValue,
        $rootDseDnsHostName,
        PollTask $parent = null,
        $isFullSync = false)
    {
        $this->pollerName         = $pollerName;
        $this->invocationId       = $invocationId;
        $this->maxUSNChangedValue = $maxUSNChangedValue;
        $this->rootDseDnsHostName = $rootDseDnsHostName;
        $this->status             = self::STATUS_RUNNING;
        $this->parent             = $parent;
        $this->isFullSync         = $isFullSync;
        $this->created            = new DateTime();
    }

    public function succeed($fetchedEntitiesAmount)
    {
        $this->status                = self::STATUS_SUCCEED;
        $this->fetchedEntitiesAmount = $fetchedEntitiesAmount;
        $this->closed                = new DateTime();
    }

    public function fail($errorMessage)
    {
        $this->status       = self::STATUS_FAILED;
        $this->errorMessage = $errorMessage;
        $this->closed       = new DateTime();
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getPollerName()
    {
        return $this->pollerName;
    }

    /**
     * @return string
     */
    public function getInvocationId()
    {
        return $this->invocationId;
    }

    /**
     * @return int
     */
    public function getMaxUSNChangedValue()
    {
        return $this->maxUSNChangedValue;
    }

    /**
     * @return string
     */
    public function getRootDseDnsHostName()
    {
        return $this->rootDseDnsHostName;
    }

    /**
     * @return bool
     */
    public function isFullSync()
    {
        return $this->isFullSync;
    }

    /**
     * @return PollTaskStatus
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @return int
     */
    public function getFetchedEntitiesAmount()
    {
        return $this->fetchedEntitiesAmount;
    }

    /**
     * @return string
     */
    public function getErrorMessage()
    {
        return $this->errorMessage;
    }

    /**
     * @return Datetime
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     * @return Datetime
     */
    public function getClosed()
    {
        return $this->closed;
    }

    /**
     * @return PollTask|null
     */
    public function getParent()
    {
        return $this->parent;
    }
}
