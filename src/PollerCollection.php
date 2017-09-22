<?php
/**
 * This file is part of the Global Trading Technologies Ltd ad-poller package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * (c) fduch <alex.medwedew@gmail.com>
 *
 * Date: 22.08.17
 */

namespace Gtt\ADPoller;

use ArrayIterator;
use InvalidArgumentException;
use IteratorAggregate;

/**
 * Pollers collection
 *
 * @author fduch <alex.medwedew@gmail.com>
 */
class PollerCollection implements IteratorAggregate
{
    /**
     * List of pollers
     *
     * @var Poller[]
     */
    private $pollers = array();

    /**
     * Add poller to collection
     *
     * @param Poller $poller
     *
     * @throws InvalidArgumentException in case when poller with the same name already exists
     */
    public function addPoller(Poller $poller)
    {
        $name = $poller->getName();
        if (array_key_exists($name, $this->pollers)) {
            throw new InvalidArgumentException("Poller named '$name' already exists");
        }

        $this->pollers[$name] = $poller;
    }

    /**
     * Returns poller by name
     *
     * @param $name
     *
     * @return Poller
     *
     * @throws InvalidArgumentException in case of absence
     */
    public function getPoller($name)
    {
        if (!array_key_exists($name, $this->pollers)) {
            throw new InvalidArgumentException("Poller named '$name' does not exist");
        }

        return $this->pollers[$name];
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        return new ArrayIterator($this->pollers);
    }
}
