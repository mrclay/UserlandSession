UserlandSession
===============

.. image:: https://travis-ci.org/mrclay/UserlandSession.png?branch=master
  :target: https://travis-ci.org/mrclay/UserlandSession

UserlandSession is an HTTP cookie-based session implemented in plain PHP, allowing it to be used concurrently with--and
completely independent of--existing native sessions. This makes it handy for bridging session state across
multiple PHP apps with incompatible sessions.

The components are loosely-coupled and introduce no global state (except headers), and the API is similar to
native sessions except access to the session data is only via the single object instead of a superglobal.

.. code-block:: php

    // create a files-based session, directory sniffed from session.save_path
    $session = \UserlandSession\Session::factory();
    $session->start();

    // write data ($session->data is a plain old array property)
    $session->data['foo'] = 'bar';

    $session->writeClose(); // or let destructor do this

Storage
-------

Adapters `FileStorage` and `PdoStorage` are included.

The storage interface is currently similar but not identical to PHP 5.4's `SessionHandlerInterface`. I decided
that it was preferable for the storage object to hold the session name. I may decide to change this to share
the same interface as native sessions.

License
-------

MIT. See LICENSE.
