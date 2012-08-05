CHANGELOG
=========

* 2.8.3 (07-30-2012)

    * Bug: Fixed a case where empty POST requests were sent as GET requests
    * Bug: Fixed a bug in ExponentialBackoffPlugin that caused fatal errors when retrying an EntityEnclosingRequest that does not have a body
    * Bug: Setting the response body of a request to null after completing a request, not when setting the state of a request to new
    * Added multiple inheritance to service description commands
    * Added an ApiCommandInterface and added ``getParamNames()`` and ``hasParam()``
    * Removed the default 2mb size cutoff from the Md5ValidatorPlugin so that it now defaults to validating everything
    * Changed CurlMulti::perform to pass a smaller timeout to CurlMulti::executeHandles

* 2.8.2 (07-24-2012)

    * Bug: Query string values set to 0 are no longer dropped from the query string
    * Bug: A Collection object is no longer created each time a call is made to ``Guzzle\Service\Command\AbstractCommand::getRequestHeaders()``
    * Bug: ``+`` is now treated as an encoded space when parsing query strings
    * QueryString and Collection performance improvements
    * Allowing dot notation for class paths in filters attribute of a service descriptions

* 2.8.1 (07-16-2012)

    * Loosening Event Dispatcher dependency
    * POST redirects can now be customized using CURLOPT_POSTREDIR

* 2.8.0 (07-15-2012)

    * BC: Guzzle\Http\Query
        * Query strings with empty variables will always show an equal sign unless the variable is set to QueryString::BLANK (e.g. ?acl= vs ?acl)
        * Changed isEncodingValues() and isEncodingFields() to isUrlEncoding()
        * Changed setEncodeValues(bool) and setEncodeFields(bool) to useUrlEncoding(bool)
        * Changed the aggregation functions of QueryString to be static methods
        * Can now use fromString() with querystrings that have a leading ?
    * cURL configuration values can be specified in service descriptions using ``curl.`` prefixed parameters
    * Content-Length is set to 0 before emitting the request.before_send event when sending an empty request body
    * Cookies are no longer URL decoded by default
    * Bug: URI template variables set to null are no longer expanded

* 2.7.2 (07-02-2012)

    * BC: Moving things to get ready for subtree splits. Moving Inflection into Common. Moving Guzzle\Http\Parser to Guzzle\Parser.
    * BC: Removing Guzzle\Common\Batch\Batch::count() and replacing it with isEmpty()
    * CachePlugin now allows for a custom request parameter function to check if a request can be cached
    * Bug fix: CachePlugin now only caches GET and HEAD requests by default
    * Bug fix: Using header glue when transferring headers over the wire
    * Allowing deeply nested arrays for composite variables in URI templates
    * Batch divisors can now return iterators or arrays

* 2.7.1 (06-26-2012)

    * Minor patch to update version number in UA string
    * Updating build process

* 2.7.0 (06-25-2012)

    * BC: Inflection classes moved to Guzzle\Inflection. No longer static methods. Can now inject custom inflectors into classes.
    * BC: Removed magic setX methods from commands
    * BC: Magic methods mapped to service description commands are now inflected in the command factory rather than the client __call() method
    * Verbose cURL options are no longer enabled by default. Set curl.debug to true on a client to enable.
    * Bug: Now allowing colons in a response start-line (e.g. HTTP/1.1 503 Service Unavailable: Back-end server is at capacity)
    * Guzzle\Service\Resource\ResourceIteratorApplyBatched now internally uses the Guzzle\Common\Batch namespace
    * Added Guzzle\Service\Plugin namespace and a PluginCollectionPlugin
    * Added the ability to set POST fields and files in a service description
    * Guzzle\Http\EntityBody::factory() now accepts objects with a __toString() method
    * Adding a command.before_prepare event to clients
    * Added BatchClosureTransfer and BatchClosureDivisor
    * BatchTransferException now includes references to the batch divisor and transfer strategies
    * Fixed some tests so that they pass more reliably
    * Added Guzzle\Common\Log\ArrayLogAdapter

* 2.6.6 (06-10-2012)

    * BC: Removing Guzzle\Http\Plugin\BatchQueuePlugin
    * BC: Removing Guzzle\Service\Command\CommandSet
    * Adding generic batching system (replaces the batch queue plugin and command set)
    * Updating ZF cache and log adapters and now using ZF's composer repository
    * Bug: Setting the name of each ApiParam when creating through an ApiCommand
    * Adding result_type, result_doc, deprecated, and doc_url to service descriptions
    * Bug: Changed the default cookie header casing back to 'Cookie'

* 2.6.5 (06-03-2012)

    * BC: Renaming Guzzle\Http\Message\RequestInterface::getResourceUri() to getResource()
    * BC: Removing unused AUTH_BASIC and AUTH_DIGEST constants from
    * BC: Guzzle\Http\Cookie is now used to manage Set-Cookie data, not Cookie data
    * BC: Renaming methods in the CookieJarInterface
    * Moving almost all cookie logic out of the CookiePlugin and into the Cookie or CookieJar implementations
    * Making the default glue for HTTP headers ';' instead of ','
    * Adding a removeValue to Guzzle\Http\Message\Header
    * Adding getCookies() to request interface.
    * Making it easier to add event subscribers to HasDispatcherInterface classes. Can now directly call addSubscriber()

* 2.6.4 (05-30-2012)

    * BC: Cleaning up how POST files are stored in EntityEnclosingRequest objects. Adding PostFile class.
    * BC: Moving ApiCommand specific functionality from the Inspector and on to the ApiCommand
    * Bug: Fixing magic method command calls on clients
    * Bug: Email constraint only validates strings
    * Bug: Aggregate POST fields when POST files are present in curl handle
    * Bug: Fixing default User-Agent header
    * Bug: Only appending or prepending parameters in commands if they are specified
    * Bug: Not requiring response reason phrases or status codes to match a predefined list of codes
    * Allowing the use of dot notation for class namespaces when using instance_of constraint
    * Added any_match validation constraint
    * Added an AsyncPlugin
    * Passing request object to the calculateWait method of the ExponentialBackoffPlugin
    * Allowing the result of a command object to be changed
    * Parsing location and type sub values when instantiating a service description rather than over and over at runtime

* 2.6.3 (05-23-2012)

    * [BC] Guzzle\Common\FromConfigInterface no longer requires any config options.
    * [BC] Refactoring how POST files are stored on an EntityEnclosingRequest. They are now separate from POST fields.
    * You can now use an array of data when creating PUT request bodies in the request factory.
    * Removing the requirement that HTTPS requests needed a Cache-Control: public directive to be cacheable.
    * [Http] Adding support for Content-Type in multipart POST uploads per upload
    * [Http] Added support for uploading multiple files using the same name (foo[0], foo[1])
    * Adding more POST data operations for easier manipulation of POST data.
    * You can now set empty POST fields.
    * The body of a request is only shown on EntityEnclosingRequest objects that do not use POST files.
    * Split the Guzzle\Service\Inspector::validateConfig method into two methods. One to initialize when a command is created, and one to validate.
    * CS updates

* 2.6.2 (05-19-2012)

    * [Http] Better handling of nested scope requests in CurlMulti.  Requests are now always prepares in the send() method rather than the addRequest() method.

* 2.6.1 (05-19-2012)

    * [BC] Removing 'path' support in service descriptions.  Use 'uri'.
    * [BC] Guzzle\Service\Inspector::parseDocBlock is now protected. Adding getApiParamsForClass() with cache.
    * [BC] Removing Guzzle\Common\NullObject.  Use https://github.com/mtdowling/NullObject if you need it.
    * [BC] Removing Guzzle\Common\XmlElement.
    * All commands, both dynamic and concrete, have ApiCommand objects.
    * Adding a fix for CurlMulti so that if all of the connections encounter some sort of curl error, then the loop exits.
    * Adding checks to EntityEnclosingRequest so that empty POST files and fields are ignored.
    * Making the method signature of Guzzle\Service\Builder\ServiceBuilder::factory more flexible.

* 2.6.0 (05-15-2012)

    * [BC] Moving Guzzle\Service\Builder to Guzzle\Service\Builder\ServiceBuilder
    * [BC] Executing a Command returns the result of the command rather than the command
    * [BC] Moving all HTTP parsing logic to Guzzle\Http\Parsers. Allows for faster C implementations if needed.
    * [BC] Changing the Guzzle\Http\Message\Response::setProtocol() method to accept a protocol and version in separate args.
    * [BC] Moving ResourceIterator* to Guzzle\Service\Resource
    * [BC] Completely refactored ResourceIterators to iterate over a cloned command object
    * [BC] Moved Guzzle\Http\UriTemplate to Guzzle\Http\Parser\UriTemplate\UriTemplate
    * [BC] Guzzle\Guzzle is now deprecated
    * Moving Guzzle\Common\Guzzle::inject to Guzzle\Common\Collection::inject
    * Adding Guzzle\Version class to give version information about Guzzle
    * Adding Guzzle\Http\Utils class to provide getDefaultUserAgent() and getHttpDate()
    * Adding Guzzle\Curl\CurlVersion to manage caching curl_version() data
    * ServiceDescription and ServiceBuilder are now cacheable using similar configs
    * Changing the format of XML and JSON service builder configs.  Backwards compatible.
    * Cleaned up Cookie parsing
    * Trimming the default Guzzle User-Agent header
    * Adding a setOnComplete() method to Commands that is called when a command completes
    * Keeping track of requests that were mocked in the MockPlugin
    * Fixed a caching bug in the CacheAdapterFactory
    * Inspector objects can be injected into a Command object
    * Refactoring a lot of code and tests to be case insensitive when dealing with headers
    * Adding Guzzle\Http\Message\HeaderComparison for easy comparison of HTTP headers using a DSL
    * Adding the ability to set global option overrides to service builder configs
    * Adding the ability to include other service builder config files from within XML and JSON files
    * Moving the parseQuery method out of Url and on to QueryString::fromString() as a static factory method.

* 2.5.0 (05-08-2012)

    * Major performance improvements
    * [BC] Simplifying Guzzle\Common\Collection.  Please check to see if you are using features that are now deprecated.
    * [BC] Using a custom validation system that allows a flyweight implementation for much faster validation. No longer using Symfony2 Validation component.
    * [BC] No longer supporting "{{ }}" for injecting into command or UriTemplates.  Use "{}"
    * Added the ability to passed parameters to all requests created by a client
    * Added callback functionality to the ExponentialBackoffPlugin
    * Using microtime in ExponentialBackoffPlugin to allow more granular backoff stategies.
    * Rewinding request stream bodies when retrying requests
    * Exception is thrown when JSON response body cannot be decoded
    * Added configurable magic method calls to clients and commands.  This is off by default.
    * Fixed a defect that added a hash to every parsed URL part
    * Fixed duplicate none generation for OauthPlugin.
    * Emitting an event each time a client is generated by a ServiceBuilder
    * Using an ApiParams object instead of a Collection for parameters of an ApiCommand
    * cache.* request parameters should be renamed to params.cache.*
    * Added the ability to set arbitrary curl options on requests (disable_wire, progress, etc). See CurlHandle.
    * Added the ability to disable type validation of service descriptions
    * ServiceDescriptions and ServiceBuilders are now Serializable