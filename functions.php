<?php

use Swoole\Http\Request;
use Swoole\Http\Response;

// convenience functions to handle requests

function handleIncomingRequest(\Redis $redis, Request $request, Response $response): bool
{
   /**
     * Spec:
     * requests must be POST requests
     * URI will be treated as the event name (e.g. onusercreate) case insensitive
     * requests may have a trailling forward slash
     * must have authentication token 'auth' in request header
     * 
     * Response codes:
     * 202 (Accepted) response with a token to identify this specific event in the body
     * 
     * 400 if we cannot validate the incoming data, or if any part of the data is missing (reason will be explained in body)
     * 401 if auth token is missing or not valid (e.g. broken or unencryptable)
     * 403 if the auth token is valid but the token is not permitted to raise the event it's trying to
     * 404 if we decide to validate event names in the future
     * 405 if the request is not a POST
     * 
     * 500 if we couldn't add the event to the queue
     */

    // only allow post requests
    if($request->getMethod() !== 'POST')
    {
        echo date('Y-m-d H:i:s', time()) . ' :: Invalid request method: ' . $request->getMethod() . "\n";
        sendInvalidRequestMethodResponse($request, $response);
        return true;
    }
    
    // authenticate user
    $authToken = $request->header['auth'] ?? '';
    if(!$authToken)
    {
        echo date('Y-m-d H:i:s', time()) . ' :: No auth token in request ' . "\n";
        sendNoAuthTokenResponse($request,$response);
        return true;
    }

    // decrypt auth token, it should contain both "username" and token
    $token = base64_decode($authToken);
    // a token is only well formed if it contains a single dot character
    if(substr_count($token, '.') !== 1)
    {
        echo date('Y-m-d H:i:s', time()) . ' :: Malformed auth token ' . "\n";
        sendMalformedAuthTokenResponse($request,$response);
        return true;
    }

    [$user, $pass] = explode('.' , $token);

    // TODO: use repository to store user/password combinations
    if($user !== 'client')
    {
        echo date('Y-m-d H:i:s', time()) . ' :: Unauthorised user ' . "\n";
        sendUnknownUserResponse($request,$response);
        return true;
    }

    if($pass !== CLIENT_PASSWORD)
    {
        echo date('Y-m-d H:i:s', time()) . ' :: Unauthorised user ' . "\n";
        sendInvalidPasswordResponse($request,$response);
        return true;
    }

    // now we need to know the requested uri
    $eventName = trim($request->server['request_uri'], '/' );

    // TODO change this to validate the event name
    if(!$eventName)
    {
        echo date('Y-m-d H:i:s', time()) . " :: invalid event name received: \n";
        sendInvalidEventNameResponse($request,$response);
        return true;
    }

    // TODO add event name with expiration date to set of recently used events

    try {
        // suppress warning on json_decode, we'll catch an error after
        $eventPayload = @json_decode($request->getContent(), false, 512, JSON_THROW_ON_ERROR);

    } catch(\Exception $e) {
        
        echo date('Y-m-d H:i:s', time()) . ' :: invalid JSON data received: ' . $e->getMessage() . "\n";
        sendInvalidJSONResponse($request,$response);
        return true;
    }

    // we store the event name in the event data so we don't need to worry about finding the name of the queue we fetch it from later
    $event = [
        'name' => $eventName,
        'payload' => $eventPayload
    ];

    if(!$queueLength = $redis->rpush(REDIS_QUEUE_PREFIX . $eventName, json_encode($event)))
    {
        echo date('Y-m-d H:i:s', time()) . " :: Error adding event to queue\n";
        sendUnableToAddEventToQueueResponse($request,$response);
        return true;
    }

    // on success, send a 202 response with text/plain body with confirmation message
    echo date('Y-m-d H:i:s', time()) . " :: $eventName event received, $queueLength items in event queue\n";
    $response->status(202);
    $response->header('content-type', 'text/plain');
    $response->end('Request received');
    return true;
}

function sendInvalidRequestMethodResponse(Request $request, Response $response)
{
   $response->status(405);
   $response->header('content-type', 'text/plain');
   $response->end('Request method not acceptable: ' . $request->getMethod());
}

function sendNoAuthTokenResponse(Request $request, Response $response)
{
   $response->status(401);
   $response->header('content-type', 'text/plain');
   $response->end('Not authorised (no auth token)');
}

function sendMalformedAuthTokenResponse(Request $request, Response $response)
{
   $response->status(401);
   $response->header('content-type', 'text/plain');
   $response->end('Not authorised (malformed auth token)');
}

function sendUnknownUserResponse(Request $request, Response $response)
{
   $response->status(403);
   $response->header('content-type', 'text/plain');
   $response->end('Not authorised (unknown user)');
}

function sendInvalidPasswordResponse(Request $request, Response $response)
{
   $response->status(403);
   $response->header('content-type', 'text/plain');
   $response->end('Not authorised (invalid password)');
}

function sendInvalidEventNameResponse(Request $request, Response $response)
{
   $response->status(404);
   $response->header('content-type', 'text/plain');
   $response->end('Invalid event name received');
}

function sendInvalidJSONResponse(Request $request, Response $response)
{
   $response->status(400);
   $response->header('content-type', 'text/plain');
   $response->end('Invalid JSON payload');
}

function sendUnableToAddEventToQueueResponse(Request $request, Response $response)
{
   $response->status(500);
   $response->header('content-type', 'text/plain');
   $response->end('Unable to add event data to queue');
}
