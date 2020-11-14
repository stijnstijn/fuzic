<?php
/**
 * Session model
 *
 * @package Fuzic-site
 */
namespace Fuzic\Models;

use Fuzic\Lib;


/**
 * Session model
 */
class Session extends Lib\Model
{
    const TABLE = 'sessions';
    const IDFIELD = 'id';
    const LABEL = 'id';
    const HIDDEN = 1;

    /**
     * Get sessions with at least one datapoint between given start and finish time
     *
     * @param            $start  int  Start timestamp
     * @param            $end    int End timestamp
     * @param bool|false $game
     *
     * @return array
     */
    public static function get_between($start, $end, $game = false) {
        global $db;

        $start = intval($start);
        $end = intval($end);

        $game_bit = $game ? "game = ".$db->escape($game).' AND ' : '';
        $sessions = $db->fetch_all("SELECT * FROM ".static::TABLE." WHERE ".$game_bit."((start < ".$start." AND end > ".$start.") OR (start < ".$end." AND end > ".$end.") OR (start > ".$start." AND end < ".$end.")) ORDER BY start ASC");

        return $sessions;
    }


    /**
     * Get events this stream has broadcasted
     *
     * @return  array   Events
     *
     * @access  public
     */
    public function get_events($game = ACTIVE_GAME) {
        global $db;
        $events = EventStream::find([
            'where' => ['stream = ? AND ( start < ? AND end > ? )' => [$this->get('stream'), $this->get('end'), $this->get('start')]],
            'mapping_function' => function($a) { return $a['event']; }
        ]);

        return Event::find([
            'where' => ['game = ? AND '.Event::IDFIELD.' IN ('.implode(',', array_map(array($db, 'escape'), $events)).')' => [$game]],
            'order_by' => 'start',
            'order' => 'DESC'
        ]);
    }

    /**
     * Get canonical URL referring to this item
     *
     * @return  string
     *
     * @access  public
     */
    public function get_url() {
        //try to make a fancy friendly URL based on class data
        if (defined(get_class($this).'::HUMAN_NAME')) {
            $cat = strtolower(static::HUMAN_NAME);

            //if that fails just use the class name
        } else {
            $cat = strtolower(get_class($this));
            $cat = explode('\\', $cat);
            $cat = array_pop($cat).'s';
        }

        return '/'.$cat.'/'.$this->get_ID().'/';
    }

    /**
     * Create URL based on supplied data
     *
     * For use in static contexts
     *
     * @param    array $data Data to build the URL from.
     *
     * @return  string  Constructed URL
     *
     * @access  public
     */
    public static function build_url($data) {
        $class = strtolower(get_called_class());
        if (defined($class.'::HUMAN_NAME')) {
            $cat = strtolower(constant($class.'::HUMAN_NAME'));
        } else {
            $cat = strtolower($class);
            $cat = explode('\\', $cat);
            $cat = array_pop($cat).'s';
        }
        return '/'.$cat.'/'.$data[static::IDFIELD].'/';
    }
}