<?php
namespace Fuzic\Crawler\Index;

use Fuzic\Crawler;


class IndexFetcher extends \Thread {
    /**
     * Value that uniquely identifies the index
     */
    const INDEX_ID = '';

    /**
     * @var array   Streams after processing
     */
    public $streams = array();

    /**
     * @var array   Streams after processing
     */
    public $providers = array();
    /**
     * @var array   Log messages
     */
    public $log = array();

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
     * Register stream data
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
    public function add_stream($data) {
        $this->streams[] = $data;
        if(!isset($this->providers[$data['provider']])) {
            $this->providers[$data['provider']] = array();
        }
        $this->providers[$data['provider']][$data['remote_ID']] = $data['game'];
    }
}