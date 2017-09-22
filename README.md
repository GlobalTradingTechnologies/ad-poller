Active Directory Change Poller
==============================

[![Build Status](https://travis-ci.org/GlobalTradingTechnologies/ad-poller.svg?branch=master)](https://travis-ci.org/GlobalTradingTechnologies/ad-poller)
[![Latest Stable Version](https://poser.pugx.org/gtt/ad-poller/version)](https://packagist.org/packages/gtt/ad-poller)
[![Latest Unstable Version](https://poser.pugx.org/gtt/ad-poller/v/unstable)](//packagist.org/packages/gtt/ad-poller)
[![License](https://poser.pugx.org/gtt/ad-poller/license)](https://packagist.org/packages/gtt/ad-poller)

This package is PHP implementation of [algorithm](https://msdn.microsoft.com/en-us/library/ms677627.aspx) of polling for changes in Active Directory servers 
using [uSNChanged](https://msdn.microsoft.com/en-us/library/ms677627.aspx) attribute with additional 
features allowing custom adjustments for Active Directory fetching processes and changesets handling. 

The main usage purpose is to keep you application in sync with Active Directory structure. 

Overview
========
The core concept is to constantly poll Active Directory server and perform *incremental* fetch to obtain changesets using [uSNChanged](https://msdn.microsoft.com/en-us/library/ms677627.aspx) attribute as an offset.
Each poll task run is persisted in database to save the offset and fetch statistics.
In case of Active Directory controller swap, server failures or initial run algorithm performs *full* fetch to obtain the fullset.
Received data is subject to deliver to the target application to be handled. 
 
The component consists of three main parts: [poller](#poller), [fetcher](#fetcher) and [synchronizer](#synchronizer). 

### Poller
This is a heart of the library and base implementation of original [polling algorithm](https://msdn.microsoft.com/en-us/library/ms677627.aspx).
Uses [fetcher](#fetcher) to interact with Active Directory Server in order to fetch changesets and [synchronizer](#synchronizer)
to process obtained changesets.

See [Poller](https://github.com/GlobalTradingTechnologies/ad-poller/blob/master/src/Poller.php) implementation for details.

### Fetcher
This part is responsible to interact with Active Directory. Allows poller to fetch neccessary metadata from Active Directory 
and search changesets using [zendframework/zend-ldap](https://github.com/zendframework/zend-ldap). 

See [LdapFetcher](https://github.com/GlobalTradingTechnologies/ad-poller/blob/master/src/Fetch/LdapFetcher.php) implementation for details.

### Synchronizer
Synchronizer is about handling datasets received during polling process.
Base implementation uses [symfony/event-dispatcher](https://github.com/symfony/event-dispatcher) to publish changesets/fullset as an Events.
For incremental sync there is a [IncrementalSyncEvent](https://github.com/GlobalTradingTechnologies/ad-poller/blob/master/src/Sync/Events/Event/IncrementalSyncEvent.php), 
for full sync - [FullSyncEvent](https://github.com/GlobalTradingTechnologies/ad-poller/blob/master/src/Sync/Events/Event/FullSyncEvent.php).

See [EventSynchronizer](https://github.com/GlobalTradingTechnologies/ad-poller/blob/master/src/Sync/Events/EventSynchronizer.php) implementation for details.

Component provides [SynchronizerInterface](https://github.com/GlobalTradingTechnologies/ad-poller/blob/master/src/Sync/SynchronizerInterface.php) allowing implement your own
synchronizer for custom aims.   

Setup and usage
===============

### Package installation
To install the package use composer:

```
composer require gtt/ad-poller
```

### Database setup
It is possible to generate schema using doctrine console utils.
You can clone the repository from scratch, adjust cli-config.php with credentials to your test database and generate init sql:
```
composer install && php ./vendor/bin/doctrine orm:schema-tool:create --dump-sql
```
Also execute [init_data.sql](https://github.com/GlobalTradingTechnologies/ad-poller/blob/master/res/init_data.sql) to fill database initially

### Application setup
Create poller:
```php
// configure ldap connector
$ldapConnector = new Ldap(
    // Connector options. @see https://github.com/zendframework/zend-ldap for details
    [
        'host' => 'ldap.myorg.com',
        'username' => 'poller@ldap.myorg.com',
        'password' => 'secret',
        'accountDomainName' => 'ldap.myorg.com',
        'baseDn' => 'DC=myorg,DC=com'
    ] 
);

// configure ldap fetcher
$ldapFetcher = new \Gtt\ADPoller\Fetch\LdapFetcher(
    $ldapConnector,
    // Optional ldap filter describes entries to fetch during full sync
    '&(objectClass=user)(objectCategory=person))',
    // Optional ldap filter describes entries to fetch during incremental sync.
    // It can differ from the previous one if you want track deactivatation of entities
    // (during full sync you need only active, but here - not)
    '&(objectClass=user)(objectCategory=person))',
    // Optional ldap filter describes deleted entries to fetch during incremental sync
    '&(objectClass=user)(objectCategory=person))',
    // list of properties to be fetched
    ['cn', 'displayname','telephonenumber', 'description']
);
// you also can specify additional ldap search options here if you need, for example:
$ldapFetcher->setLdapSearchOptions(LDAP_OPT_SERVER_CONTROLS, [['oid' => '1.2.840.113556.1.4.529']]);

// configure entity manager to persist poll tasks
$em = \Doctrine\ORM\EntityManager::create($conn, $config);

// configure synchronizer (use your own SynchronizerInterface implementation if needed)
$sync = new \Gtt\ADPoller\Sync\Events\EventSynchronizer(new \Symfony\Component\EventDispatcher\EventDispatcher());

// configure Poller itself
$poller = new Poller(
    $ldapFetcher,
    $sync,
    $em,
    // optionaly you can tell poller to fetch deleted entries
    // @see https://msdn.microsoft.com/en-us/library/ms677927(v=vs.85).aspx for details 
    false
    // optional poller name - use it if you have different pollers
    'mypoller'
);
```

You also can create as many pollers as you want with different settings depending on your needs.

Now you can poll Active Directory permanently (normally using crontab) runing something like this:
```php
$poller->poll();
``` 

There is also console command that represents convenient way to run pollers with pretty output and additional options
if you use use [symfony/console](https://github.com/symfony/console):
```php
// bin/console (do not forget #!/usr/bin/env php at very first line)
// create poller collection
$pollerCollection = new \Gtt\ADPoller\PollerCollection();
// Add poller to collection:
$pollerCollection->addPoller($poller);
// create application and command
$application = new \Symfony\Component\Console\Application();
$application->add(new \Gtt\ADPoller\Command\PollCommand($pollerCollection));
$application->run();

```
And put into crontab command to run all pollers:
```
php bin/console gtt:pollers:run
```
or concrete one:
```
php bin/console gtt:pollers:run --poller=mypoller
```

Framework integration
=====================
There is a [gtt/ad-poller-bundle](https://github.com/GlobalTradingTechnologies/ad-poller-bundle) which integrates component in Symfony2+ ecosystem

Testing
=======
 
To run library test suite just clone the repository and execute the following inside:
```
composer install && ./vendor/bin/phpunit
```
