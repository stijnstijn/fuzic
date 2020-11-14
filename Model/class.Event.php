<?php
/**
 * Event model
 *
 * @package Fuzic
 */
namespace Fuzic\Models;

use Fuzic\Lib;


/**
 * Event model
 */
class Event extends Lib\Model
{
    const TABLE = 'events';
    const IDFIELD = 'id';
    const LABEL = 'name';

    /**
     * Get events with at least one datapoint between given start and finish time
     *
     * @param            $start  int  Start timestamp
     * @param            $end    int End timestamp
     * @param bool|false $game
     *
     * @return array
     */
    public static function find_between($start, $end, $game = false) {
        global $db;

        $start = intval($start);
        $end = intval($end);

        $game_bit = $game ? "game = ".$db->escape($game).' AND ' : '';
        $sessions = $db->fetch_all("SELECT * FROM ".static::TABLE." WHERE ".$game_bit."((start < ".$start." AND end > ".$start.") OR (start < ".$end." AND end > ".$end.") OR (start > ".$start." AND end < ".$end."))");

        return $sessions;
    }

    /**
     * Get streams for this event
     *
     * @return  array   Stream data
     *
     * @access  public
     */
    public function get_streams() {
        return EventStream::find([
            'event' => $this->get_ID(),
            'order_by' => 'viewers'
        ]);
    }

    /**
     * Get viewers at point in time
     *
     * @param int $time  Timestamp to find viewers for
     * @param int $end optional; if specified, finds max viewers between `$time` and `$end`
     * @return int   Viewers
     *
     * @access public
     */
    public function get_viewers_at($time, $end = -1) {
        if($time > $this->get('end') || $time < $this->get('start')) {
            return 0;
        }

        $stream_IDs = $this->get_stream_IDs();
        if (count($stream_IDs) == 0) {
            return 0;
        }

        if($end < 0) {
            $end = $time;
        }

        //calculate statistics
        $overall = new Lib\Interval($stream_IDs, $this->get('start'), $this->get('end'), false, $this->get('game'), false, $this->get_ID());
        $datapoints = $overall->get_datapoints();
        $return = 0;
        foreach($datapoints as $timestamp => $viewers) {
            if($timestamp <= $end) {
                if($timestamp >= $time) {
                    $return = max($return, $viewers);
                }
            } else {
                return $return;
            }
        }
        return $return;
    }

    /**
     * Get IDs of streams for this event
     *
     * @return  array   Stream IDs
     *
     * @access  public
     */
    public function get_stream_IDs() {
        $streams = $this->get_streams();
        return array_map(function ($a) {
            return $a['stream'];
        }, $streams);
    }


    /**
     * Get canonical URL referring to this item
     *
     * @return  string
     *
     * @access  public
     */
    public function get_url() {
        return '/events/'.$this->get_ID().'-'.friendly_url($this->get('short_name')).'/';
    }


    /**
     * Create URL based on data
     *
     * For use in static contexts
     *
     * @param    array $data Data to build the URL from. The method looks
     *                       for an 'url' parameter or if that is not found, uses the `LABEL` class
     *                       constant.
     *
     * @return  string  Constructed URL
     *
     * @access  public
     */
    public static function build_url($data) {
        return '/events/'.$data[self::IDFIELD].'-'.friendly_url($data['short_name']).'/';
    }


    /**
     * Retrieve short event name
     *
     * @param   string $name Name to shorten
     *
     * @return  string  Shortened name
     *
     * @access  public
     */
    public static function get_short_name($name) {
        $short_name = preg_replace('/^\[[a-zA-Z0-9 ]+\]/siU', '', $name);
        $short_name = preg_split('/(w\/|:| - | at )/siU', $short_name);
        $short_name = trim($short_name[0]);
        if(empty(trim($short_name))) {
            $short_name = $name;
        }
        return $name;
    }


    /**
     * Identify an event's franchise based on its listed name on Team Liquid
     *
     * @param   string $event_name Event name to check for
     *
     * @return  integer     Franchise ID
     *
     * @access public
     */
    public static function get_franchise($event_name) {
        //check manually made list of franchise mapping to see if the name contains
        //any known identifier
        $franchises = json_decode(file_get_contents(ROOT.'/Upkeep/events_mapping.json'), true);
        foreach ($franchises as $identifier => $franchise_name) {
            if (strpos($event_name, $identifier) !== false) {
                $name = $franchise_name;
                $franchise = $franchise_name;
                break;
            }
        }

        //if not, try to find it with some regexp magic
        if (!isset($franchise)) {
            $expl = explode(': ', $event_name);
            if (count($expl) > 1) {
                $franchise = $expl[0];
            }
        }

        //if nothing was found, apparently it's not part of a franchise
        if (!isset($franchise) || $franchise == '') {
            return Franchise::INDIE_ID;
        } else {
            try {
                $franchise = new Franchise(['tag' => $franchise]);
            } catch (\ErrorException $e) {
                if (!isset($name)) {
                    $name = $franchise;
                }
                $franchise = Franchise::create(array(
                    'tag' => $franchise,
                    'name' => $name,
                    'real_name' => $name,
                    'url' => friendly_url($name)
                ));
            }
            return $franchise->get_ID();
        }
    }


    /**
     * Extra search parameters
     *
     * @param   string $query Search query.
     *
     * @return  array   A set of SQL parameters that are used for searching for
     * objects of this type, with an OR relation. Example parameter:
     * `name LIKE ?`; ? would be replaced by the search query.
     *
     * @access  public
     */
    public static function search_params($query = '') {
        $franchises = Franchise::search($query);

        if (count($franchises) > 0) {
            return array(
                'franchise IN ('.implode(',', array_keys($franchises)).')' => ['relation' => 'OR']
            );
        } else {
            return array();
        }
    }


    /**
     * Recalculate event stats
     *
     */
    public function recalc_stats() {
        //get linked streams
        $stream_IDs = $this->get_stream_IDs();
        if (count($stream_IDs) == 0) {
            return false;
        }

        //calculate statistics
        $overall = new Lib\Interval($stream_IDs, $this->get('start'), $this->get('end'), false, $this->get('game'), false, $this->get_ID());

        //round values
        $stats = array_map(function ($a) {
            return floor($a);
        }, $overall->get_stats());

        //make stats array match database layout
        unset($stats['time'], $stats['end'], $stats['start'], $stats['outage'], $stats['datapoints']);
        $stats['hidden'] = 0;

        $this->set($stats);
        $this->update();
    }

    /**
     * Copy another event's data and delete the other event
     *
     * Effectively merges the other event into this one.
     *
     * @param int $event_ID  Event ID of event to copy data of and delete
     */
    public function absorb($event_ID) {
        $absorb = new Event($event_ID);

        $matches = Match::find(['return' => Lib\Model::RETURN_OBJECTS, 'where' => ['event = ?' => [$absorb->get_ID()]]]);
        foreach($matches as $match) {
            $match->set('event', $this->get_ID());
            $match->update();
        }

        $links = EventStream::find(['return' => Lib\Model::RETURN_OBJECTS, 'where' => ['event = ?' => [$absorb->get_ID()]]]);
        foreach($links as $link) {
            $current_link = EventStream::find(['return' => Lib\Model::RETURN_SINGLE_OBJECT, 'where' => ['event = ? AND stream = ?' => [$this->get_ID(), $link->get('stream')]]]);
            if($current_link) {
                $current_link->set('start', min($current_link->get('start'), $link->get('start')));
                $current_link->set('end', max($current_link->get('end'), $link->get('end')));
                $current_link->update();
                $link->delete();
            } else {
                $link->set('event', $this->get_ID());
                $link->update();
            }
        }

        $this->set('start', min($absorb->get('start'), $this->get('start')));
        $this->set('end', max($absorb->get('end'), $this->get('end')));
        $this->update();
        $this->recalc_stats();

        $absorb->delete();
    }
}