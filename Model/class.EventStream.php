<?php
/**
 * Event stream link model
 *
 * @package Fuzic
 */
namespace Fuzic\Models;

use Fuzic\Lib;


/**
 * Event stream link model
 */
class EventStream extends Lib\Model
{
    const TABLE = 'event_streams';
    const IDFIELD = 'id';
    const LABEL = 'id';

    /**
     * Get event streans with at least one datapoint between given start and finish time
     *
     * @param            $start  int  Start timestamp
     * @param            $end    int End timestamp
     * @param bool|false $game
     *
     * @return array
     */
    public static function find_between($start, $end, $stream = false, $event = false) {
        global $db;

        $start = intval($start);
        $end = intval($end);

        $stream_bit = $stream ? "stream = ".$db->escape($stream).' AND ': '';
        $event_bit = $event ? "event = ".$db->escape($event).' AND ': '';

        $sessions = $db->fetch_all("SELECT * FROM ".static::TABLE." WHERE ".$stream_bit.$event_bit."((start < ".$start." AND end > ".$start.") OR (start < ".$end." AND end > ".$end.") OR (start > ".$start." AND end < ".$end."))");

        return $sessions;
    }
}