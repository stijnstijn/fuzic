<?php
/**
 * Gather information about broadcasting streams from Streaming services, calendars and indexes
 *
 * @package Fuzic
 */
namespace Fuzic\Crawler;


use Fuzic;
use Fuzic\Lib;
use Fuzic\Models;
use Fuzic\Crawler\Event;
use Fuzic\Crawler\Stream;

//this is just so that in case pthreads isn't available, the classes that inherit Thread don't break
require 'threading_fallback.php';

ini_set('memory_limit', '6G');

/**
 * Stream checker
 */
class StreamChecker
{
    /**
     * Log message severity levels
     */
    const LOG_DEBUG = 100;
    const LOG_INFO = 200;
    const LOG_NOTICE = 250;
    const LOG_WARNING = 300;
    const LOG_ERROR = 400;
    const LOG_CRITICAL = 500;
    const LOG_ALERT = 550;
    const LOG_EMERGENCY = 600;


    /**
     * User-Agent string the checker identifies itself with
     */
    const APP_ID = 'eSports Stream Tracker [tracker@fuzic.nl]';

    /**
     * Lock file name
     */
    const LOCK_FILE = 'streamchecker.lock';

    /**
     * Use threaded checkers?
     */
    const THREADED_CHECKERS = false;

    /**
     * @var array  Logged messages, for flushing or caching or whatever
     */
    private $log = array();

    /**
     * @var array  Array containing fetchers for various stream providers
     */
    private $provider_checkers = array();
    /**
     * @var array  Array containing fetchers for various event calendars
     */
    private $event_checkers = array();
    /**
     * @var array  Array containing fetchers for various stream indexes
     */
    private $index_checkers = array();
    /**
     * @var array  Stream data gathered from Twitch, Azubu, etc
     */
    private $live_streams = array();
    /**
     * @var array  Live events as reported by online calendars
     */
    private $live_events = array();
    /**
     * @var array  Live matches as reported by online calendars
     */
    private $live_matches = array();
    /**
     * @var array  Stream data gathered from indexes
     */
    private $indexed_streams = array();
    /**
     * @var array  Provider IDs gathered from indexes
     */
    private $indexed_providers = array();
    /**
     * @var array  Providers
     */
    private $providers = array();
    /**
     * @var int  Timestamp upon initialization. Used wherever time() is needed to synchronize data
     */
    private $time = 0;
    /**
     * @var object Database connection
     */
    private $db = NULL;
    /**
     * @var object Cache handler
     */
    private $cache = NULL;
    /**
     * @var array Stream trackers to use; empty = use all
     */
    private $use_stream_checkers = array();
    /**
     * @var array Calendar trackers to use; empty = use all
     */
    private $use_calendar_checkers = array();
    /**
     * @var array Index trackers to use; empty = use all
     */
    private $use_index_checkers = array();


    /**
     * Initialize stream checker
     *
     * @access  public
     *
     * @param $db
     * @param $cache
     */
    public function __construct($db, $cache) {
        $this->db = $db;
        $this->cache = $cache;
        $this->db->toggle_debug(false, true);

        $this->log('--- /!\ --- Check init.', self::LOG_WARNING);
        //register timestamp of this check
        $this->time = time();

        //make sure classes will be autoloaded. gotta find a better way to do this
        $x = new Models\Stream([], true);
        $x = new Models\Event([], true);
        unset($x);

        //make sure we're good to go
        if(file_exists(self::LOCK_FILE)) {
            $proc = explode('|', file_get_contents(self::LOCK_FILE));
            $pids = explode("\n", trim(`ps -e | awk '{print $1}'`));
            if(!in_array($proc[1], $pids)) {
                $this->log('Stream checker reported busy, but PID was not valid. Starting over.', self::LOG_EMERGENCY);
            } else {
                file_put_contents(self::LOCK_FILE, $proc[0].'|'.$proc[1].'|'.($proc[2] + 1));
                if($proc[2] > 1) {
                    $this->log('--- /!\ --- Previous instance of stream checker still running (cycle: '.$proc[2].'). Aborting.'."\n", self::LOG_EMERGENCY);
                } else {
                    $this->log('--- /!\ --- Previous instance of stream checker still running. Aborting.'."\n", self::LOG_EMERGENCY);
                }

                return;
            }
        }
        file_put_contents(self::LOCK_FILE, time().'|'.getmypid().'|1');

        //load per-provider trackers
        $this->load_checkers();

        $this->log('Init complete.', self::LOG_WARNING);

        //finish sesssions before checking for new data, to prevent gaps
        //in sessions.
        //
        //for example, if for whatever reason there has been no check for a day,
        //and a stream was broadcasting during the last check, and again during
        //this one, the script would otherwise see this as one continuous session
        $this->finish_sessions();

        //run checkers and have them process their data
        $this->check_events();
        $this->check_indexes();
        $this->check_streams();


        //we now have a list of events (with a list of non-normalized stream IDs casting each event), and a list of
        //live streams (with both normalized and non-normalized IDs). Let's see if we can link them properly.
        $this->link_events();

        krsort($this->live_events); //just make sure the events are always in the same order

        //update streams and events
        $this->process_streams();
        $this->process_events();
        $this->process_matches();

        //save data for live streams
        $this->add_datapoints();

        //update navigable calendar and audience count
        $this->get_audience();
        $this->get_calendar();

        $this->cleanup();
        $this->log('--- /!\ --- Check complete.'."\n", self::LOG_WARNING);
        unlink('streamchecker.lock');
    }


    /**
     * Load third-party stream and event info fetchers
     *
     * Searches subfolders for files implementing the relevant interface
     * and indexes them for later use. All PHP files in the relevant subfolders
     * will be included and then scanned for features identifying them as
     * trackers; so make sure all files are valid PHP
     *
     * @access  private
     */
    private function load_checkers() {
        //stream providers
        $files = scandir(dirname(__FILE__).'/Stream');
        foreach($files as $filename) {
            if(substr($filename, -4, 4) == '.php') {
                include dirname(__FILE__).'/Stream/'.$filename;
                $class = 'Fuzic\Crawler\Stream\\'.ucfirst(substr($filename, 0, -4)).'Checker';
                if(defined($class.'::DB_PROVIDER_ID') && defined($class.'::PROVIDER_PREFIX') && (empty($this->use_stream_checkers) || in_array($class::DB_PROVIDER_ID, $this->use_stream_checkers))) {
                    $this->providers[$class::DB_PROVIDER_ID] = $class::PROVIDER_PREFIX;
                    $this->provider_checkers[$class::DB_PROVIDER_ID] = array(
                        'provider' => $class::DB_PROVIDER_ID,
                        'prefix' => $class::PROVIDER_PREFIX,
                        'class' => $class
                    );
                }
            }
        }

        //event calendars
        $files = scandir(dirname(__FILE__).'/Event');
        foreach($files as $filename) {
            if(substr($filename, -4, 4) == '.php') {
                include dirname(__FILE__).'/Event/'.$filename;
                $class = 'Fuzic\Crawler\Event\\'.ucfirst(substr($filename, 0, -4)).'Checker';
                if(defined($class.'::CALENDAR_ID') && (empty($this->use_calendar_checkers) || in_array($class::CALENDAR_ID, $this->use_calendar_checkers))) {
                    $this->event_checkers[$class::CALENDAR_ID] = array(
                        'provider' => $class::CALENDAR_ID,
                        'class' => $class
                    );
                }
            }
        }

        //stream indexes
        $files = scandir(dirname(__FILE__).'/Index');
        foreach($files as $filename) {
            if(substr($filename, -4, 4) == '.php') {
                include dirname(__FILE__).'/Index/'.$filename;
                $class = 'Fuzic\Crawler\Index\\'.ucfirst(substr($filename, 0, -4)).'IndexChecker';
                if(defined($class.'::INDEX_ID') && (empty($this->use_index_checkers) || in_array($class::INDEX_ID, $this->use_index_checkers))) {
                    $this->index_checkers[$class::INDEX_ID] = array(
                        'provider' => $class::INDEX_ID,
                        'class' => $class
                    );
                }
            }
        }
    }


    /**
     * Gather data from event checkers
     */
    private function check_events() {
        //run event trackers, same story as for stream trackers
        $threaded_checkers = array();
        foreach($this->event_checkers as $checker_data) {
            $this->log('Checking event calendar '.$checker_data['provider']);
            //pass all TL streams linked to events as an argument
            $threaded_checkers[$checker_data['class']] = new $checker_data['class']();
            if(self::THREADED_CHECKERS) {
                $threaded_checkers[$checker_data['class']]->start();
            } else {
                $threaded_checkers[$checker_data['class']]->run();
            }
        }

        //collect results from event trackers (rather complex, but this way allows for making the checkers threaded on
        //a php install with pthreads, which has significant speed benefits
        foreach($threaded_checkers as $checker) {
            if(self::THREADED_CHECKERS) {
                $checker->join();
            }
            foreach($checker->live_events as $event_internal_ID => $event) {
                $event['streams'] = (array) $event['streams'];
                $this->live_events[$event_internal_ID] = (array) $event;
            }
            //$this->live_events = array_merge($this->live_events, $checker->live_events); //IDs as indexes
            if(isset($checker->live_matches)) {
                foreach($checker->live_matches as $match) {
                    $this->live_matches[] = (array) $match;
                }
            }
            foreach($checker->providers as $provider => $streams) {
                if(!isset($this->indexed_providers[$provider])) {
                    $this->indexed_providers[$provider] = (array) $streams;
                } else {
                    foreach($streams as $remote_ID => $game) {
                        $this->indexed_providers[$provider][$remote_ID] = $game;
                    }
                }
            }

            //can't log directly from threaded trackers, so process backlog of messages
            foreach($checker->log as $message) {
                $this->log($message['message'], $message['level']);
            }
            $this->log('Events on calendar '.$checker::CALENDAR_ID.': '.count($checker->live_events));
        }
    }


    /**
     * Gather data from Index checkers
     */
    private function check_indexes() {
        //run index trackers
        $threaded_checkers = array();
        foreach($this->index_checkers as $checker_data) {
            $this->log('Checking index provider '.$checker_data['provider']);
            $threaded_checkers[$checker_data['class']] = new $checker_data['class']();
            if(self::THREADED_CHECKERS) {
                $threaded_checkers[$checker_data['class']]->start();
            } else {
                $threaded_checkers[$checker_data['class']]->run();
            }
        }

        //collect results from index trackers
        foreach($threaded_checkers as $checker_ID => $checker) {
            if(self::THREADED_CHECKERS) {
                $checker->join();
            }
            foreach($checker->streams as $stream) {
                $this->indexed_streams[] = (array) $stream;
            }
            foreach($checker->providers as $provider => $streams) {
                if(!isset($this->indexed_providers[$provider])) {
                    $this->indexed_providers[$provider] = (array) $streams;
                } else {
                    $this->indexed_providers[$provider] = array_merge($this->indexed_providers[$provider], (array) $streams);
                }
            }

            //can't log from threaded trackers, so process backlog of messages
            foreach($checker->log as $message) {
                $this->log($message['message'], $message['level']);
            }
        }

        //normalize stream IDs and add info gathered from indexes
        $fixed = array();
        foreach($this->indexed_streams as $stream) {
            $internal_ID = $this->map_stream_id($stream['provider'], $stream['remote_ID']);
            if(isset($stream['event']) && !empty($stream['event'])) {
                foreach($this->live_events as $event_internal_ID => $event) {
                    if($event_internal_ID == $stream['event_internal_ID']) {
                        $this->live_events[$event_internal_ID]['streams'][] = array('provider' => $stream['provider'], 'remote_ID' => $stream['remote_ID']);
                        $this->log('Matched stream '.$internal_ID.' to event '.$event_internal_ID.' via indexing');
                    }
                }
            }
            $fixed[$internal_ID] = $stream;
        }

        $this->indexed_streams = $fixed;
        unset($fixed);

        //check if there are any new and exciting providers in what we've got
        $providers = is_file('stream_providers') ? file('stream_providers') : array();
        $known = array_map(function($a) {
            return trim($a);
        }, $providers);
        foreach($this->providers as $provider => $streams) {
            if(!in_array($provider, $known)) {
                $this->log('Unknown provider '.$provider, self::LOG_EMERGENCY);
            }
        }
    }


    /**
     * Gather data from stream checkers
     */
    private function check_streams() {
        //load games
        $__games = json_decode(file_get_contents(dirname(dirname(__FILE__)).'/games.json'), true);


        $threaded_checkers = array();
        foreach($this->provider_checkers as $checker_data) {
            $this->log('Checking stream provider '.$checker_data['provider']);
            $live = isset($this->indexed_providers[$checker_data['class']::DB_PROVIDER_ID]) ? $this->indexed_providers[$checker_data['class']::DB_PROVIDER_ID] : array();
            $threaded_checkers[$checker_data['class']] = new $checker_data['class']($live, $__games);
            if(self::THREADED_CHECKERS) {
                $threaded_checkers[$checker_data['class']]->start();
            } else {
                $threaded_checkers[$checker_data['class']]->run();
            }
        }

        //collect results from stream trackers
        foreach($threaded_checkers as $checker_ID => $checker) {
            if(self::THREADED_CHECKERS) {
                $checker->join();
            }
            foreach($checker->live_streams as $stream) {
                $this->live_streams[] = (array) $stream;
            }

            //can't log from threaded trackers, so process backlog of messages
            foreach($checker->log as $message) {
                $this->log($message['message'], $message['level']);
            }
            $this->log($checker::DB_PROVIDER_ID.' streams: '.count($checker->live_streams));
        }

        //normalize stream IDs and add info gathered from indexes
        $fixed = array();
        foreach($this->live_streams as $stream) {
            $internal_ID = $this->map_stream_id($stream['provider'], $stream['remote_ID']);
            if(isset($this->indexed_streams[$internal_ID])) {
                foreach($this->indexed_streams[$internal_ID] as $key => $value) {
                    if(!isset($stream[$key])) {
                        $stream[$key] = $value;
                    }
                }
            }
            $fixed[$internal_ID] = $stream;
        }
        $this->live_streams = $fixed;
        unset($fixed);
    }


    /**
     * Link events to streams
     *
     * All this does is take the list of remote IDs listed as streaming an event, and attempt to match these to IDs of
     * currently live streams. The internal IDs are then saved.
     */
    private function link_events() {
        foreach($this->indexed_streams as $internal_ID => $stream) {
            if(isset($stream['event']) && isset($this->live_events[$stream['event_internal_ID']]) && !in_array($internal_ID, $this->live_events[$stream['event_internal_ID']]['streams'])) {
                $this->live_events[$stream['event_internal_ID']]['streams'][] = array('provider' => $stream['provider'], 'remote_ID' => strtolower($stream['remote_ID']));
                $this->log('Adding stream '.$stream['remote_ID'].' to event '.$this->live_events[$stream['event_internal_ID']]['name']);
            }
        }

        foreach($this->live_events as $event_ID => $event) {
            $streams = array();
            if(isset($event['streams']) && !empty($event['streams'])) {
                foreach($event['streams'] as $stream) {
                    $mapped_ID = self::map_stream_id($stream['provider'], $stream['remote_ID']);
                    foreach($this->live_streams as $internal_ID => $stream_data) {
                        if($internal_ID == $mapped_ID && $stream_data['game'] == $event['game'] && !in_array($internal_ID, $streams)) {
                            $streams[] = $internal_ID;
                            continue 2;
                        }
                    }
                    $this->log('Stream '.$stream['provider'].'/'.$stream['remote_ID'].' ('.$mapped_ID.') was linked to '.$event['name'].' but is already linked or not available');
                }
            }
            $this->live_events[$event_ID]['streams'] = $streams;
        }
    }


    /**
     * Process viewer numbers and save finished sessions to database
     *
     * @access  private
     */
    private function finish_sessions() {
        //get all unprocessed datapoints
        $this->log('Querying datapoints.');
        $datapoints = $this->db->fetch_all("SELECT stream, time, viewers, game, title FROM ".Models\Datapoint::TABLE." ORDER BY stream ASC, time ASC");
        $sorted = array();
        $this->log('Sorting datapoints.');

        //we need them sorted later
        foreach($datapoints as $datapoint) {
            $sorted[$datapoint['game']][$datapoint['stream']][$datapoint['time']] = $datapoint;
        }

        $cutoff = $this->time - Fuzic\Config::MAX_SESSION_PAUSE;
        $max_pause = Fuzic\Config::CHECK_DELAY * 6;
        $i = 0;

        $this->db->start_transaction();

        foreach($sorted as $game_ID => $game) {
            foreach($game as $stream_ID => $stream_datapoints) {
                unset($previous);
                $last = end($stream_datapoints);
                $first = reset($stream_datapoints);

                if($last['time'] < $cutoff || $last['time'] - $first['time'] > 86400) {
                    //session has ended or is longer than one day - start processing
                    $titles = array();
                    $stats = array('peak' => 0, 'total_time' => 0, 'total_viewers' => 0, 'start' => $first['time'], 'end' => $last['time']);
                    $fixed_datapoints = array();

                    //cycle through datapoints and calculate statistics
                    foreach($stream_datapoints as $point) {
                        if(!isset($titles[$point['title']])) {
                            $titles[$point['title']] = 1;
                        } else {
                            $titles[$point['title']] += 1;
                        }

                        if(isset($previous)) {
                            $stats['peak'] = max($stats['peak'], $point['viewers']);
                            $interval_time = $point['time'] - $previous['time'];
                            $interval_average = ($point['viewers'] + $previous['viewers']) / 2;

                            if($interval_time < $max_pause) {
                                $stats['total_viewers'] += ($interval_average * $interval_time);
                                $stats['total_time'] += $interval_time;
                            }
                        }

                        $previous = $point;
                        $fixed_datapoints[$point['time'] - $first['time']] = $point['viewers'];
                    }

                    $stats['average'] = $stats['total_time'] > 0 ? $stats['total_viewers'] / $stats['total_time'] : 0;
                    $stats['vh'] = $stats['total_time'] > 0 ? $stats['average'] * $stats['total_time'] / 3600 : 0;
                    $stats['time'] = $stats['total_time'];

                    //sort titles by most occuring and use the most-used one as session title
                    arsort($titles);
                    $titles = array_keys($titles);
                    $stats['title'] = iconv('UTF-8', 'UTF-8//IGNORE', array_shift($titles));

                    //whether the stream is saved or not, we don't need the datapoints in the database anymore
                    $i += 1;
                    $this->db->delete(Models\Datapoint::TABLE, 'game = '.$this->db->escape($game_ID).' AND stream = '.$this->db->escape($stream_ID));

                    //ignore sessions with only a few datapoints or low amount of viewers
                    if(count($stream_datapoints) < 3 || ((count($stream_datapoints) < 30 && $stats['peak'] < 50) || $stats['peak'] < 10)) {
                        $this->log('Sessions for stream '.$stream_ID.' ended but is not relevant enough to save (peak '.$stats['peak'].', average '.$stats['average'].', '.count($stream_datapoints).' datapoints)', self::LOG_DEBUG);
                        continue;
                    }

                    //add session to database if it survived the last check
                    try {
                        $session = Models\Session::create([
                            'stream' => $stream_ID,
                            'game' => $game_ID,
                            'start' => $stats['start'],
                            'end' => $stats['end'],
                            'time' => $stats['time'],
                            'average' => round($stats['average']),
                            'peak' => $stats['peak'],
                            'vh' => round($stats['vh'])
                        ]);
                        Models\SessionData::create([
                            'sessionid' => $session->get_ID(),
                            'datapoints' => json_encode($fixed_datapoints),
                            'title' => $stats['title'],
                            'interpolated' => 0
                        ]);
                    } catch(\ErrorException $e) {
                        $this->log('Could not add session to database. Probable Deadlock; will try again next cycle ('.$e->getMessage().')', self::LOG_EMERGENCY);

                        return;
                    }

                    $this->log('Session for '.$stream_ID.' (dead for '.($this->time - $stats['end']).'s; '.$stats['time'].'s/'.count($stream_datapoints).'pts long; avg '.round($stats['average']).'; peak '.$stats['peak'].'; title "'.$stats['title'].'")');
                }
            }
        }

        $this->db->commit();
        $this->log($i.' sessions ended.');
    }


    /**
     * Put new streams in the database
     *
     * @access  private
     */
    private function process_streams() {
        $this->db->start_transaction();

        //go through each stream
        foreach($this->live_streams as $internal_ID => $stream) {
            //normalize data from APIs to database field names
            $update = array(
                'last_seen' => $this->time,
                'last_status' => iconv('UTF-8', 'UTF-8//IGNORE', $stream['title']),
                'remote_id' => $stream['remote_ID'],
                'avatar' => (isset($stream['avatar']) ? $stream['avatar'] : ''),
                'last_game' => $stream['game'],
                'tl_featured' => (isset($stream['featured']) ? $stream['featured'] : 0),
                'tl_id' => (isset($stream['tl_name']) ? $stream['tl_name'] : ''),
                'provider' => $stream['provider'],
                'language' => (isset($stream['language']) ? $stream['language'] : '')
            );

            //update stream if it already exists
            try {
                $stream_obj = new Models\Stream($internal_ID);
                if(isset($stream['real_name']) && !empty($stream['real_name']) && $stream_obj->get('real_name') == $stream['name']) {
                    $update['real_name'] = $stream['real_name'];
                }
                $stream_obj->set($update);
                $stream_obj->update();

                //or create it if not (in which case we need some more data)
            } catch(Lib\ItemNotFoundException $e) {
                $real_name = isset($stream['real_name']) && !empty($stream['real_name']) ? $stream['real_name'] : $stream['name'];
                $data['team'] = Models\Team::TEAMLESS_ID;
                $data['player'] = 1;
                $data['real_name'] = (!isset($update['tl_id']) || empty($update['tl_id'])) ? $real_name : $update['tl_id'];
                if(isset($stream['real_name'])) {
                    $data['real_name'] = $stream['real_name'];
                }
                $this->log('Stream '.$data['real_name'].' unknown, adding to database', self::LOG_WARNING);
                $data[Models\Stream::IDFIELD] = $internal_ID;
                $data = array_merge($data, $update);
                try {
                    Models\Stream::create($data);
                } catch(\ErrorException $e) {
                    //This sometimes happens if a particular check takes longer than a minute and a subsequent check has already
                    //added the stream to database. No cause for real alarm, since the data *is* in the database now anyway
                    $this->log('Could not push stream '.$data['real_name'].' to database ('.$e->getMessage().')', self::LOG_WARNING);
                    $this->log($data['last_status']);
                }
            }
        }

        $this->db->commit();
    }


    /**
     * Record current viewer numbers
     *
     * @access  private
     */
    private function add_datapoints() {
        $this->db->start_transaction();

        //simply loop through streams and save the viewer number for each
        foreach($this->live_streams as $internal_ID => $stream) {
            $viewers = $stream['viewers'];

            try {
                Models\Datapoint::create([
                    'time' => $this->time,
                    'game' => $stream['game'],
                    'viewers' => $viewers,
                    'stream' => $internal_ID,
                    'title' => iconv('UTF-8', 'UTF-8//IGNORE', $stream['title'])
                ]);
            } catch(\ErrorException $e) {
                $this->log('Could not add datapoint for stream with ID '.$internal_ID.': deadlock? ('.$e->getMessage().')', self::LOG_WARNING);
            }
        }

        $this->db->commit();

        //cache the timestamps
        $this->cache->set('first-timestamp', $this->db->fetch_field("SELECT MIN(start) FROM ".$this->db->escape_identifier(Models\Session::TABLE)));
        $this->cache->set('last-timestamp', $this->db->fetch_field("SELECT MAX(start) FROM ".$this->db->escape_identifier(Models\Session::TABLE)));
    }


    /**
     * Store overall amount of viewers for games
     *
     * @access  private
     */
    private function get_audience() {
        $total = array();
        foreach($this->live_streams as $stream) {
            if(isset($total[$stream['game']])) {
                $total[$stream['game']] += $stream['viewers'];
            } else {
                $total[$stream['game']] = $stream['viewers'];
            }
        }


        $this->db->start_transaction();

        foreach($total as $game => $audience) {
            $this->log('Saving overall viewer number ('.$audience.') for game '.$game);
            $this->db->insert('audience', ['time' => $this->time, 'game' => $game, 'viewers' => $audience]);
        }

        $this->db->commit();
    }


    /**
     * Weed out duplicate events
     *
     * Some events may be listed on multiple event calendars; this function attempts to get rid of the duplicates
     */
    private function filter_events() {
        $deleted = array();
        $compare = $this->live_events;
        foreach($this->live_events as $index => $event) {
            $franchise = Models\Event::get_franchise($event['name']);
            foreach($compare as $index_cmp => $event_cmp) {
                if($index == $index_cmp || isset($deleted[$index])) {
                    continue;
                }

                $franchise_cmp = Models\Event::get_franchise($event_cmp['name']);
                $franchise_match = ($franchise_cmp == $franchise && $franchise != Models\Franchise::INDIE_ID && $event['game'] == $event_cmp['game']);

                if(!$franchise_match) {
                    //shitty attempt at doing fuzzy matching
                    $ls = levenshtein(strtolower($event['name']), strtolower($event_cmp['name']));
                    $ls_match = ($ls < strlen($event['name']) / 3);
                } else {
                    $ls_match = false;
                }

                if($franchise_match || $ls_match) {
                    $this->log('Duplicate event detected: '.$event['name'].' <- '.$event_cmp['name'], self::LOG_WARNING);
                    $this->log('Deleting '.$index_cmp.' in favour of '.$index, self::LOG_WARNING);
                    foreach($event_cmp['streams'] as $stream) {
                        if(!in_array($stream, $event['streams'])) {
                            $event['streams'][] = $stream;
                        }
                    }
                    if(empty($event['wiki']) && !empty($event_cmp['wiki'])) {
                        $event['wiki'] = $event_cmp['wiki'];
                    }
                    foreach($this->live_matches as $id => $data) {
                        if($data['event'] == $index_cmp) {
                            $this->live_matches[$id]['event'] = $index;
                        }
                    }
                    unset($this->live_events[$index_cmp]);
                    unset($compare[$index_cmp]);
                    $this->live_events[$index] = $event;
                    $deleted[$index_cmp] = true;
                    continue;
                }
            }
        }
    }


    private function process_events() {
        $autolink = array();

        //auto-link streams based on stream title
        foreach($this->live_events as $event_index => $event) {
            $franchise = new Models\Franchise(Models\Event::get_franchise($event['name']));
            $this->live_events[$event_index]['franchise'] = $franchise->get_ID();
            foreach($this->live_streams as $internal_ID => $stream) {
                if(
                    !in_array($internal_ID, $event['streams'])
                    && strlen($franchise->get('name')) > 4
                    && $event['game'] == $stream['game']
                    && (strpos($stream['title'], $franchise->get('name')) !== false || stripos($stream['title'], $franchise->get('name')) !== false)
                    && strpos(strtolower($stream['title']), 'rediffusion') === false
                ) {
                    $this->live_events[$event_index]['streams'][] = $internal_ID;
                    $this->log('Auto-linking stream '.$stream['remote_ID'].' to '.$event['name'].' ('.$stream['title'].')', self::LOG_WARNING);
                    $autolink[] = $internal_ID;
                }
            }
        }

        foreach($this->live_events as $event) {//update event info, or create if new
            try {
                $event_obj = new Models\Event(['tl_id' => $event['id']]);
                if($event_obj->get('end') < time() - 3600) {
                    $this->log('Event '.$event['name'].' has been inactive for too long; creating sub-event');
                    $i = 1;
                    while($newer = Models\Event::find(['return' => Lib\Model::RETURN_SINGLE_OBJECT, 'where' => ['tl_id = ?' => [$event['id'].'-'.$i]]])) {
                        if($newer->get('end') < time() - 3600) {
                            $i += 1;
                        } else {
                            $event['id'] = $newer->get('tl_id');
                            $event_obj = $newer;
                            break;
                        }
                    }
                    if(!$newer) {
                        $event['id'] .= '-'.$i;
                        throw new Lib\ItemNotFoundException('Event too old');
                    }
                }
                $new_data = [
                    'end' => $this->time,
                    'tl_id' => $event['id'],
                    'franchise' => $event['franchise'],
                    'wiki' => $event['wiki']
                ];
                if(count($event['streams']) > 0) {
                    $new_data['hidden'] = '0';
                }
                $event_obj->set($new_data);
                $this->log('Event '.$event['name'].' ('.$event['id'].', '.$event['game'].', '.count($event['streams']).' streams) already known, updating.');

                //if new, make it
            } catch(Lib\ItemNotFoundException $e) {
                $event_obj = Models\Event::create([
                    'name' => $event['name'],
                    'game' => $event['game'],
                    'tl_id' => $event['id'],
                    'short_name' => Models\Event::get_short_name($event['short_name']),
                    'wiki' => $event['wiki'],
                    'start' => $this->time,
                    'end' => $this->time,
                    'franchise' => $event['franchise'],
                    'hidden' => (count($event['streams']) == 0 ? '1' : '0')
                ]);
                $this->log('Creating new event '.$event['short_name'].' with ID '.$event_obj->get_ID());
            }

            foreach($event['streams'] as $stream_internal_ID) {
                $this->log('Linking event '.$event_obj->get_ID().' to stream '.$stream_internal_ID, self::LOG_NOTICE);
                $is_auto = isset($autolink[$stream_internal_ID]);

                try {
                    $stream = new Models\Stream($stream_internal_ID);
                } catch(Lib\ItemNotFoundException $e) {
                    $this->log('Invalid stream link: event '.$event_obj->get('name').' ('.$event_obj->get_ID().') to stream '.$stream_internal_ID, self::LOG_WARNING);
                    continue;
                }

                try {
                    $link = new Models\EventStream([
                        'event' => $event_obj->get_ID(),
                        'stream' => $stream->get_ID()
                    ]);

                    $link->set('end', $this->time);
                    $link->set('viewers', $this->live_streams[$stream_internal_ID]['viewers']);
                    $link->update();
                    //if not, create it
                } catch(Lib\ItemNotFoundException $e) {
                    Models\EventStream::create([
                        'event' => $event_obj->get_ID(),
                        'stream' => $stream->get_ID(),
                        'start' => $this->time,
                        'end' => $this->time,
                        'auto' => (isset($is_auto) ? 1 : 0),
                        'viewers' => $this->live_streams[$stream_internal_ID]['viewers']
                    ]);
                }
            }

            if(count($event['streams']) != 0) {
                //streams linked, so event can be made visible
                $event_obj->recalc_stats();
                $event_obj->set('hidden', 0);
                $event_obj->update();
            }
        }
    }


    /**
     * Process found live matches and save them to the database
     */
    private function process_matches() {
        //save currently playing matches to database
        foreach($this->live_matches as $id => $data) {
            try {
                $event = new Models\Event(['tl_id' => $data['event']]);
            } catch(Lib\ItemNotFoundException $e) {
                $this->log('Invalid event ('.$data['event'].') for match '.$data['match'], self::LOG_WARNING);
                continue;
            }

            $data['event'] = $event->get_ID();

            try {
                $match = new Models\Match(['event' => $data['event'], 'match' => $data['match']]);
                $match->set('end', $this->time);
                $match->update();
            } catch(Lib\ItemNotFoundException $e) {
                $data['start'] = $this->time;
                $data['end'] = $this->time;
                Models\Match::create($data);
            }
        }
    }


    /**
     * Check for what time periods we have data for this view
     *
     * This is done within the Stream Checker even though it is a site-related
     * task as the only time this changes is when streams are checked.
     *
     * An array of available weeks is stored in the cache for use on the website.
     *
     * @access  public
     */
    function get_calendar() {
        //get earliest and last timestamps for which data was recorded
        $first = $this->cache->get('first-timestamp');
        $last = $this->cache->get('last-timestamp');

        $first_year = date('Y', $first);
        $last_year = date('Y', $last);

        $calendar = array();

        //loop from first to last recorded year
        for($year = $first_year; $year <= $last_year; $year += 1) {
            $calendar[$year] = array();

            //if it's not the first or last, just include the whole year
            if($year != $first_year && $year != $last_year) {
                $first_week = 1;
                $last_week = units_per_year('week', $year);

                //else only include those months for which there is data
            } else {
                if($year == $first_year) {
                    $first_week = intval(date('W', $first));
                } else {
                    $first_week = 1;
                }

                if($year == $last_year) {
                    $last_week = intval(date('W', $last));
                } else {
                    $last_week = units_per_year('week', $year);
                }
            }

            //for each month, see what weeks are in there
            for($week = $first_week; $week <= $last_week; $week += 1) {
                $month = date('n', strtotime($year.'W'.str_pad($week, 2, '0', STR_PAD_LEFT)));

                //if a low week number is in month 12, that means the week was
                //actually in the previous year. week numbers are weird.
                if($week < 10 && $month == 12) {
                    $week_year = $year - 1;
                } else {
                    $week_year = $year;
                }

                //store the week number
                if(!isset($calendar[$week_year][$month])) {
                    $calendar[$week_year][$month] = array();
                }
                $calendar[$week_year][$month][] = ['w' => $week, 'y' => $year];
            }
        }

        $this->cache->set('nav-calendar', $calendar);
    }


    /**
     * Map game descriptor to standardized identifier
     *
     * @param $string string  Game name to map
     *
     * @return string         Game name in standardized format, or `false` if unknown
     * game
     */
    public static function map_game_name($string) {
        //run ALL THE FILTERS
        $string = trim(strtolower(preg_replace(array('/&amp;/', '/([^a-zA-Z0-9 ]+)/siU', '/([ ]+)/', '/\-/'), array('&', '', ' ', ' '), strip_tags(urldecode($string)))));
        if(strpos($string, 'starcraft2') !== false || strpos($string, 'starcraft 2') !== false || strpos($string, 'starcraftii') !== false || strpos($string, 'starcraft ii') !== false || strpos($string, 'sc2') !== false || strpos($string, 'lotv') !== false || strpos($string, 'heart of the swarm') !== false || strpos($string, 'legacy of the void') !== false) {
            return 'sc2';
        }
        if(strpos($string, 'hearth') !== false || strtolower($string) == 'hs') {
            return 'hearthstone';
        }
        if(strpos($string, 'heroesofthestorm') !== false || strpos($string, 'heroes of the storm') !== false || strpos($string, 'hots') !== false || $string == 'heroes') {
            return 'heroes';
        }
        if(strpos($string, 'broodwar') !== false || strpos($string, 'starcraft') !== false || strpos($string, 'brood war') !== false || strtolower($string) == 'bw') {
            return 'broodwar';
        }
        if(strpos($string, 'overwatch') !== false || strtolower($string) == 'ow') {
            return 'overwatch';
        }

        /*
        if(strpos($string, 'dota2') !== false || strpos($string, 'dota 2') !== false || strpos($string, 'dota ii') !== false) {
          return 'dota2';
        }
        if(strpos($string, 'lol') !== false || strpos($string, 'league') !== false) {
          return 'lol';
        }
    */

        return false;
    }


    /**
     * Get internal stream ID
     *
     * Internal stream IDs are based on a provider-specific prefix and the remote ID. This method takes both those
     * values as parameter and returns the internal ID.
     *
     * @param $provider         string  Provider ID (as used in the database)
     * @param $remote_ID        string  Stream ID (specific for the provider)
     *
     * @return bool|string  The internal ID, or `false` if the provider ID was not valid.
     */
    public function map_stream_id($provider, $remote_ID) {
        if(!isset($this->providers[$provider])) {
            $this->log('Invalid provider '.$provider.' when mapping stream ID', self::LOG_EMERGENCY);

            return false;
        }

        return $this->providers[$provider].strtolower($remote_ID);
    }


    /**
     * Identify a stream by its URL
     *
     * @param  string $string Link to parse
     *
     * @return  array|bool  Array with keys `provider` and `name`, or `false` if unparseable
     *
     * @access  public
     */
    public static function map_stream_link($string) {
        if(preg_match('/(twitch|azubu)(\.tv|)\/([^\/]+)$/siU', $string, $match)) {
            return array(
                'provider' => trim($match[1]),
                'remote_ID' => trim($match[3])
            );
        }
        if(preg_match('/youtube\.com\/watch\?v=([^&]+)/si', $string, $match)) {
            return array(
                'provider' => 'youtube',
                'remote_ID' => $match[1]
            );
        }
        if(preg_match('/youtu\.be\/([^&]+)/si', $string, $match)) {
            return array(
                'provider' => 'youtube',
                'remote_ID' => $match[1]
            );
        }
        if(preg_match('/stream\.me\/([^&]+)/si', $string, $match)) {
            return array(
                'provider' => 'streamme',
                'remote_ID' => $match[1]
            );
        }

        return false;
    }


    /**
     * Get valid provider ID for provider string
     *
     * @param $string  string  Provider ID
     *
     * @return bool|mixed|string  Provider ID, or `false` if no valid one could be mapped to.
     */
    public static function map_provider_id($string) {
        $string = strtolower($string);
        $string = preg_replace('/\.([a-z]+)$/siU', '', $string);
        if(in_array($string, array('afreeca', 'azubu', 'dingit', 'dailymotion', 'goodgame', 'hitbox', 'livestream', 'twitch', 'youtube'))) {
            return $string;
        } elseif($string == 'justin') {
            return 'twitch';
        } elseif($string == 'mogulus') {
            return 'livestream';
        }

        return false;
    }


    /**
     * Log to file
     *
     * @param   string  $message Message to log
     * @param   integer $level   Severity level
     *
     * @access private
     */
    private function log($message, $level = self::LOG_NOTICE) {
        $this->log[] = array('level' => $level, 'message' => $message);
        //echo (time() - $this->time).' '.$message."\n";

        //put log in log file
        if($level >= self::LOG_INFO) {
            //$log = file_get_contents('log');
            $log = time().' '.date('r').' '.$message."\n"; //.$log;
            $handle = fopen(LOG_DIR.'/crawler.log', 'a');
            fwrite($handle, $log);
            fclose($handle);
        }

        //if severe, dump to echo so a mail gets sent (could be more sophisticated, but this works)
        if($level >= self::LOG_EMERGENCY) {
            $dump = $level.' '.$message."\n\n";
            foreach($this->log as $index => $line) {
                $dump .= $line['level'].' '.$line['message']."\n";
                unset($this->log[$index]);
            }
        }
    }


    /**
     * Truncates the rolling log
     *
     * @access private
     */
    private function cleanup() {
        $log = file(LOG_DIR.'/crawler.log');
        while(count($log) > 10000) {
            array_shift($log);
        }
        file_put_contents(LOG_DIR.'/crawler.log', implode('', $log));
    }


}