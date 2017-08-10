<?php
/**
 * This file is part of the Global Trading Technologies Ltd ad-poller-bundle package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * (c) fduch <alex.medwedew@gmail.com>
 *
 * Date: 11.08.17
 */

namespace Gtt\ADPoller\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Task status
 *
 * @ORM\Entity
 * @ORM\Table(name="poll_task_status")
 *
 * @author fduch <alex.medwedew@gmail.com>
 */
class PollTaskStatus
{
    /**
     * Running
     */
    const RUNNING = 1;

    /**
     * Succeeded
     */
    const SUCCEEDED = 2;

    /**
     * Failed
     */
    const FAILED = 3;

    /**
     * ID
     *
     * @var int
     *
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * Name
     *
     * @var string
     *
     * @ORM\Column(type="string", length=255)
     */
    protected $name;

    /**
     * Get the ID
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get the name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }
}
