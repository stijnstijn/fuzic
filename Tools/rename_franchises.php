<?php
/**
 * Rename franchises that lack a full name via commandline
 */
namespace Fuzic\Tools;

use Fuzic\Models;


chdir(dirname(__FILE__));
require '../init.php';

$franchises = $db->fetch_all("SELECT * FROM ".Franchise::TABLE." WHERE tag = real_name");
foreach ($franchises as $franchise) {
    $franchise = new Models\Franchise($franchise[Franchise::IDFIELD]);
    $events = Models\Event::find(['franchise' => $franchise->get_ID(), 'limit' => 3, 'order_by' => 'RAND()']);
    echo '---------------'."\n";
    echo 'Franchise tag: '.$franchise->get('tag')."\n";
    echo "Examples of events:\n";
    foreach ($events as $event) {
        echo '  '.$event['name']."\n";
    }
    echo "Franchise name (empty to skip:)";
    $cmd = trim(fgets(STDIN));
    if (!empty($cmd)) {
        $franchise->set('real_name', $cmd);
        $franchise->update();
    }
}