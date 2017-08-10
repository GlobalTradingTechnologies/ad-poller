<?php
/**
 * This file is part of the Global Trading Technologies Ltd ad-poller package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * (c) fduch <alex.medwedew@gmail.com>
 *
 * Date: 17.08.17
 */

namespace Gtt\ADPoller\Exception;

use RuntimeException;
use Zend\Ldap\Node\RootDse\ActiveDirectory;

/**
 * Exception for case when there is an attempt to handle non-supported RootDse's
 *
 * @author fduch <alex.medwedew@gmail.com>
 */
class UnsupportedRootDseException extends RuntimeException
{
    public function __construct($currentRootDse)
    {
        parent::__construct(
            sprintf('Only %s root dse is supported. Fetched %s',
                ActiveDirectory::class,
                get_class($currentRootDse)
            )
        );
    }
}
