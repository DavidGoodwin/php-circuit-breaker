# Build Status

[![CI](https://github.com/DavidGoodwin/php-circuit-breaker/workflows/CI/badge.svg)](https://github.com/DavidGoodwin/php-circuit-breaker/actions)

# What is php-circuit-breaker

A component helping you gracefully handle outages and timeouts of external services (usually remote, 3rd party
services).

It is a library providing extremely easy to use circuit breaker component. It does not require external dependencies and
it has default storage
implementations for APCu and Memcached but can be extended multiple ways.

# Requirements

This library does not require any particular PHP framework, all you need is PHP 7.4 or higher.

# Motivation & Benefits

* Allow application to detect failures and adapt its behaviour without human intervention.
* Increase robustness of services by adding fail-safe functionality into modules.

# Installation

`composer require davidgoodwin/php-circuit-breaker`

## Use Case - Non-Critical Feature

* Your application has an Non-Critical Feature like: user tracking, stats, recommendations etc
* The optional feature uses remote service that causes outages of your application.
* You want to keep applicaton and core processes available when "Non-Critical Feature" fails.

Code of your application could look something like:

```PHP
$factory = new DavidGoodwin\CircuitBreaker\Factory();
$circuitBreaker = $factory->getSingleApcInstance(30, 300);

$userProfile = null;
if( $circuitBreaker->isAvailable("UserProfileService") ){
    try {
        $userProfile = $userProfileService->loadProfileOrWhatever();
        $circuitBreaker->reportSuccess("UserProfileService");
    } catch( UserProfileServiceConnectionException $e ){
        // network failed - report it as failure
        $circuitBreaker->reportFailure("UserProfileService");
    } catch( Exception $e ){
        // something went wrong but it is not service's fault, dont report as failure
    }
}
if( $userProfile === null ){
    // for example, show 'System maintenance, you cant login now.' message
    // but still let people buy as logged out customers.
}
````

## Use Case - Payment Gateway

* Web application depends on third party service (for example a payment gateway).
* Web application needs to keep track when 3rd party service is unavailable.
* Application can not become slow/unavailable, it has to tell user that features are limited or just hide them.
* Application uses circuit breaker before checkout page rendering and if particular payment gateway is unavailable
  payment option is hidden from the user.

As you can see that is a very powerful concept of selectively disabling feautres at runtime but still allowing the
core business processes to be uninterrupted.

Backend talking to the payment service could look like this:

```PHP
$factory = new DavidGoodwin\CircuitBreaker\Factory();
$circuitBreaker = $factory->getSingleApcInstance(30, 300);

try {
    // try to process the payment
    // then tell circuit breaker that it went well
    $circuitBreaker->reportSuccess("PaymentOptionOne");
} catch(SomePaymentConnectionException $e ){
    // If you get network error report it as failure
    $circuitBreaker->reportFailure("PaymentOptionOne");
} catch(Exception $e ){
    // in case of your own error handle it however it makes sense but
    // dont tell circuit breaker it was 3rd party service failure
}
````

Since you are recording failed and successful operations you can now use them in the front end as well
to hide payment options that are failing.

Frontend rendering the available payment options could look like this:

```PHP
$factory = new DavidGoodwin\CircuitBreaker\Factory();
$circuitBreaker = $factory->getSingleApcInstance(30, 300);

if ($circuitBreaker->isAvailable("PaymentOptionOne")) {
    // display the option
}
```

# Features

* Track multiple services through a single Circuit Breaker instance.
* Pluggable backend adapters, provided APCu and Memcached by default.
* Customisable service thresholds. You can define how many failures are necessary for service to be considered down.
* Customisable retry timeout. You do not want to disable the service forever. After provided timeout
  circuit breaker will allow a single process to attempt
* This fork includes the ability to execute a code block when a Service is modified to a failed state

# Tripping The Breaker

Code can be executed when the breaker is tripped by providing a `TrippedHandler` for each service.
The TrippedHandler is responsible for loggin your service outage.
The breaker will respond to 2 states: Unavailable, and Retry. Then the breaker trips for the first time it will send the
unavailable message, "Service No Longer Available", while after every retry attempt it will send, "Retrying Service"
Get/Set have been provided for these messages.

Here is an example of a `EmailHandler`

```PHP
use DavidGoodwin\CircuitBreaker\TrippedHandlerInterface;

class EmailHandler implements TrippedHandlerInterface
{
    protected $targetEmail = '';
    
    protected $headers = '';
    
    public function __construct($targetEmail)
    {
        $this->targetEmail = $targetEmail;
        $this->headers = 'From: xxx@xxx.ca' . "\r\n" .
            'Reply-To: xxx@xxx.ca' . "\r\n" .
            'X-Mailer: PHP/' . phpversion();
    }

    public function __invoke($serviceName, $count, $message)
    {
        mail($this->targetEmail, "Service Outage: " . $serviceName, $message, $this->headers);
    }
}
```

Here is how to register it. This adds a handler for "Database" service

```PHP
$circuitBreaker = $factory->getSingleApcInstance(5, 30);
$circuitBreaker->registerHandler("Database", new \Handler\EmailHandler("your_email@your_domain.com"));
```

# Performance Impact

Overhead of the Circuit Breaker is negligible.

APCu implementation takes roughly 0.0002s to perform isAvailable() and then reportSuccess() or reportFailure().

Memcache adapter is in range of 0.0005s when talking to the local memcached process.

The only potential performance impact is network connection time. If you chose to use remote memcached server or
implement your own custom StorageAdapter.

## Details

Before documentation gets updated you can read more on the concept of circuit breaker and
its implementation on my blog http://artur.ejsmont.org/blog/circuit-breaker

Some implementation details has changed but the core logic is still the same.

(Update) You can read my blog on what I do with this
package, http://bolt.tamingtheelephpant.com/page/circuit-breakers-failing-gracefully

## Unit Testing

`composer test`

or ...

`vendor/bin/phpunit tests`

## Author

* Artur Esjmont (https://github.com/ejsmont-artur) via http://artur.ejsmont.org
* Glenn Eggleton (https://github.com/geggleto)
* David Goodwin (https://github.com/DavidGoodwin)
