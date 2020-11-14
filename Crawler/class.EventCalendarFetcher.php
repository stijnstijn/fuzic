<?php
namespace Fuzic\Crawler\Event;

use Fuzic\Crawler;


class EventCalendarFetcher { //extends \Thread {
    /**
     * Value that uniquely identifies the calendar
     */
    const CALENDAR_ID = '';

    /**
     * @var array   Events after processing
     */
    public $live_events = array();
    /**
     * @var array   Matches after processing
     */
    public $live_matches = array();
    /**
     * @var array   Streams after processing
     */
    public $live_streams = array();
    /**
     * @var array   Log messages
     */
    public $log = array();
    /**
     * @var array   Streams after processing
     */
    public $providers = array();

    /**
     * Record log message
     *
     * Logging is not done by this class itself but log messages are stored so they can be
     * processed and optionally saved by another (e.g. `StreamChecker`)
     *
     * @param string  $message Message
     * @param integer $level   Severity level
     */
    public function log($message, $level = Crawler\StreamChecker::LOG_NOTICE) {
        $this->log[] = array('message' => $message, 'level' => $level);
    }

    /**
     * Register event data
     *
     * Adds an event to the internal array of live events. If the event is already known,
     * streams linked to the event are added to the array but other data will not be
     * processed.
     *
     * @param mixed $id   Unique identifier of the event
     * @param array $data Event data. Should contain two keys (`data` and `streams`).
     *                    `streams` is a numeric array of event IDs linked to the event; `data` an associative
     *                    array containing values for `id`, `name`, `short_name`, `wiki` and `game`.
     *
     * @access  public
     */
    public function add_event($id, $data) {
        if(isset($this->live_events[$id])) {
            foreach($data['streams'] as $stream_data) {
                if(!in_array($stream_data, $this->live_events[$id]['streams'])) {
                    $this->live_events[$id]['streams'][] = $stream_data;
                    $this->providers[$stream_data['provider']][$stream_data['remote_ID']] = $data['game'];
                }
            }
        } else {
            $this->live_events[$id] = $data;
            foreach($data['streams'] as $stream_data) {
                $this->providers[$stream_data['provider']][$stream_data['remote_ID']] = $data['game'];
            }
        }
    }

    /**
     * Register match data
     *
     * @param array $data Match data. Should contain two keys (`event` and `match`).
     *
     * @access  public
     */
    public function add_match($data) {
        if(!isset($this->live_events[$data['event']])) {
            return;
        }

        $unique_ID = sha1($data['event'].$data['match']);

        $this->live_matches[$unique_ID] = $data;
    }
}