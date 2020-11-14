<?php
/**
 * Update short event names for all events
 *
 * Use when `Event::get_short_name()`  has changed
 */
namespace Fuzic\Tools;

use Fuzic\Models;


chdir(dirname(__FILE__));
require '../init.php';

$events = Models\Event::find(['return' => 'object']);

foreach ($events as $event) {
    $event->set('short_name', Models\Event::get_short_name($event->get('name')));
    $event->update();
}