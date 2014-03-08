# UserlandSession

UserlandSession is an HTTP cookie-based session implemented in plain PHP, allowing it to be used concurrently with--and
completely independent of--existing native sessions. This makes it handy for bridging session state across
multiple PHP apps with incompatible sessions.

The components are loosely-coupled and introduce no global state (except headers), and the API is similar to
native sessions except access to the session data is only via the single object instead of a superglobal.

The storage interface is very similar to PHP 5.4's SessionHandlerInterface.

## License

MIT. See LICENSE.
