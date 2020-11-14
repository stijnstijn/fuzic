<?php
/**
 * Gather information about broadcasting streams from Streaming services and TeamLiquid
 *
 * @package Fuzic
 */
namespace Fuzic\Crawler;

chdir(dirname(__FILE__));
require '../init.php';

spl_autoload_register(auto_loader('Crawler', 'class'));

new StreamChecker($db, $cache);