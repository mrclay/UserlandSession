<?php
/**
 * Two Shibalike sessions and one native
 */

use UserlandSession\Session;
use UserlandSession\Storage\FileStorage;

require __DIR__ . '/../autoload.php';

$incrementI = function (Session $sess) {
    $sess->set('i', $sess->get('i', 0) + 1);
};

$msgs = array();

$sess1 = Session::factory();
$sess1->cache_limiter = Session::CACHE_LIMITER_NONE;
$sess1->gc_divisor = 3;
$sess1->start();

$sess1Storage = $sess1->getStorage();
/* @var FileStorage $sess1Storage */

$msgs['sess1']['name'] = $sess1Storage->getName();
$msgs['sess1']['path'] = $sess1Storage->getPath();
$msgs['sess1']['id'] = $sess1->id();

$sess1->set('i', $sess1->get('i', 0) + 1);

$msgs['sess1']['counter'] = $sess1->data['i'];


session_start();
$msgs['native']['name'] = session_name();
$msgs['native']['path'] = session_save_path();
$msgs['native']['id'] = session_id();
if (isset($_SESSION['i'])) {
    $_SESSION['i']++;
} else {
    $_SESSION['i'] = 21;
}
$msgs['native']['counter'] = $_SESSION['i'];


$sess2 = Session::factory();
$sess2->cache_limiter = Session::CACHE_LIMITER_NONE;
$sess2->start();

$sess2Storage = $sess2->getStorage();
/* @var FileStorage $sess2Storage */

$msgs['sess2']['name'] = $sess2Storage->getName();
$msgs['sess2']['path'] = $sess2Storage->getPath();
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
