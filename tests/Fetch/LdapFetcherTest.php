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

namespace Gtt\ADPoller\Fetch;

use Prophecy\Argument;
use Prophecy\Prophecy\ObjectProphecy;
use Zend\Ldap\Filter\AbstractFilter;
use Zend\Ldap\Filter;
use Zend\Ldap\Ldap;
use Zend\Ldap\Node\RootDse\ActiveDirectory;

class LdapFetcherTest extends \PHPUnit_Framework_TestCase
{
    public function testNullFilterApplicable()
    {
        new LdapFetcher($this->getMock(Ldap::class));
    }

    public function testStringFilterApplicable()
    {
        new LdapFetcher(
            $this->getMock(Ldap::class),
            '(&(a=b)(c=d))',
            '(!(a=v))',
            '(cn=*)'
            )
        ;
    }

    public function testAbstractFilterApplicable()
    {
        new LdapFetcher(
            $this->getMock(Ldap::class),
            Filter::andFilter(
                Filter::greater('a', 0),
                Filter::less('a', 3)
            ),
            Filter::greater('a', 2)->negate()->addOr(Filter::greater('a', 10))
        );
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidFilterIsNotApplicable()
    {
        new LdapFetcher(
            $this->getMock(Ldap::class),
            'valid',
            new \StdClass()
        );
    }

    public function testFullFetch()
    {
        /** @var Ldap|ObjectProphecy $ldap */
        $ldap = $this->prophesize(Ldap::class);

        $ldap->searchEntries(
            Argument::that(
                function(AbstractFilter $filter) {
                    return $filter->toString() === "(&(&(uSNChanged>=0)(uSNChanged<=5))(a>5))";
                }
            ),
            Argument::any(),
            Argument::any(),
            ['attrs']
        )->shouldBeCalled();

        $fetcher = new LdapFetcher($ldap->reveal(), 'a>5', null, null, ['attrs']);
        $fetcher->fullFetch(5);
    }

    public function testIncrementalFetchWithoutDeleted()
    {
        /** @var Ldap|ObjectProphecy $ldap */
        $ldap = $this->prophesize(Ldap::class);

        $ldap->searchEntries(
            Argument::that(
                function(AbstractFilter $filter) {
                    return $filter->toString() === "(&(&(uSNChanged>=1)(uSNChanged<=10))(a>5))";
                }
            ),
            Argument::any(),
            Argument::any(),
            ['attrs']
        )->shouldBeCalled();

        $fetcher = new LdapFetcher($ldap->reveal(), null, 'a>5', null, ['attrs']);
        $fetcher->incrementalFetch(1, 10);
    }

    public function testIncrementalFetchWithDeleted()
    {
        /** @var Ldap|\PHPUnit_Framework_MockObject_MockObject $ldap */
        $ldap = $this->getMock(Ldap::class);

        /** @var Ldap|\PHPUnit_Framework_MockObject_MockObject $ldap */
        $ad = $this
            ->getMockBuilder(ActiveDirectory::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;
        $ad->method('getDefaultNamingContext')->willReturn('context');
        $ldap->method('getRootDse')->willReturn($ad);

        $ldap
            ->expects($this->exactly(2))
            ->method('searchEntries')
            ->withConsecutive(
                [
                    $this->callback(
                        function(AbstractFilter $filter) {
                            return $filter->toString() === "(&(&(uSNChanged>=1)(uSNChanged<=10))(a>5))";
                        }
                    ),
                    $this->anything(),
                    $this->anything(),
                    $this->equalTo(['attrs'])
                ],
                [
                    $this->callback(
                        function(AbstractFilter $filter) {
                            return $filter->toString() === "(&(&(isDeleted=TRUE)(uSNChanged>=1)(uSNChanged<=10))(b>1))";
                        }
                    ),
                    $this->anything(),
                    $this->anything(),
                    $this->equalTo(['attrs'])
                ]
            )
        ;

        $fetcher = new LdapFetcher($ldap, null, 'a>5', 'b>1', ['attrs']);
        $fetcher->incrementalFetch(1, 10, true);
    }
}
