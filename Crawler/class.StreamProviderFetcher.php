<?php
namespace Fuzic\Crawler\Stream;

use Fuzic\Crawler;
use Fuzic\Lib;


class StreamProviderFetcher extends \Thread {
    /**
     * ID prefix as used for streams from this provider in the database
     */
    const PROVIDER_PREFIX = '';
    /**
     * Identifier used by TeamLiquid to mark streams as using this service
     */
    const LIQUID_PROVIDER_ID = '';

    /**
     * @var array   Streams to check
     */
    protected $streams = array();
    /**
     * @var array   Streams after processing
     */
    public $live_streams = array();
    /**
     * @var array   Streams as indexed by calendars and indexes earlier
     */
    public $indexed = array();
    /**
     * @var array   Log messages
     */
    public $log = array();

    /**
     * Set up API checking object
     *
     * @param array  $streams Streams using this service, as reported by calendars and indexes
     * @param string $games   Game data
     */
    public function __construct($streams, $games) {
        $this->games = array();

        foreach($games as $index => $game) {
            $this->games[$index] = $game;
        }

        foreach($streams as $index => $stream) {
            $this->indexed[$index] = $stream;
        }
    }

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
     * Record Stream data
     *
     * @param string $id   Stream ID
     * @param array  $data Stream data
     */
    public function add_stream($id, $data) {
        $data['remote_ID'] = $id;
        //check if stream was already tracked - if so, merge (this only happens for youtube)
        if(isset($this->live_streams[$id])) {
            //stream with most viewers takes precedence
            if($data['viewers'] > $this->live_streams[$id]['viewers']) {
                $this->live_streams[$id]['title'] = $data['title'];
                $this->live_streams[$id]['real_name'] = $data['real_name'];
                $this->live_streams[$id]['avatar'] = $data['avatar'];
            }
            $this->live_streams[$id]['viewers'] += $data['viewers'];
        } else {
            $this->live_streams[$id] = $data;
        }
    }
}