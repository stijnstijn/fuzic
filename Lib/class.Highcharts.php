<?php
/**
 * Chart building
 *
 * @package Fuzic-site
 */
namespace Fuzic\Lib;

use Fuzic\Models;


/**
 * Collection of methods for generating data for highcharts-based charts
 */
class Highcharts
{
    /**
     * Get weekly stats
     *
     * @param   mixed   $stream  Stream to chart
     * @param   integer $amount  How many weeks to include
     * @param   string  $rank_by What to rank by. Can be any of `vh`, `peak`,
     *                           `average`, `rank` (default) or `time`
     * @param   boolean $pad     Whether to pad data. See {@see Interval::pad()}.
     *
     * @param bool      $game
     *
     * @return array Statistics. Array with keys `labels` (week numbers), `years`
     * (year numbers), `data` (datapoints)
     * @throws \ErrorException
     * @access  public
     */
    public static function get_weekly($stream, $amount = 0, $offset = 0, $rank_by = 'rank', $pad = true, $game = false) {
        global $db;

        if(!$game) {
            if(defined('ACTIVE_GAME')) {
                $game = ACTIVE_GAME;
            } else {
                $game = 'sc2';
            }
        }

        $stream = self::get_stream_ID($stream);
        $limit = ($amount > 0) ? ' LIMIT '.intval($offset).', '.intval($amount) : '';

        $weeks = $db->fetch_all_indexed("SELECT *, CONCAT(year, LPAD(week, 2, '0')) AS yw FROM ranking_week WHERE game = ".$db->escape($game)." AND stream = ".$db->escape($stream).' ORDER BY year DESC, week DESC'.$limit, 'yw');
        if(!$weeks) {
            $weeks = array();
        }


        if($weeks && count($weeks) > 0) {
            $latest = reset($weeks);
        } else {
            $week = intval(date('W'));
            $year = get_week_year(time());
            for($i = 0; $i < $offset; $i += 1) {
                if($week > 1) {
                    $week -= 1;
                } else {
                    $year -= 1;
                    $week = units_per_year('week', $year);
                }
            }
            $latest = array('year' => $year, 'week' => $week);
        }

        if ($pad) {
            $result = [];
            $w = $latest['week'];
            $y = $latest['year'];
            for ($i = 0; $i < $amount; $i += 1) {
                $key = $y.str_pad($w, 2, '0', STR_PAD_LEFT);

                if (isset($weeks[$key])) {
                    $result[] = $weeks[$key];
                } else {
                    $result[] = array('week' => $w, 'year' => $y, 'rank' => 0, 'average' => 0, 'peak' => 0, 'vh' => 0, 'time' => 0);
                }
                if ($w == 1) {
                    $y -= 1;
                    $w = units_per_year('week', $y);
                } else {
                    $w -= 1;
                }
            }
        }

        $result = array_reverse($result);
        $last = end($result);
        $first = reset($result);

        $return = array('data' => array(), 'labels' => array());
        $return['type'] = ($rank_by == 'vh') ? 'VH' : ucfirst($rank_by);

        foreach ($result as $week) {
            $return['labels'][] = intval($week['week']);
            $return['years'][] = intval($week['year']);
            $return['data'][] = intval($week[$rank_by]);
        }

        if($last['year'] == $first['year']) {
            $return['first'] = date('\W\e\ek W', period_start('week', $first['week'], $first['year']));
            $return['last'] = date('W, Y', period_end('week', $last['week'], $last['year']));
        } else {
            $return['first'] = date('\W\e\ek W, Y', period_start('week', $first['week'], $first['year']));
            $return['last'] = date('W, Y', period_end('week', $last['week'], $last['year']));
        }
        $return['next'] = ($last['year'] == get_week_year() && $last['week'] == intval(date('W'))) ? false : $offset - 1;
        $return['previous'] = $offset + 1;

        return $return;
    }

    /**
     * Get monthly stats
     *
     * @param   mixed   $stream  Stream to chart
     * @param   integer $amount  How many months to include
     * @param   string  $rank_by What to rank by. Can be any of `vh`, `peak`,
     *                           `average`, `rank` (default) or `time`
     * @param   boolean $pad     Whether to pad data. See {@see Interval::pad()}.
     *
     * @param bool      $game
     *
     * @return array Statistics. Array with keys `labels` (week numbers), `years`
     * (year numbers), `data` (datapoints)
     * @throws \ErrorException
     * @access  public
     */
    public static function get_monthly($stream, $amount = 0, $offset = 0, $rank_by = 'rank', $pad = true, $game = false) {
        global $db;

        if (!$game) {
            if (defined('ACTIVE_GAME')) {
                $game = ACTIVE_GAME;
            } else {
                $game = 'sc2';
            }
        }

        $stream = self::get_stream_ID($stream);
        $limit = ($amount > 0) ? ' LIMIT '.intval($offset).', '.intval($amount) : '';

        $months = $db->fetch_all_indexed("SELECT *, CONCAT(year, LPAD(month, 2, '0')) AS ym FROM ranking_month WHERE game = ".$db->escape($game)." AND stream = ".$db->escape($stream).' ORDER BY year DESC, month DESC'.$limit, 'ym');

        if($months && count($months) > 0) {
            $latest = reset($months);
        } else {
            $year = date('Y');
            $month = date('n');
            for($i = 0; $i < $offset; $i += 1) {
                if($month > 1) {
                    $month -= 1;
                } else {
                    $month = 12;
                    $year -= 1;
                }
            }
            $latest = array('year' => $year, 'month' => $month);
        }

        if (!$months) {
            $months = array();
        }

        if ($pad) {
            $result = [];
            $y = $latest['year'];
            $m = $latest['month'];
            for ($i = 0; $i < $amount; $i += 1) {
                $key = $y.str_pad($m, 2, '0', STR_PAD_LEFT);
                if (isset($months[$key])) {
                    $result[] = $months[$key];
                } else {
                    $result[] = array('month' => $m, 'year' => $y, 'rank' => 0, 'average' => 0, 'peak' => 0, 'vh' => 0, 'time' => 0);
                }
                if ($m == 1) {
                    $m = 12;
                    $y -= 1;
                } else {
                    $m -= 1;
                }
            }
        }

        $result = array_reverse($result);
        $last = end($result);
        $first = reset($result);

        $return = array('data' => array(), 'labels' => array());
        $return['type'] = ($rank_by == 'vh') ? 'VH' : ucfirst($rank_by);

        $months = array('', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December');
        foreach ($result as $month) {
            $month_n = intval($month['month']);
            $month_n = ($month_n < 1) ? (12 + $month_n) : $month_n;
            $return['labels'][] = $months[$month_n].', '.$month['year'];
            $return['years'][] = intval($month['year']);
            $return['data'][] = intval($month[$rank_by]);
        }

        if($last['year'] == $first['year']) {
            $return['first'] = date('F', period_start('month', $first['month'], $first['year']));
            $return['last'] = date('F, Y', period_end('month', $last['month'], $last['year']));
        } else {
            $return['first'] = date('F, Y', period_start('month', $first['month'], $first['year']));
            $return['last'] = date('F, Y', period_end('month', $last['month'], $last['year']));
        }

        $return['next'] = ($last['year'] == date('Y') && $last['month'] == date('n')) ? false : $offset - 1;
        $return['previous'] = $offset + 1;

        return $return;
    }

    /**
     * Get yearly stats
     *
     * @param   mixed   $stream  Stream to chart
     * @param   integer $amount  How many years to include
     * @param   string  $rank_by What to rank by. Can be any of `vh`, `peak`,
     *                           `average`, `rank` (default) or `time`
     * @param   boolean $pad     Whether to pad data. See {@see Interval::pad()}.
     *
     * @param bool      $game
     *
     * @return array Statistics. Array with keys `labels` (week numbers), `years`
     * (year numbers), `data` (datapoints)
     * @throws \ErrorException
     * @access  public
     */
    public static function get_yearly($stream, $amount = 0, $rank_by = 'rank', $pad = true, $game = false) {
        global $db;

        if (!$game) {
            if (defined('ACTIVE_GAME')) {
                $game = ACTIVE_GAME;
            } else {
                $game = 'sc2';
            }
        }

        $stream = self::get_stream_ID($stream);
        $limit = ($amount > 0) ? ' LIMIT '.intval($amount) : '';

        $weeks = $db->fetch_all_indexed("SELECT * FROM ranking_week WHERE game = ".$db->escape($game)." AND stream = ".$db->escape($stream).' ORDER BY year DESC, week DESC'.$limit, 'week');

        if (!$weeks) {
            $weeks = array();
        }

        if ($pad) {
            $this_week = intval(date('W'));
            $start = $this_week - $amount;

            $result = [];

            for ($i = $start; $i <= $this_week; $i += 1) {
                if (isset($weeks[$i])) {
                    $result[] = $weeks[$i];
                } else {
                    $result[] = array('week' => $i, 'year' => 2014, 'rank' => 0, 'average' => 0, 'peak' => 0, 'vh' => 0, 'time' => 0);
                }
            }
        } else {
            $result = array_reverse($weeks);
        }

        $return = array('data' => array(), 'labels' => array());
        $return['type'] = ($rank_by == 'vh') ? 'VH' : ucfirst($rank_by);

        foreach ($result as $week) {
            $return['labels'][] = intval($week['week']);
            $return['years'][] = intval($week['year']);
            $return['data'][] = intval($week[$rank_by]);
        }

        return $return;
    }

    /**
     * Get session viewer chart
     *
     * @param   integer $session Session ID
     * @param   boolean $mild    If set to `true` (default `false`), the amount
     *                           of datapoints is kept below 1000 for less resource-intensive processing on the
     *                           website. Every second datapoint is removed, and if the amount of points is
     *                           still above 1000 after that the procedure is repeated until it isn't.
     *
     * @return  array   Statistics. Array with keys `labels` (times), `data` (viewer numbers)
     *
     * @access  public
     */
    public static function get_session($session_data, $mild = false) {
        if (!is_object($session_data)) {
            $session_data = new Models\SessionData($session_data);
        }

        $datapoints = json_decode($session_data->get('datapoints'), true);

        ksort($datapoints);
        while (count($datapoints) > 1000) {
            $i = 0;
            foreach ($datapoints as $key => $value) {
                if ($i % 2 == 0) {
                    unset($datapoints[$key]);
                }
                $i += 1;
            }
        }

        $return = array(
            'data' => array_values($datapoints),
            'labels' => array_keys($datapoints)
        );

        return $return;
    }

    /**
     * Get event chart
     *
     * @param   mixed $event Event
     *
     * @return array    Statistics. Array with keys `labels` (week numbers), `years`
     * (year numbers), `data` (datapoints), `legend` (streams)
     *
     * @access  public
     */
    public static function get_event($event, $reverse_sort = false) {
        if (!is_object($event)) {
            $event = new Models\Event($event);
        }

        $streams = Models\EventStream::find([
            'event' => $event->get_ID(),
            'order_by' => 'viewers'
        ]);

        $viewers = array();
        $stream_data = array();
        $legend = array();
        foreach ($streams as $stream) {
            $interval = new Interval($stream, $event->get('start'), $event->get('end'), false, $event->get('game'), false, $event->get_ID());
            $stream_info = Models\Stream::find([
                'where' => [Models\Stream::IDFIELD.' = ?' => [$stream['stream']]],
                'return' => Model::RETURN_SINGLE
            ]);
            $stream_info += array(
                'peak' => $interval->get_peak(),
                'average' => $interval->get_average(),
                'vh' => $interval->get_vh(),
                'time' => $interval->get_length(),
                'start' => $stream['start'],
                'end' => $stream['end']
            );
            $stream_data[$stream_info[Models\Stream::IDFIELD]] = $stream_info;
            $viewers[$stream['stream']] = $interval->get_datapoints();
        }

        uksort($viewers, function ($a, $b) use ($stream_data, $reverse_sort) {
            if($reverse_sort) {
                return ($stream_data[$a]['vh'] > $stream_data[$b]['vh']) ? 1 : -1;
            } else {
                return ($stream_data[$a]['vh'] < $stream_data[$b]['vh']) ? 1 : -1;
            }
        });
        uasort($stream_data, function ($a, $b) use ($reverse_sort) {
            if($reverse_sort) {
                return ($a['vh'] > $b['vh']) ? 1 : -1;
            } else {
                return ($a['vh'] < $b['vh']) ? 1 : -1;
            }
        });

        foreach ($stream_data as $stream) {
            $legend[] = $stream['real_name'];
        }

        $viewers = Interval::combine($viewers);

        return array(
            'data' => array_values($viewers),
            'labels' => array_keys($viewers),
            'streams' => $stream_data,
            'legend' => $legend
        );
    }


    /**
     * Get stream ID from parameter, whether it is an ID, Stream object, array of stream data or array of other data
     *
     * @param  mixed $stream Variable to extract Stream ID from
     *
     * @return  integer  Stream ID
     * @throws  \ErrorException  Throws an exception if no stream ID could be found
     *
     * @access  public
     * @package Fuzic
     */
    public static function get_stream_ID($stream) {
        if (is_numeric($stream)) {
            $stream_ID = intval($stream);
        } elseif (is_object($stream) && strpos(get_class($stream), 'Stream') !== false) {
            $stream_ID = $stream->get_ID();
        } elseif (is_array($stream) && isset($stream[Models\Stream::IDFIELD])) {
            $stream_ID = $stream[Models\Stream::IDFIELD];
        } elseif (is_array($stream) && isset($stream['stream'])) {
            $stream_ID = $stream[Models\Stream::IDFIELD];
        } elseif (is_integer($stream)) {
            return intval($stream);
        } elseif (is_string($stream)) {
            $stream = preg_replace('/([^a-zA-Z0-9-]*)/siU', '', $stream);
            return $stream;
        } else {
            throw new \ErrorException('No valid Stream ID given');
        }
        return $stream_ID;
    }
}