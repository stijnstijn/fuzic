<?php
/**
 * Stream model
 *
 * @package Fuzic
 */
namespace Fuzic\Models;

use Fuzic\Lib;


/**
 * Stream model
 */
class Stream extends Lib\Model
{
    const TABLE = 'streams';
    const IDFIELD = 'id';
    const LABEL = 'real_name';

    /**
     * Get events this stream has broadcasted
     *
     * @param string $game
     *
     * @return array Events
     *
     * @throws \ErrorException
     * @access  public
     */
    public function get_events($game = ACTIVE_GAME) {

        global $db;
        $events = EventStream::find([
            'where' => [
                'stream = ?' => [$this->get_ID()]
            ],
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
        return '/streams/'.$this->get_ID().'/';
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
        return '/streams/'.$data[static::IDFIELD].'/';
    }


    /**
     * Extra search parameters
     *
     * @param   string  Search query.
     *
     * @return  array   A set of SQL parameters that are used for searching for
     * objects of this type, with an OR relation. Example parameter:
     * `name LIKE ?`; ? would be replaced by the search query.
     *
     * @access  public
     */
    public static function search_params($query = '') {
        $teams = Team::search($query);

        if (count($teams) > 0) {
            return array(
                'team IN ('.implode(',', array_keys($teams)).')' => ['relation' => 'OR']
            );
        } else {
            return array();
        }
    }


    public function is_live() {
        $points = Datapoint::find(['where' => ['stream = ?' => [$this->get_ID()]]]);

        return count($points) > 0;
    }


}