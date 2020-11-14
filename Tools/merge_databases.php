<?php
namespace Fuzic\Tools;

use Fuzic\Models;
use Fuzic\Lib;
use Fuzic\Config;

chdir(dirname(__FILE__));
require '../init.php';

$db = new Lib\mysqldb(Config::DB_PLACE, Config::DB_USER, Config::DB_PASSWD, 'fuzic');
$temp = new Lib\mysqldb(Config::DB_PLACE, Config::DB_USER, Config::DB_PASSWD, 'fuzic_temp');
$db->toggle_debug(false, true);
$temp->toggle_debug(false, true);
$db->start_transaction();

//streams
$streams = array_map(function($a) use ($db) { return $db->escape($a[Models\Stream::IDFIELD]); }, $db->fetch_all("SELECT ".$db->escape_identifier(Models\Stream::IDFIELD)." FROM ".$db->escape_identifier(Models\Stream::TABLE)));
$temp_streams = $temp->fetch_all("SELECT * FROM ".$db->escape_identifier(Models\Stream::TABLE)." WHERE ".$db->escape_identifier(Models\Stream::IDFIELD)." NOT IN (".implode(',', $streams).")");

echo count($temp_streams).' streams to be imported.'."\n";
foreach($temp_streams as $stream) {
    //Models\Stream::create($stream);
}

//datapoints
$datapoints = $temp->fetch_all("SELECT * FROM ".$db->escape_identifier(Models\Datapoint::TABLE));
echo count($datapoints).' datapoints to be imported.'."\n";
foreach($datapoints as $datapoint) {
    unset($datapoint[Models\Datapoint::IDFIELD]);
    //Models\Datapoint::create($datapoint);
}

//sessions
$sessions = $temp->fetch_all("SELECT * FROM ".$db->escape_identifier(Models\Session::TABLE));
echo count($sessions).' sessions to be imported.'."\n";
foreach($sessions as $session) {
    $data = $temp->fetch_single("SELECT * FROM ".$db->escape_identifier(Models\SessionData::TABLE)." WHERE ".$db->escape_identifier(Models\SessionData::IDFIELD)." = ".$db->escape($session[Models\Session::IDFIELD]));
    unset($session[Models\Session::IDFIELD]);
    /*$new = Models\Session::create($session);
    $data['sessionid'] = $new->get_ID();
    Models\SessionData::create($data);*/
}

$db->commit();