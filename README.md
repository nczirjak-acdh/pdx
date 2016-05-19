# PDX

[![Latest Stable Version](https://img.shields.io/packagist/v/Islandora/PDX.svg?style=flat-square)](https://packagist.org/packages/islandora/PDX)
[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%205.5-8892BF.svg?style=flat-square)](https://php.net/)
[![Downloads](https://img.shields.io/packagist/dt/islandora/PDX.svg?style=flat-square)](https://packagist.org/packages/islandora/PDX)
[![Build Status](https://travis-ci.org/Islandora-CLAW/PDX.svg?branch=master)](https://travis-ci.org/Islandora-CLAW/PDX)

This is a top level container for the various PCDM related Islandora CLAW microservices. It allows you to mount the various endpoints at one port on one machine and makes a development vagrant/docker configuration easier to produce.

## Requirements


* PHP 5.5+
* [Composer](https://getcomposer.org/)
* [Chullo](https://github.com/Islandora-CLAW/chullo)
* [Crayfish](https://github.com/Islandora-CLAW/Crayfish)
* [Fedora 4](https://github.com/fcrepo4/fcrepo4)
* A triplestore (i.e. [Blazegraph](https://www.blazegraph.com/download/), [Fuseki](https://jena.apache.org/documentation/fuseki2/), etc)

## Installation

You will need to copy the configuration file [_example.settings.yml_](config/example.settings.yml) to either **settings.yml** or **settings.dev.yml** (if $app['debug'] = TRUE) and change any required settings.

You can run just this service using PHP by executing 

```
php -S localhost:<some port> -t src/ src/index.php
```
from this directory to start it running.

## Services

This mounts all the various individual microservices under the `/islandora` URL, so you currently have access to 

* CollectionService at `/islandora/collection`

See the individual services for more information on their endpoints.

### CollectionService

This an Islandora PHP Microservice to create PCDM:Collections and add/remove PCDM:Objects to a PCDM:Collection.

#### Services

The CollectionService provides the following endpoints for HTTP requests. 

**Note**: The UUID is of the form `18c67794-366c-a6d9-af13-b3464a1fb9b5`

1. POST to `/collection`

    for creating a new PCDM:Collection at the root level

2. POST to `/collection/{uuid}`

    for creating a new PCDM:Collection as a child of resource {uuid}
    
2. POST to `/collection/{uuid}/member/{member}`

    for adding the resource identifier by the UUID {member} to the collection identified by the UUID {uuid}
    
2. DELETE to `/collection/{uuid}/member/{member}`

    for removing the resource identifier by the UUID {member} from the collection identified by the UUID {uuid}


## Sponsors

* UPEI
* discoverygarden inc.
* LYRASIS
* McMaster University
* University of Limerick
* York University
* University of Manitoba
* Simon Fraser University
* PALS
* American Philosophical Society
* common media inc.

## Maintainers

* [Jared Whiklo](https://github.com/whikloj)
* [Diego Pino](https://github.com/diegopino)
* [Nick Ruest](https://github.com/ruebot)

## License

[MIT](https://opensource.org/licenses/MIT)
