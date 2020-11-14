<?php
/**
 * Calculate data for a given interval and stream set
 *
 * @package Fuzic
 */
namespace Fuzic\Lib;

use Fuzic;
use Fuzic\Models;


/**
 * Calculate data for a given interval and stream set
 */
class Interval
{
    /**
     * @var  array  Datapoints in this interval
     */
    private $datapoints = array();
    /**
     * @var  integer  Start of this interval
     */
    private $start;
    /**
     * @var  integer  End of this interval
     */
    private $end;

    /**
     * Gather datapoints for given interval
     *
     * @param   mixed   $input         Streams to work with. Can be many things:
     *                                 - Array of arrays: with each array containing a record for a session
     *                                 - Array of stream IDs
     *                                 - Stream ID
     *                                 - Stream object
     *                                 - Session object: to calculate statistics for that session (overrides
     *                                 `$start` and `$end`)
     *
     * @param bool|int  $start         UNIX timestamp of interval start
     * @param bool|int  $end           UNIX timestamp of interval end
     * @param   boolean $pad           Whether to pad the interval when the found sessions
     *                                 and datapoints do not fully cover the specified interval
     * @param boolean   $ignore_events Whether to ignore datapoints that are marked as being during an event.
     * @param boolean   $only_events   Whether to ignore datapoints that are NOT marked as being during an event.
     *
     * @param string    $game
     *
     * @throws \ErrorException
     * @access  public
     */
    public function __construct($input, $start = false, $end = false, $pad = false, $game = 'sc2', $ignore_events = false, $only_events = false) {
        global $db;

        if (defined('ACTIVE_GAME')) {
            $game = ACTIVE_GAME;
        }

        //if input is an array of sessions, use those as base data
        if (is_array($input) && isset($input[0]) && isset($input[0]['datapoints'])) {
            $sessions = $input;
            $unprocessed = array();
            $stream = false;
        }
        //if input is not an ID but a session object, infer parameters from
        //the session data
        elseif (is_object($input) && strpos(get_class($input), 'Session') !== false) {
            $sessions = array($input->get_all_data());
            $start = !$start ? $input->get('start') : $start;
            $end = !$end ? $input->get('end') : $end;
            $unprocessed = array();
            $stream = $input->get('stream');
        }
        //else, use input as stream ID and retrieve relevant sessions from
        //database
        else {
            $end = intval($end);
            $start = intval($start);
            //if stream is a stream object, get the ID from that object
            if (is_object($input) && strpos(get_class($input), 'Stream') !== false) {
                $stream = $input->get_ID();
                $input = $db->escape($input->get_ID());
            } elseif (is_array($input)) {
                if(isset($input['stream'])) {
                    $stream = $input['stream'];
                    $input = $db->escape($input['stream']);
                } elseif(isset($input[Models\Stream::IDFIELD])) {
                    $stream = $input[Models\Stream::IDFIELD];
                    $input = $db->escape($input[Models\Stream::IDFIELD]);
                } else {
                    $input = implode(', ', array_map(array($db, 'escape'), $input));
                    $stream = false;
                }
            } else {
                $stream = $input;
                $input = $db->escape($input);
            }

            //get all sessions within this time frame from the database
            $sessions = Models\Session::find([
                'fields' => ['start', 'end'],
                'join' => ['table' => Models\SessionData::TABLE, 'on' => [Models\SessionData::IDFIELD, Models\Session::IDFIELD], 'fields' => ['datapoints']],
                'make_url' => false,
                'where' => "stream IN (".$input.")
                   AND game = '".$game."'
			       AND (start <= ".$end." AND end > ".$start.")"
            ]);

            //and all unprocessed datapoints (for unfinished sessions)
            $unprocessed = Models\Datapoint::find([
                'fields' => ['time', 'viewers'],
                'make_url' => false,
                'where' => 'stream IN ('.$input.') AND time >= '.$start.' AND time <= '.$end." AND game = '".$game."'"
            ]);
        }

        //from sessions, include all datapoints within the time frame
        foreach ($sessions as $session) {
            //skip those sessions that are definitely out of bounds
            if ($session['start'] > $end || $session['end'] < $start) {
                continue;
            }

            $session_points = json_decode($session['datapoints'], true);
            if (!$session_points) {
                continue;
            }
            foreach ($session_points as $time => $viewers) {
                $time += $session['start'];
                if ($time >= $start && $time < $end) {
                    if (isset($this->datapoints[$time])) {
                        $this->datapoints[$time] += $viewers;
                    } else {
                        $this->datapoints[$time] = $viewers;
                    }
                }
            }
        }

        //and put them together with the unprocessed datapoints
        foreach ($unprocessed as $datapoint) {
            if (isset($this->datapoints[$datapoint['time']])) {
                $this->datapoints[$datapoint['time']] += $datapoint['viewers'];
            } else {
                $this->datapoints[$datapoint['time']] = $datapoint['viewers'];
            }
        }

        //if needed, throw out the datapoints during events
        //warning, expensive!
        if ($ignore_events) {
            $s_end = ($end == 0) ? time() : $end;
            $coverage = Models\EventStream::find_between($start, $s_end, $stream);
            foreach ($this->datapoints as $time => $viewers) {
                foreach ($coverage as $event_stream) {
                    if ($time >= $event_stream['start'] && $time < $event_stream['end']) {
                        unset($this->datapoints[$time]);
                    }
                }
            }
        }

        //only events
        //similar
        $keep = array();
        if ($only_events != false) {
            $stream = $stream ? ' = '.$db->escape($stream) : ' IN ('.$input.')';
            $event_ID = (is_numeric($only_events) && $only_events !== false) ? $only_events : false;
            $coverage = Models\EventStream::find(['where' => ['stream '.$stream.' AND event = ?' => [$event_ID]]]);

            foreach ($this->datapoints as $time => $viewers) {
                foreach ($coverage as $event_stream) {
                    if ($time >= $event_stream['start'] && $time < $event_stream['end']) {
                        $keep[$time] = true;
                    }
                }
            }

            foreach($this->datapoints as $time => $viewers) {
                if(!isset($keep[$time])) {
                    unset($this->datapoints[$time]);
                }
            }
        }

        //sort them by timestamp so we have a chronological list of viewer numbers
        ksort($this->datapoints);

        $this->end = $end;
        $this->start = $start;

        //pad with 0 viewers if needed
        if ($pad) {
            $this->pad($start, $end);
        }
    }

    /**
     * Exclude datapoints within given boundaries
     *
     * @param $start
     * @param $end
     */
    public function exclude($start, $end) {
        foreach ($this->datapoints as $key => $value) {
            if ($key >= $start && $key <= $end) {
                unset($this->datapoints[$key]);
            }
        }
    }


    /**
     * Pad the interval's datapoints
     *
     * Pad the datapoints to span the whole interval, even if there was no recorded
     * activity (in which case a viewer count of 0 will be assumed)
     *
     * @param   integer $start Start of padding
     * @param   integer $end   End of padding
     *
     * @return  array   Padded datapoints
     *
     * @access  private
     */
    private function pad($start, $end) {
        //first pad outside the the recorded datapoints
        $times = array_keys($this->datapoints);
        if (count($times) == 0) {
            return;
        }

        $i = $start;
        $max = (round(max($times) - $start / Fuzic\Config::CHECK_DELAY, 0) * Fuzic\Config::CHECK_DELAY) + $start;
        while ($i < $end) {
            if ($i < ($times[0] - Fuzic\Config::CHECK_DELAY) || $i >= $max) {
                $this->datapoints[$i] = 0;
            } else {
                $i = $max;
            }
            $i += Fuzic\Config::CHECK_DELAY;
        }

        //sort again
        ksort($this->datapoints);
        $times = array_keys($this->datapoints);

        //pad the array internally, as well
        $new = array();

        //boundaries for one missed check
        $twice = Fuzic\Config::CHECK_DELAY * 2;
        $andahalf = Fuzic\Config::CHECK_DELAY * 1.5;

        while ($current = current($times)) {
            $new[$current] = $this->datapoints[$current];
            $next = next($times);

            //if there is no new datapoint for over 3 minutes, but the end has not been
            //reached yet, pad the gap
            if (!$next) {
                break;
            }
            $difference = $next - $current;
            if ($difference > $andahalf) {
                if ($difference < $twice) {
                    $new[$current + floor($difference / 2)] = 0;
                } else {
                    $pad = $current;
                    while ($pad < ($next - Fuzic\Config::CHECK_DELAY)) {
                        $pad += Fuzic\Config::CHECK_DELAY;
                        $new[$pad] = 0;
                    }
                }
            }
        }

        $this->datapoints = $new;
    }

    /**
     * Get datapoints for this interval
     *
     * @return  array  The datapoints, as an array with times as key and viewers as value
     */
    public function get_datapoints() {
        return $this->datapoints;
    }

    /**
     * Get average viewer count for this interval
     *
     * @return  integer
     */
    public function get_average() {
        $average = $this->get_average_and_length();
        return $average['average'];
    }

    /**
     * Get total time streamed during interval
     *
     * @return  integer
     */
    public function get_length() {
        $average = $this->get_average_and_length();
        return $average['length'];
    }

    /**
     * Get average and total running time
     *
     * This is a combined helper function because both calculations are mostly identical
     *
     * @return  array  The time and viewer count, as 'time' and 'viewers' array keys
     */
    private function get_average_and_length() {
        $total_time = 0;
        $total_viewers = 0;

        $max_pause = Fuzic\Config::CHECK_DELAY * 6;

        foreach ($this->datapoints as $time => $viewers) {
            if (isset($previous)) {
                $interval_time = $time - $previous['time'];
                $interval_average = ($viewers + $previous['viewers']) / 2;

                //only count this interval between two points if it's below 6 minutes
                //if not, it (probably) means the stream wasn't running at that time,
                //so don't count it
                if ($interval_time < $max_pause) {
                    $total_viewers += ($interval_average * $interval_time);
                    $total_time += $interval_time;
                }
            }

            $previous = array('time' => $time, 'viewers' => $viewers);
        }

        if ($total_time == 0) {
            return array('average' => 0, 'length' => 0);
        } else {
            return array('average' => ($total_viewers / $total_time), 'length' => $total_time);
        }
    }

    /**
     * Get stats in an array
     *
     * @return  array  Array with 'average', 'peak', 'vh', 'time', 'start', 'end' as items
     */
    public function get_stats() {
        $average_and_length = $this->get_average_and_length();

        return array(
            'average' => $average_and_length['average'],
            'peak' => $this->get_peak(),
            'vh' => $this->get_vh(),
            'time' => $average_and_length['length'],
            'start' => $this->start,
            'end' => $this->end,
            'datapoints' => count($this->datapoints)
        );
    }

    /**
     * Get peak viewer count for this interval
     *
     * @return  integer
     */
    public function get_peak() {
        if (count($this->datapoints) == 0) {
            return 0;
        } else {
            return max($this->datapoints);
        }
    }

    /**
     * Get V*H (viewer hours) metric for this interval
     *
     * @return  integer
     */
    public function get_vh() {
        if (count($this->datapoints) == 0) {
            return 0;
        }

        $data = $this->get_average_and_length();

        return ($data['length'] * $data['average']) / 3600;
    }

    /**
     * Combine interval datapoints
     *
     * @param  array $intervals The intervals to combine, as an array of datapoint sets
     *
     * @return  array  A new datapoint sets, with aggregrate viewer counts
     */
    public static function combine($intervals) {
        $times = array();
        $empty = array();

        //set up an array with all times at which datapoints were recorded
        foreach ($intervals as $stream => $interval) {
            $times += $interval;
            $empty[$stream] = 0;
        }
        ksort($times);

        //set the margin within which no datapoint may occur for two datapoints
        //to be considered consecutive
        $margin = ceil(Fuzic\Config::CHECK_DELAY * 4);

        //loop through timestamps to pad them if needed
        $padded_times = array();
        while ($current = key($times)) {
            $time = key($times);
            $next = next($times);
            $padded_times[$time] = $empty;

            //don't do anything if this is the last timnestamp
            if (!$next) {
                continue;
            }

            //see if next timestamps is within the margin
            $next_time = key($times);
            $difference = $next_time - $time;

            //if not, add dummy timestmaps until the sequence is 
            //evenly spaced again
            while ($difference >= $margin) {
                $time += Fuzic\Config::CHECK_DELAY;
                $difference -= Fuzic\Config::CHECK_DELAY;
                $padded_times[$time] = $empty;
            }
        }

        //make an array with viewer number for each timestamp per
        //stream
        foreach ($intervals as $stream => $interval) {
            foreach ($interval as $time => $viewers) {
                $padded_times[$time][$stream] = intval($viewers);
            }
        }

        return $padded_times;
    }
}