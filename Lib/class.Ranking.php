<?php
/**
 * Stream rankings
 *
 * @package Fuzic
 */
namespace Fuzic\Lib;


use Fuzic\Models;
use Fuzic\Config;


/**
 * Calculate rankings
 */
class Ranking
{
    /**
     * @var integer Start of ranking period
     */
    protected $epoch;
    /**
     * @var integer  Ending of ranking period
     */
    protected $terminus;
    /**
     * @var array   Streams active within ranking period
     */
    protected $streams;
    /**
     * @var string  Field identifying the object ranked (`team` or `stream`)
     */
    protected $rank_type = 'stream';
    /**
     * @var string  Table suffix
     */
    protected $suffix = '';
    /**
     * @var boolean  Ignore events?
     */
    protected $ignore_events = false;
    /**
     * @var int     Start time of ranking
     */
    protected $start = 0;
    /**
     * @var string  Game to calculate starts for
     */
    protected $game = '';


    /**
     * Set up ranking and normalize times
     *
     * @param   integer $epoch         The start of the interval to analyze, as UNIX timestamp
     * @param   integer $terminus      The end of the interval to analyze, as UNIX timestamp
     * @param   string  $rank_type     What to rank. Can be `stream`, `event` or `team`
     * @param boolean   $ignore_events Ignore stretched of time during which stream was broadcasting an event
     *
     * @param bool      $opt
     * @param string    $game
     *
     * @access  public
     */
    public function __construct($epoch = 0, $terminus = 0, $rank_type = 'stream', $opt = false, $game = '', $ignore_events = false) {
        global $db;

        $this->start = time();
        $this->game = preg_replace('/[^a-z0-9]/siU', '', $game);

        $this->epoch = ($epoch == 0) ? $db->fetch_field("SELECT MIN(start) FROM ".Models\Session::TABLE." WHERE game = '".$this->game."'") : $epoch;
        $this->terminus = ($terminus == 0) ? $db->fetch_field("SELECT MAX(time) FROM ".Models\Datapoint::TABLE." WHERE game = '".$this->game."'") : $terminus;

        $this->rank_type = $rank_type;

        // "Ranking for game ".$game."\n";

        if($this->rank_type == 'team') {
            $this->suffix = '_team';
        } elseif($this->rank_type == 'event') {
            $this->suffix = '_event';
        }

        if($ignore_events) {
            $this->suffix .= '_e';
        }

        $this->ignore_events = $ignore_events;
        //echo "Calculating rankings between ".date('d-m-Y H:i', $this->epoch).' and '.date('d-m-Y H:i', $this->terminus)."\n";
    }


    /**
     * Get all stream IDs active within the period
     *
     * @return  array   Streams
     *
     * @access  public
     */
    public function get_all_streams() {
        global $db;

        if(empty($this->streams)) {
            $this->streams = $db->fetch_fields("
                SELECT DISTINCT stream
                  FROM ".Models\Session::TABLE."
                 WHERE game = '".$this->game."' AND ((end <= ".$this->terminus." AND end >= ".$this->epoch.") OR
                        (start >= ".$this->epoch." AND start <= ".$this->terminus.") OR
                        (start <= ".$this->epoch." AND end >= ".$this->terminus."))");
        }

        return $this->streams;
    }


    /**
     * Get current week/month ranking for a given stream
     *
     * @param   mixed $stream Stream to get ranking for
     *
     * @param int     $limit
     *
     * @return array Stream ranking for current week
     *
     * @access  public
     */
    public static function get_current_week($stream, $limit = 0) {
        global $db;

        $current_week = date('W');
        $current_year = ($current_week > 52) ? date('y') - 1 : date('y');

        $limit = ($limit > 0) ? ' LIMIT '.intval($limit) : '';

        if($stream) {
            $stream_ID = $stream->get_ID();

            return $db->fetch_single("SELECT * FROM ranking_week WHERE game = '".ACTIVE_GAME."' AND stream = ".$db->escape($stream_ID)." AND week = ".$current_week." AND year = ".$current_year.$limit);
        } else {
            return $db->fetch_all("SELECT r.*, s.* FROM ranking_week AS r, ".Models\Stream::TABLE." AS s WHERE r.game = '".ACTIVE_GAME."' AND r.week = ".$current_week." AND r.year = ".$current_year.' AND s.player = 1 AND r.stream = s.'.Models\Stream::IDFIELD.' ORDER BY r.rank ASC'.$limit);
        }
    }


    /**
     * Get current week/month ranking for a given stream
     *
     * @param   mixed $stream Stream to get ranking for
     *
     * @param int     $limit
     *
     * @return array Stream ranking for current week
     *
     * @access  public
     */
    public static function get_current_month($stream, $limit = 0) {
        global $db;

        $current_month = date('n');
        $current_year = date('Y');

        $limit = ($limit > 0) ? ' LIMIT '.intval($limit) : '';

        if($stream) {
            $stream_ID = $stream->get_ID();

            return $db->fetch_single("SELECT * FROM ranking_month WHERE game = '".ACTIVE_GAME."' AND stream = ".$db->escape($stream_ID)." AND month = ".$current_month." AND year = ".$current_year.$limit);
        } else {
            return $db->fetch_all("SELECT r.*, s.* FROM ranking_month AS r, ".Models\Stream::TABLE." AS s WHERE r.game = '".ACTIVE_GAME."' AND r.month = ".$current_month." AND r.year = ".$current_year.' AND s.player = 1 AND r.stream = s.'.Models\Stream::IDFIELD.' ORDER BY r.rank ASC'.$limit);
        }
    }


    /**
     * Get all-time rank for a given stream
     *
     * @param   mixed $stream Stream to get ranking for
     *
     * @return  array   Stream ranking for current week
     *
     * @access  public
     */
    public static function get_alltime($stream) {
        global $db;

        $stream_ID = $stream->get_ID();

        return $db->fetch_single("SELECT * FROM ranking_alltime WHERE game = '".ACTIVE_GAME."' AND stream = ".$db->escape($stream_ID));
    }


    /**
     * Rank recently active streams
     *
     * Instead of re-calculating ranks for all streams within a given period, it is often more efficient to only
     * (re-)calculate stats for recently active streams. This method does that, calculating stats for streams with
     * sessions within the last so many hours and afterwards recalculating rank numbers and deltas.
     *
     * Calculates both month and week ranks.
     *
     * @param int      $since         Streams with sessions that ended after this time will be (re-)ranked.
     * @param bool|int $until         Upper limit for inclusion
     * @param boolean  $ignore_events Ignore stretched of time during which stream was broadcasting an event
     * @param boolean  $output        Show debug output in console
     *
     * @throws \ErrorException
     */
    public function rank_since($since = 0, $until = false, $ignore_events = false, $output = false) {
        global $db;

        if($output) {
            echo "\nInit...";
        }

        if($until === false) {
            $until = time() + 1;
        }

        //this is kind of tricky - we need to normalize the start and until so we're at the start and end of a month
        //or we're gonna end up with a bunch of stats that miss part of the week/month
        //normalize to the start of the month, and then the start of the week that day is
        $since = mktime(0, 0, 0, date('n', $since), 1, date('Y', $since)); //month!
        $first_month = date('n', $since);
        $first_year = date('Y', $since);
        $week_since = period_start('week', date('W', $since), get_week_year($since)); //week!
        $since = mktime(0, 0, 0, date('n', $week_since), date('j', $week_since), date('Y', $week_since));
        $first_week = date('W', $since);
        $first_weekyear = get_week_year($since);

        //do the same for the end time
        $until = period_end('month', date('n', $until), date('Y', $until));
        $last_month = date('n', $until);
        $last_year = date('Y', $until);
        $week_until = period_end('week', date('W', $until), get_week_year($until));
        $until = mktime(23, 59, 59, date('n', $week_until), date('j', $week_until), date('Y', $week_until));
        $last_week = date('W', $until);
        $last_weekyear = get_week_year($until);


        if($output) {
            echo "\nCalculating ".($this->ignore_events ? 'eventless ' : '')."rankings for weeks and month between ".date('r', $since)." and ".date('r', $until)."\n";
        }

        //get all sessions, we need various subsets of this for various parts of the script
        //this is a lot of data!
        $all_sessions = Models\Session::find(['where' => ['game = ? AND start <= ? AND end > ?' => [$this->game, $until, $since]], 'fields' => [Models\Session::IDFIELD, 'stream', 'start', 'end', 'peak', 'average', 'time', 'vh']]);

        if($output) {
            echo 'Got '.count($all_sessions).' sessions total.'."\n";

        }

        //narrow them down to the ones relevant to the given period
        //also store them per-stream for all-time stats calculation
        $sessions = array();
        $sessions_stream = array();
        foreach($all_sessions as $session) {
            if($session['end'] > $since && $session['start'] <= $until) {
                $sessions[$session[Models\Session::IDFIELD]] = $session;
            }
            $sessions_stream[$session['stream']][] = $session;
        }

        //get extra data for sessions within the month, as this may be needed to determine what part of it needs to be
        //counted - this can be a lot of data so this is why we query it separately from $all_sessions
        $session_data = Models\SessionData::find([
            'make_url' => false,
            'fields' => [Models\SessionData::IDFIELD, 'datapoints'],
            'where' => [Models\SessionData::IDFIELD.' IN ('.implode(', ', array_keys($sessions)).')'],
            'key' => Models\SessionData::IDFIELD
        ]);


        //this is a lot of data we don't need at this point
        unset($all_sessions);

        //see how the sessions map to weeks/months to calculate stats for
        $weeks = array();
        $months = array();
        $max_pause = Config::CHECK_DELAY * 6;

        foreach($sessions as $session) {
            $start_year = date('Y', $session['start']);
            $start_month = date('n', $session['start']);
            $start_week = date('W', $session['start']);
            $start_weekyear = get_week_year($session['start']);
            $end_year = date('Y', $session['end']);
            $end_month = date('n', $session['end']);
            $end_week = date('W', $session['end']);
            $end_weekyear = get_week_year($session['end']);

            //weeks
            if(
                (($start_weekyear == $first_weekyear && $start_week >= $first_week) || $start_weekyear > $first_weekyear) &&
                (($start_weekyear == $last_weekyear && $start_week <= $last_week) || $start_weekyear < $last_weekyear)
            ) {
                $weeks[$start_weekyear][$start_week][] = $session;
            }

            if($start_week != $end_week &&
                (($end_weekyear == $first_weekyear && $end_week >= $first_week) || $end_weekyear > $first_weekyear) &&
                (($end_weekyear == $last_weekyear && $end_week <= $last_week) || $end_weekyear < $last_weekyear)
            ) {
                $weeks[$end_weekyear][$end_week][] = $session;
            }

            //months
            if(
                (($start_year == $first_year && $start_month >= $first_month) || $start_year > $first_year) &&
                (($start_year == $last_year && $start_month <= $last_month) || $start_year < $last_year)
            ) {
                $months[$start_year][$start_month][] = $session;
            }

            if($start_month != $end_month &&
                (($end_year == $first_year && $end_month >= $first_month) || $end_year > $first_year) &&
                (($end_year == $last_year && $end_month <= $last_month) || $end_year < $last_year)
            ) {
                $months[$end_year][$end_month][] = $session;
            }
        }

        //process both months and weeks
        foreach(['month' => $months, 'week' => $weeks] as $identifier => $period_type) {
            foreach($period_type as $year => $year_periods) {
                foreach($year_periods as $period => $sessions) {
                    $start = period_start($identifier, $period, $year);
                    $end = period_end($identifier, $period, $year);
                    $streams = array();
                    $session_count = 0;

                    if($output) {
                        echo "Calculating rankings for ".ucfirst($identifier)." ".$period.", ".$year." (".date('r', $start).' - '.date('r', $end).")\n";
                    }

                    foreach($sessions as $session) {
                        //init stream stats at 0 if not seen before
                        if(!isset($streams[$session['stream']])) {
                            $streams[$session['stream']] = array(
                                'total_viewers' => 0,
                                'total_average' => 0,
                                'total_time' => 0,
                                'peak' => 0
                            );
                        }

                        //just to be a little less verbose
                        $sdata = &$session_data[$session[Models\Session::IDFIELD]];
                        if(!isset($session_data[$session[Models\Session::IDFIELD]]['datapoints'])) {
                            var_dump($session_data[$session[Models\Session::IDFIELD]]);
                            echo $session[Models\Session::IDFIELD]."\n";
                            continue;
                        }
                        $stats = &$streams[$session['stream']];

                        if($this->ignore_events) {
                            $events = Models\EventStream::find(['join' => ['on' => [Models\Event::IDFIELD, 'event'], 'fields' => ['game', 'name'], 'table' => Models\Event::TABLE], 'where' => ['stream = ? AND game = ? AND '.$db->escape_identifier(Models\EventStream::TABLE).'.start < ? AND '.$db->escape_identifier(Models\EventStream::TABLE).'.end > ?' => [$session['stream'], $this->game, $session['end'], $session['start']]]]);
                        } else {
                            $events = array();
                        }

                        if(!$this->ignore_events && $session['start'] >= $start && $session['end'] < $end) {
                            $stats['peak'] = max($stats['peak'], $session['peak']);
                            $stats['total_time'] += $session['time'];
                            $stats['total_viewers'] += $session['average'];
                            $stats['total_average'] += $session['average'] * $session['time'];
                        } else {
                            //if not, recalculate for the part that is within limits
                            //we're not using Interval here because that's just too expensive
                            $datapoints = json_decode($sdata['datapoints'], true);
                            ksort($datapoints);

                            //calculate average
                            unset($previous);
                            $session_time = 0;
                            $session_viewers = 0;

                            foreach($datapoints as $time => $viewers) {
                                $real_time = $time + $session['start'];
                                if($real_time < $start || $real_time >= $end) {
                                    unset($datapoints[$time]);
                                    continue;
                                }

                                foreach($events as $event) {
                                    if($real_time >= $event['start'] && $real_time < $event['end']) {
                                        unset($datapoints[$time]);
                                        continue 2;
                                    }
                                }
                                if(isset($previous)) {
                                    $interval_time = $time - $previous['time'];
                                    $interval_average = ($viewers + $previous['viewers']) / 2;

                                    //only count this interval between two points if it's below 6 minutes
                                    //if not, it (probably) means the stream wasn't running at that time,
                                    //so don't count it
                                    if($interval_time < $max_pause) {
                                        $session_viewers += ($interval_average * $interval_time);
                                        $session_time += $interval_time;
                                    }
                                }
                                $previous = array('time' => $time, 'viewers' => $viewers);
                                $stats['peak'] = max($stats['peak'], $viewers);
                            }

                            if(count($datapoints) == 0 || $session_time == 0) { //session has no relevant datapoints
                                continue;
                            }

                            $times = array_keys($datapoints);
                            $average = ($session_viewers / $session_time);
                            $time = max($times) - min($times);
                            $stats['total_average'] += $average * $time;
                            $stats['total_time'] += $time;
                            $stats['total_viewers'] += $average;
                        }

                        $session_count += 1;
                    }

                    $db->start_transaction();
                    foreach($streams as $stream => $stream_stats) {
                        if($stream_stats['total_time'] == 0) {
                            continue;
                        }
                        $stream_stats['total_average'] /= $stream_stats['total_time'];

                        $db->query("DELETE FROM ranking_".$identifier.$this->suffix." WHERE game = ".$db->escape($this->game)." AND ".$identifier." = ".intval($period)." AND year = ".intval($year)." AND stream = ".$db->escape($stream));
                        $db->insert('ranking_'.$identifier.$this->suffix, [
                            'game' => $this->game,
                            $identifier => $period,
                            'year' => $year,
                            'stream' => $stream,
                            'average' => round($stream_stats['total_average']),
                            'peak' => $stream_stats['peak'],
                            'vh' => round(($stream_stats['total_time'] * $stream_stats['total_average']) / 3600),
                            'time' => $stream_stats['total_time']
                        ]);
                    }
                    $db->commit(); //we need to commit here to be able to determine the order

                    if($output) {
                        echo "Processed ".$session_count." sessions, determining order for ".ucfirst($identifier)." ".$period.", ".$year."       \n";
                    }

                    //determine actual ranking now that the stats are accurate
                    $this->determine_order($identifier, $year, $period);
                }
            }

            if($output) {
                echo "\nCalculating deltas.";
            }

            $this->calculate_deltas($identifier);

            if($output) {
                echo "\n".ucfirst($identifier)."s done.\n";
            }
        }
    }


    /**
     * (Re-)calculate ranks for all streams within given period
     *
     * This method calculates the ranks for all (active) streams between the given epoch and terminus. Note that this
     * also reclaculates the <em>statistics</em> for all streams, which can be very CPU/RAM-intensive. Use `rank_since`
     * instead if you only want to update ranks for recently active streams.
     *
     * @param string $rank_by        Statistic to rank by, defaults to V*H.
     * @param null   $streams        Streams to rank; defaults to all streams
     * @param bool   $exclude_events Whether to include events in the stats or not
     * @param bool   $do_return      Return stats instead of storing them in the database?
     */
    public function rank($rank_by = 'vh', $streams = NULL, $exclude_events = false, $do_return = false) {
        global $db;

        $years = array();
        for($y = date('Y', $this->epoch); $y <= date('Y', $this->terminus); $y += 1) {
            $years[$y] = array();
        }

        $weeks = $years;
        $months = $years;

        echo 'Creating week and month map...'."\n";
        foreach($years as $year => $m) {
            if($year == date('Y', $this->epoch)) {
                $max_week = ($year == date('Y', $this->terminus)) ? date('W', $this->terminus) : units_per_year('week', $year);
                $max_month = ($year == date('Y', $this->terminus)) ? date('n', $this->terminus) : units_per_year('month', $year);
                for($i = intval(date('W', $this->epoch)); $i <= $max_week; $i += 1) {
                    $weeks[$year][$i] = $i;
                }
                for($i = intval(date('n', $this->epoch)); $i <= $max_month; $i += 1) {
                    $months[$year][$i] = $i;
                }
            } elseif($year == date('Y', $this->terminus)) {
                $min_week = ($year == date('Y', $this->epoch)) ? date('W', $this->epoch) : 1;
                $min_month = ($year == date('Y', $this->epoch)) ? date('n', $this->epoch) : 1;
                for($i = $min_week; $i <= date('W', $this->terminus); $i += 1) {
                    $weeks[$year][$i] = $i;
                }
                for($i = $min_month; $i <= date('n', $this->terminus); $i += 1) {
                    $months[$year][$i] = $i;
                }
            } else {
                for($i = 1; $i <= units_per_year('week', $year); $i += 1) {
                    $weeks[$year][$i] = $i;
                }
                for($i = 1; $i <= 12; $i += 1) {
                    $months[$year][$i] = $i;
                }
            }
        }

        //sessions
        echo "Retrieving stream list...\n";
        $sessions = Models\Session::find([
            'fields' => [Models\Session::IDFIELD, 'stream'],
            'where' => [
                '(start > '.$this->epoch.' AND start < '.$this->terminus.') OR (end > '.$this->epoch.' AND end < '.$this->terminus.')'
            ],
            'key' => 'stream'
        ]);
        $streams = array_keys($sessions);

        //weeks
        echo "Calculating weekly rank...\n";
        $i = 0;
        foreach($weeks as $y => $all) {
            foreach($all as $w) {
                echo $w.', '.$y."\n";
                $db->query("DELETE FROM ranking_week WHERE game = '".$this->game."' AND year = ".$y." AND week = ".$w);
                foreach($streams as $stream) {
                    $i += 1;
                    if($i % 29 == 0) {
                        $len = strlen($i) + 3;
                        echo "\033[".$len."D";
                        echo $i.'...';
                    }
                    $interval = new Interval($stream, period_start('week', $w, $y), period_end('week', $w, $y), false, $this->game);
                    if($interval->get_peak() > 0) {
                        $db->insert('ranking_week', [
                            'game' => $this->game,
                            'stream' => $stream,
                            'peak' => $interval->get_peak(),
                            'average' => floor($interval->get_average()),
                            'vh' => floor($interval->get_vh()),
                            'week' => $w,
                            'year' => $y,
                            'time' => $interval->get_length()
                        ]);
                    }
                }
            }
        }
        echo "\n";

        //months
        echo "Calculating monthly rank...\n";
        $i = 0;
        foreach($months as $y => $all) {
            foreach($all as $m) {
                echo $m.', '.$y."\n";
                $db->query("DELETE FROM ranking_month WHERE game = '".$this->game."' AND year = ".$y." AND month = ".$m);
                foreach($streams as $stream) {
                    $i += 1;
                    if($i % 29 == 0) {
                        $len = strlen($i) + 3;
                        echo "\033[".$len."D";
                        echo $i.'...';
                    }
                    $interval = new Interval($stream, period_start('month', $m, $y), period_end('month', $m, $y));
                    if($interval->get_peak() > 0) {
                        $db->insert('ranking_month', [
                            'game' => $this->game,
                            'stream' => $stream,
                            'peak' => $interval->get_peak(),
                            'average' => floor($interval->get_average()),
                            'vh' => floor($interval->get_vh()),
                            'month' => $m,
                            'year' => $y,
                            'time' => $interval->get_length()
                        ]);
                    }
                }
            }
        }
        echo "\n";

        echo "Determining order...\n";
        $this->determine_order('week');
        $this->determine_order('month');

        echo "Calculating deltas...\n";
        $this->calculate_deltas('week');
        $this->calculate_deltas('month');

        echo "Done in ".(time() - $this->start)." seconds!\n";
    }


    /**
     * Calculate all-time ranks
     *
     * This only takes finished sessions into account.
     *
     * @access  public
     *
     * @param null $streams
     */
    public function rank_alltime($output = false) {
        global $db, $cache;

        $cutoff = $cache->get('last_rank_alltime_'.$this->game);
        if(!$cutoff) {
            $cutoff = time() - 86400;
        }

        if($output) {
            echo "Calculating all-time rankings for game ".$this->game."\n";
        }

        $streams = Models\Stream::find(['make_url' => false, 'fields' => [Models\Stream::IDFIELD], 'mapping_function' => function($a) {
            return $a[Models\Stream::IDFIELD];
        }, 'where' => ['last_seen > ?' => [$cutoff]]]);
        $streams = array_map(function($a) use ($db) {
            return $db->escape($a);
        }, $streams);

        if($output) {
            echo count($streams)." streams active since last update (".date('r', $cache->get('last_rank_alltime_'.$this->game)).")\n";
        }

        $i = 0;
        $sessions = Models\Session::find(['order_by' => ['end' => 'ASC'], 'where' => ['game = ? AND end > ?' => [$this->game, $cache->get('last_rank_alltime_'.$this->game)]]]);
        foreach($sessions as $session) {
            $i += 1;
            $ranking = $db->fetch_single("SELECT * FROM rank_alltime WHERE game = ".$db->escape($this->game)." AND stream = ".$session['stream']);
            if($ranking) {
                $ranking['peak'] = max($ranking['peak'], $session['peak']);
                $ranking['vh'] += $session['vh'];
                $ranking['time'] += $session['time'];
                $ranking['average'] = round(($ranking['average'] + $session['average']) / ($ranking['time'] + $session['time']));
                $db->query("UPDATE rank_alltime SET peak = ".$db->escape($ranking['peak']).", vh = ".$db->escape($ranking['vh']).", time = ".$db->escape($ranking['time']).", average = ".$db->escape($ranking['average'])." WHERE id = ".$db->escape($ranking['id']));
            } else {
                $db->insert('rank_alltime', [
                    'stream' => $session['stream'],
                    'game' => $this->game,
                    'peak' => $session['peak'],
                    'vh' => $session['vh'],
                    'time' => $session['time'],
                    'average' => $session['average']
                ]);
            }
        }

        if($output) {
            echo "Done. ".$i." sessions processed.\n";
        }

        $cache->set('last_rank_alltime_'.$this->game, time());
    }


    /**
     * Determine order and change wrt previous
     *
     * @param   string $type Either `week` or `month` depending on what period to
     *                       calculate deltas for
     *
     * @param bool     $year
     * @param bool     $period
     *
     * @acces   public
     */
    public function determine_order($type, $year = false, $period = false) {
        global $db;

        if(!!$period && !!$year) {
            $bit = ' AND '.$type.' = '.intval($period).' AND year = '.$year;
        } else {
            $bit = '';
        }

        //get all records, ordered by V*H
        $ranking = $db->fetch_all("SELECT id, ".$type.", year FROM ranking_".$type.$this->suffix." WHERE game = '".$this->game."'".$bit." ORDER BY year ASC, ".$type." ASC, vh DESC, average DESC");
        $previous = array(0, 0);
        $rank = 1;

        foreach($ranking as $row) {
            //simply number from 1 to n in order we got from the database,
            //which is already correct - start again each new period
            if($row[$type] != $previous[0] || $row['year'] != $previous[1]) {
                $rank = 1;
            } else {
                $rank += 1;
            }
            $db->query("UPDATE ranking_".$type.$this->suffix." SET rank = ".$rank." WHERE id = ".$row['id']);
            $previous = array($row[$type], $row['year']);
        }
    }


    /**
     * Calculate deltas - change in rank over weeks
     *
     * @param   string $type Either `week` or `month` depending on what period to
     *                       calculate deltas for
     *
     * @access  public
     */
    public function calculate_deltas($type) {
        global $db;

        $ranking = $db->fetch_all("SELECT id, ".$this->rank_type.", rank, ".$type.", year FROM ranking_".$type.$this->suffix." WHERE game = '".$this->game."' ORDER BY ".$this->rank_type." ASC, year ASC, ".$type." ASC");
        $previous = array();

        $db->start_transaction();
        foreach($ranking as $row) {
            //what's the previous time period? account for year change, etc
            if($row[$type] == 1) {
                $previous_period = units_per_year($type, $row['year'] - 1);
                $previous_year = $row['year'] - 1;
            } else {
                $previous_period = $row[$type] - 1;
                $previous_year = $row['year'];
            }

            //only calculate delta if there was a ranking in the period directly preceding this one
            $is_gap = !(
                isset($previous[$row[$this->rank_type]])
                && $previous[$row[$this->rank_type]][$type] == $previous_period
                && $previous[$row[$this->rank_type]]['year'] == $previous_year
            );

            if(!$is_gap) {
                $delta = $row['rank'] - $previous[$row[$this->rank_type]]['rank'];
                $db->update('ranking_'.$type.$this->suffix, array('delta' => $delta), 'id = '.$row['id']);
            }

            //save this value for comparison
            $previous[$row[$this->rank_type]] = $row;
        }
        $db->commit();
    }
}