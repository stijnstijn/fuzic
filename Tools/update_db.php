<?php
namespace Fuzic\Tools;

use Fuzic\Models;
use Fuzic\Lib;
use Fuzic\Config;

/**
 * Rename a stream
 *
 * Changes stream ID and updates all referencing tables
 *
 * @param string $old  Old ID
 * @param string $new  New ID
 * @param object$db  Database interface
 */
function rename($old, $new, &$db) {
    foreach(array(Models\Datapoint::TABLE, Models\EventStream::TABLE, Models\Session::TABLE, 'ranking_alltime', 'ranking_month', 'ranking_month_e', 'ranking_week', 'ranking_week_e') as $table) {
        $db->query("UPDATE ".$db->escape_identifier($table)." SET stream = ".$db->escape($new)." WHERE stream = ".$db->escape($old));
    }

    $db->query("UPDATE ".$db->escape_identifier(Models\Match::TABLE)." SET player1 = ".$db->escape($new)." WHERE player1 = ".$db->escape($old));
    $db->query("UPDATE ".$db->escape_identifier(Models\Match::TABLE)." SET player2 = ".$db->escape($new)." WHERE player2 = ".$db->escape($old));
    $db->query("UPDATE ".$db->escape_identifier(Models\Stream::TABLE)." SET ".$db->escape_identifier(Models\Stream::IDFIELD)." = ".$db->escape($new)." WHERE ".$db->escape_identifier(Models\Stream::IDFIELD)." = ".$db->escape($old));
}

ini_set('memory_limit', '3G');
chdir(dirname(__FILE__));

require '../init.php';
$db = new Lib\mysqldb(Config::DB_PLACE, Config::DB_USER, Config::DB_PASSWD, 'fuzic');

//general updates
echo "\nChanging ID column names.";
$db->query("ALTER TABLE ".$db->escape_identifier(Models\Stream::TABLE)." CHANGE `eventid` `id` MEDIUMINT(8) UNSIGNED NOT NULL AUTO_INCREMENT;");
$db->query("ALTER TABLE ".$db->escape_identifier(Models\EventStream::TABLE)." CHANGE `linkid` `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT;");
$db->query("ALTER TABLE ".$db->escape_identifier(Models\Team::TABLE)." CHANGE `teamid` `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT;");
$db->query("ALTER TABLE ".$db->escape_identifier(Models\Session::TABLE)." CHANGE `sessionid` `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT;");
$db->query("ALTER TABLE ".$db->escape_identifier(Models\Stream::TABLE)." CHANGE `twitch_id` ".$db->escape_identifier(Models\Stream::IDFIELD)." VARCHAR(32) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL;");

//fix wrong afreeca stream IDs
echo "\nAdding prefix to wrongly-saved Afreeca stream IDs.\n";
$db->query("SET foreign_key_checks = 0");
$db->start_transaction();
$afreeca = $db->fetch_all("SELECT ".$db->escape_identifier(Models\Stream::IDFIELD).", remote_id FROM ".$db->escape_identifier(Models\Stream::TABLE)." WHERE provider = 'afreeca' AND ".$db->escape_identifier(Models\Stream::IDFIELD)." NOT LIKE 'af-%'");
echo count($afreeca).' streams to be updated.'."\n";
$i = 1;
foreach($afreeca as $stream) {
    rename($stream[Models\Stream::IDFIELD], $stream['remote_id'], $db);
    $pct = round(($i / count($afreeca)) * 100);
    $str = 'Updated '.str_pad($pct, '0', STR_PAD_LEFT).'% ('.$i++.' streams)';
    echo $str."\r";
}
$db->commit();
$db->query("SET foreign_key_checks = 1");


//stream table
echo "\nChanging stream table column names and order.";
$db->query("ALTER TABLE".$db->escape_identifier(Models\Stream::TABLE)." CHANGE `name` `remote_id` VARCHAR(32) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL;");
$db->query("ALTER TABLE ".$db->escape_identifier(Models\Stream::TABLE)." ADD `last_game2` VARCHAR(32) NOT NULL AFTER `remote_id`;");
$db->query("UPDATE ".$db->escape_identifier(Models\Stream::TABLE)." SET last_game2 = last_game");
$db->query("ALTER TABLE ".$db->escape_identifier(Models\Stream::TABLE)." DROP `last_game`;");
$db->query("ALTER TABLE ".$db->escape_identifier(Models\Stream::TABLE)." CHANGE `last_game2` `last_game` VARCHAR(32) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL;");


//Rename Twitch streams
echo "\nChanging IDs for Twitch streams.\n";
$db->query("SET foreign_key_checks = 0");

$twitch = $db->fetch_all("SELECT ".$db->escape_identifier(Models\Stream::IDFIELD).", `remote_id` FROM streams WHERE provider = 'twitch' AND ".$db->escape_identifier(Models\Stream::IDFIELD)." != ''");
echo count($twitch).' streams to be updated.'."\n";

$db->start_transaction();
$i = 1;
foreach($twitch as $stream) {
    rename($stream[Models\Stream::IDFIELD], $stream['remote_id'], $db);
    $pct = round(($i / count($twitch)) * 100);
    $str = 'Updated '.str_pad($pct, '0', STR_PAD_LEFT).'% ('.$i++.' streams)';
    echo $str."\r";
}

echo "\nDone. Committing queries.";
$db->commit();
$db->query("SET foreign_key_checks = 1");


//update names
echo "\nUpdating non-Twitch remote IDs.";
$db->query("UPDATE ".$db->escape_identifier(Models\Stream::TABLE)." SET `remote_id` = SUBSTRING(`remote_id` FROM 3) WHERE `provider` = 'afreeca'");
$db->query("UPDATE ".$db->escape_identifier(Models\Stream::TABLE)." SET `remote_id` = SUBSTRING(`remote_id` FROM 2) WHERE `provider` = 'azubu'");
$db->query("UPDATE ".$db->escape_identifier(Models\Stream::TABLE)." SET `remote_id` = SUBSTRING(`remote_id` FROM 2) WHERE `provider` = 'dailymotion'");
$db->query("UPDATE ".$db->escape_identifier(Models\Stream::TABLE)." SET `remote_id` = SUBSTRING(`remote_id` FROM 3) WHERE `provider` = 'dingit'");
$db->query("UPDATE ".$db->escape_identifier(Models\Stream::TABLE)." SET `remote_id` = SUBSTRING(`remote_id` FROM 3) WHERE `provider` = 'douyu'");
$db->query("UPDATE ".$db->escape_identifier(Models\Stream::TABLE)." SET `remote_id` = SUBSTRING(`remote_id` FROM 2) WHERE `provider` = 'goodgame'");
$db->query("UPDATE ".$db->escape_identifier(Models\Stream::TABLE)." SET `remote_id` = SUBSTRING(`remote_id` FROM 2) WHERE `provider` = 'hitbox'");
$db->query("UPDATE ".$db->escape_identifier(Models\Stream::TABLE)." SET `remote_id` = SUBSTRING(`remote_id` FROM 2) WHERE `provider` = 'livestream'");
$db->query("UPDATE ".$db->escape_identifier(Models\Stream::TABLE)." SET `remote_id` = SUBSTRING(`remote_id` FROM 2) WHERE `provider` = 'youtube'");

//check integrity
echo "\nChecking data integrity.\n";
echo $db->fetch_field("SELECT COUNT(*) FROM ".$db->escape_identifier(Models\Datapoint::TABLE)." WHERE stream NOT IN ( SELECT ".$db->escape_identifier(Models\Stream::IDFIELD)." FROM ".$db->escape_identifier(Models\Stream::TABLE).")")." orphaned datapoints\n";
echo $db->fetch_field("SELECT COUNT(*) FROM ".$db->escape_identifier(Models\EventStream::TABLE)." WHERE stream NOT IN ( SELECT ".$db->escape_identifier(Models\Stream::IDFIELD)." FROM ".$db->escape_identifier(Models\Stream::TABLE).")")." orphaned event stream links\n";
echo $db->fetch_field("SELECT COUNT(*) FROM ".$db->escape_identifier(Models\Session::TABLE)." WHERE stream NOT IN ( SELECT ".$db->escape_identifier(Models\Stream::IDFIELD)." FROM ".$db->escape_identifier(Models\Stream::TABLE).")")." orphaned sessions\n";
echo $db->fetch_field("SELECT COUNT(*) FROM ranking_alltime WHERE stream NOT IN ( SELECT ".$db->escape_identifier(Models\Stream::IDFIELD)." FROM ".$db->escape_identifier(Models\Stream::TABLE).")")." orphaned all-time rankings\n";
echo $db->fetch_field("SELECT COUNT(*) FROM ranking_month WHERE stream NOT IN ( SELECT ".$db->escape_identifier(Models\Stream::IDFIELD)." FROM ".$db->escape_identifier(Models\Stream::TABLE).")")." orphaned month rankings\n";
echo $db->fetch_field("SELECT COUNT(*) FROM ranking_month_e WHERE stream NOT IN ( SELECT ".$db->escape_identifier(Models\Stream::IDFIELD)." FROM ".$db->escape_identifier(Models\Stream::TABLE).")")." orphaned eventless month rankings\n";
echo $db->fetch_field("SELECT COUNT(*) FROM ranking_week WHERE stream NOT IN ( SELECT ".$db->escape_identifier(Models\Stream::IDFIELD)." FROM ".$db->escape_identifier(Models\Stream::TABLE).")")." orphaned week rankings\n";
echo $db->fetch_field("SELECT COUNT(*) FROM ranking_week_e WHERE stream NOT IN ( SELECT ".$db->escape_identifier(Models\Stream::IDFIELD)." FROM ".$db->escape_identifier(Models\Stream::TABLE).")")." orphaned eventless week rankings\n";

echo "\nDone.";
