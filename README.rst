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

    // use public $session->data array property...
    $session->data['foo'] = 'bar';

    // ...or use set/get()
    $session->set('foo', 'bar');

    $session->writeClose(); // ...or let destructor do this

Storage
-------

Adapters `FileStorage` and `PdoStorage` are included.

The storage interface is currently similar but not identical to PHP 5.4's `SessionHandlerInterface`. I decided
that it was preferable for the storage object to hold the session name. I may decide to change this to share
the same interface as native sessions.

Creating a Session
------------------

Easy ways to get a files-based session with directory sniffed from session.save_path:

.. code-block:: php

    // from script
    $session = (require 'path/to/UserlandSession/scripts/get_file_session.php');

    // using factory
    $session = Session::factory();

File Storage Options
--------------------

.. code-block:: php

    // creates storage for a session with name ULSESS
    $storage = new FileStorage('ULSESS', array(
        'path' => '/storage/location',
        'flock' => false, // turn off file locking
    ));
    $session = new Session($storage);

Using PDO
---------

.. code-block:: php

    // pre-existing PDO connection
    $storage = new PdoStorage('ULSESS', array(
        'table' => 'userland_sessions',
        'pdo' => $myPdoConnection,
    ));
    $session = new Session($storage);

    // or if you want it to connect for you when needed:
    $storage = new PdoStorage('ULSESS', array(
        'table' => 'userland_sessions',
        'dsn' => "mysql:host=localhost;dbname=ulsess;charset=UTF8",
        'username' => 'username',
        'password' => 'password1',
    ));
    $session = new Session($storage);


Extras
------

You can check for data matching the client's cookie without starting the session:

.. code-block:: php

    if ($session->sessionLikelyExists()) {
        $session->start();
        // use session
    } else {
        // don't start if we don't need to
    }

Simpler cookie removal:

.. code-block:: php

    $session->removeCookie();

    // or specify true when destroying the session
    $session->destroy(true);

License
-------

MIT. See LICENSE.
