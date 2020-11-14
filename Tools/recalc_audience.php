<?php
namespace Fuzic\Tools;

use Fuzic\Models;


require '../init.php';

$audience = array('sc2' => array(), 'hearthstone' => array(), 'bw' => array(), 'overwatch' => array(), 'hearthstone' => array());
$wayback = time() - 86400;

$sessions = Models\Session::find(['where' => ['end >= ?' => [$wayback]], 'join' => ['table' => Models\SessionData::TABLE, 'on' => [Models\SessionData::IDFIELD, Models\Session::IDFIELD], 'fields' => ['datapoints']]]);

foreach ($sessions as $session) {
    $datapoints = json_decode($session['datapoints'], true);
    foreach ($datapoints as $time => $viewers) {
        $time += $session['start'];
        if (isset($audience[$session['game']][$time])) {
            $audience[$session['game']][$time] += $viewers;
        } else {
            $audience[$session['game']][$time] = $viewers;
        }
    }
}

$datapoints = Models\Datapoint::find(['where' => ['time >= ?' => [$wayback]]]);

foreach ($datapoints as $datapoint) {

    if (isset($audience[$datapoint['game']][$datapoint['time']])) {
        $audience[$datapoint['game']][$datapoint['time']] += $datapoint['viewers'];
    } else {
        $audience[$datapoint['game']][$datapoint['time']] = $datapoint['viewers'];
    }
}

foreach($audience as $game => $viewers) {
    ksort($audience[$game]);
}

$db->query("DELETE FROM audience WHERE time >= ".$wayback);

$db->start_transaction();

foreach ($audience as $game => $viewership) {
    foreach ($viewership as $time => $viewers) {
        $db->insert('audience', ['time' => $time, 'viewers' => $viewers, 'game' => $game]);
    }
}

$db->commit();