# event-receiver
A PHP Swoole HTTP server that receives input in the form of "events" and stores them in Redis queues

## requirements
The code here has been written with PHP 8 in mind, and while it is probably compatible with PHP 5.6 or 7, it's not been tested against those versions.

### notes
Currently, a password must be provided in the `auth` header, that password is set to "password" in config.php.  Not very secure, but fine for running on a local host for demonstration purposes.

### todo
1. Add support for JWT tokens for authorisation
2. Add role-based access for raising events
3. Flesh out this readme file to include usage and examples
