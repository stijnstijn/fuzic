<?php
/**
 * Delete old sessions
 */
namespace Fuzic\Upkeep;

use Fuzic\Models;


chdir(dirname(__FILE__));
require '../init.php';

$db->query("DELETE FROM ".Models\UserSession::TABLE." WHERE timestamp < FROM_UNIXTIME(".(time() - 1800).")");