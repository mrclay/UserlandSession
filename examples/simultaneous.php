<?php
/**
 * Two Shibalike sessions and one native
 */

use UserlandSession\SessionBuilder;
use UserlandSession\Session;
use UserlandSession\Handler\FileHandler;
use UserlandSession\Util\Php53Adapter;

require __DIR__ . '/../autoload.php';

$path = sys_get_temp_dir();

$incrementI = function (Session $sess) {
    $sess->set('i', $sess->get('i', 0) + 1);
};

$msgs = array();

// First session: UserlandSession //////////////////////////////////////////////////////////////////

$sess1 = SessionBuilder::instance()->setName('ULSESS1')->setSavePath($path)->build();
$sess1->cache_limiter = Session::CACHE_LIMITER_NONE;
$sess1->gc_divisor = 3;
$sess1->start();

$msgs['sess1']['name'] = $sess1->getName();
$msgs['sess1']['id'] = $sess1->id();

$sess1->set('i', $sess1->get('i', 0) + 1);

$msgs['sess1']['counter'] = $sess1->data['i'];


// Second session: Native PHP Session //////////////////////////////////////////////////////////////

Php53Adapter::setSaveHandler(new FileHandler()); // custom save handler
session_save_path($path);
session_start();
$msgs['native']['name'] = session_name();
$msgs['native']['id'] = session_id();
if (isset($_SESSION['i'])) {
    $_SESSION['i']++;
} else {
    $_SESSION['i'] = 21;
}
$msgs['native']['counter'] = $_SESSION['i'];


// Third session: UserlandSession //////////////////////////////////////////////////////////////////

$sess2 = SessionBuilder::instance()->setName('ULSESS2')->setSavePath($path)->build();
$sess2->cache_limiter = Session::CACHE_LIMITER_NONE;
$sess2->start();

$msgs['sess2']['name'] = $sess2->getName();
$msgs['sess2']['id'] = $sess2->id();

$sess2->set('i', $sess2->get('i', 10) + 1);

$msgs['sess2']['counter'] = $sess2->data['i'];


$sess1->regenerateId(true);
$msgs['sess1']['new_id'] = $sess1->id();

session_regenerate_id(true);
$msgs['native']['new_id'] = session_id();

$sess2->regenerateId(true);
$msgs['sess2']['new_id'] = $sess2->id();

header('Content-Type: text/plain');
echo "Note three simultaneous sessions, including a native one.\n";
echo "All counters increment independently and IDs are regenerated on every request.\n\n";
var_export($msgs);
