UserlandSession
===============

.. image:: https://travis-ci.org/mrclay/UserlandSession.png?branch=master
  :target: https://travis-ci.org/mrclay/UserlandSession

UserlandSession is an HTTP cookie-based session components implemented in plain PHP, allowing it to be used
concurrently with--and completely independent of--existing native sessions. This makes it handy for bridging
session state across multiple PHP apps with incompatible sessions.

- Loosely-coupled components that introduce no global state (except headers)
- Uses PHP's `SessionHandlerInterface`, so you can re-use existing 3rd-party handlers, even in PHP 5.3!
- Session data is only accessible via the object instead of a global

.. code-block:: php

    // create a files-based session, directory sniffed from session.save_path
    $session = \UserlandSession\SessionBuilder::instance()->build();
    $session->start();

    // use public $session->data array property...
    $session->data['foo'] = 'bar';

    // ...or use set/get()
    $session->set('foo', 'bar');

    $session->writeClose(); // ...or let destructor do this

Handlers
--------

The save handler interface is PHP's `SessionHandlerInterface` (provided for PHP 5.3), and handlers
`FileHandler` and `PdoHandler` are included.

Feel free to use your own save handler class, or use these as handlers for native sessions!

Creating a Session
------------------

Easy ways to get a files-based session:

.. code-block:: php

    // from script (save path sniffed from session.save_path)
    $session = (require 'path/to/UserlandSession/scripts/get_file_session.php');

    // using builder (here we set the session name to MYSESS)
    $session = SessionBuilder::instance()
        ->setSavePath('/tmp')
        ->setName('MYSESS')
        ->build();

File Storage Options
--------------------

.. code-block:: php

    // turn off file locking
    $session = SessionBuilder::instance()
        ->setFileLocking(false)
        ->build();

Using PDO
---------

.. code-block:: php

    // pre-existing PDO connection
    $session = SessionBuilder::instance()
        ->setPdo($myConnection)
        ->setTable('userland_sessions')
        ->build();

    // or if you want it to connect for you when needed:
    $session = SessionBuilder::instance()
        ->setDbCredentials(array(
            'dsn' => 'mysql:host=localhost;dbname=ulsess;charset=UTF8',
            'username' => 'fred',
            'password' => 'password1',
        ))
        ->setTable('userland_sessions')
        ->build();

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

Using PHP 5.4-style session handler objects in PHP 5.3

.. code-block:: php

    UserlandSession\Util\Php53Adapter::setSaveHandler(new FileHandler());
    session_start();

License
-------

MIT. See LICENSE.
