<?php
/**
 * Event rankings
 *
 * @package Fuzic
 */
namespace Fuzic\Lib;

use Fuzic\Models;


/**
 * Calculate rankings
 */
class EventRanking extends Ranking
{
    /**
     * Set up ranking and normalize times
     *
     * @param   integer $epoch    The start of the interval to analyze, as UNIX
     *                            timestamp
     * @param   integer $terminus The end of the interval to analyze, as UNIX
     *                            timestamp
     *
     * @param string    $game
     *
     * @access  public
     */
    public function __construct($epoch = 0, $terminus = 0, $game = '') {
        parent::__construct($epoch, $terminus, 'event', $game);
        $this->game = preg_replace('/[^a-z0-9]/siU', '', $game);
    }


    /**
     * Calculate event ranking per week/month
     *
     * @param   string $rank_by By what metric to rank events by. Defaults
     *                          to `vh`, can also be `peak`, `average` or `time`
     *
     * @param null     $streams
     * @param bool     $exclude_events
     * @param bool     $do_return
     *
     * @throws \ErrorException
     * @access  public
     */
    public function rank($rank_by = 'vh', $streams = null, $exclude_events = false, $do_return = false) {
        global $db;

        //sort by Viewers * Hours by default, or any other valid option
        if (!in_array($rank_by, array('vh', 'peak', 'average', 'time'))) {
            $rank_by = 'vh';
        }

        //loop through ranking types (week, month, ...)
        foreach (array('month' => 'n', 'week' => 'W') as $type => $identifier) {
            $year = date('Y', $this->epoch);
            $period = date($identifier, $this->epoch);

            //go through the periods of this type within the boundaries
            while (!(($period > date($identifier, $this->terminus) && $year == date('Y', $this->terminus)) || $year > date('Y', $this->terminus))) {
                $db->delete('ranking_'.$type.'_event', 'year = '.$year.' AND '.$type.' = '.$period.' AND game = '.$db->escape($this->game));

                $id_var = ($type == 'month') ? '%m' : '%v';

                //get events that were active within this period, sort by metric
                $events = Models\Event::find([
                    'constraint' => "game = '".$this->game."' AND FLOOR(DATE_FORMAT(FROM_UNIXTIME(start), '".$id_var."')) = ".$period." AND FLOOR(DATE_FORMAT(FROM_UNIXTIME(start), '%Y')) = ".$year,
                    'order_by' => $rank_by,
                    'order' => 'DESC'
                ]);


                //the result we got *is* the ranking, so just use that
                $rank = 1;
                foreach ($events as $event) {
                    if ($event['peak'] > 0) {
                        $db->insert('ranking_'.$type.'_event', array(
                            'game' => $this->game,
                            'event' => $event[Models\Event::IDFIELD],
                            $type => $period,
                            'year' => $year,
                            'rank' => $rank
                        ));
                        $rank += 1;
                    }
                }

                //increase the period pointer...
                if ($period == units_per_year($type, $year)) {
                    $period = 1;
                    $year += 1;
                } else {
                    $period += 1;
                }
            }
        }
    }


    /**
     * Calculate event ranking
     *
     * No separate table; for ease of access event stats are stored in the
     * event table itself.
     *
     * @access  public
     *
     * @param null $streams
     *
     * @throws \ErrorException
     */
    public function rank_alltime($streams = null) {
        global $db;

        //get events within time period
        $events = Models\Event::find([
            'constraint' => "game = '".$this->game."' AND start > ".$this->epoch,
            'return' => 'object'
        ]);

        $db->start_transaction();
        foreach ($events as $event) {
            //get linked streams
            $stream_IDs = $event->get_stream_IDs();
            if (count($stream_IDs) == 0) {
                continue;
            }

            //calculate statistics
            $overall = new Interval($stream_IDs, $event->get('start'), $event->get('end'), false, $event->get('game'), false, $event->get_ID());

            //round values
            $stats = array_map(function ($a) {
                return floor($a);
            }, $overall->get_stats());

            //make stats array match database layout
            unset($stats['time'], $stats['end'], $stats['start'], $stats['outage'], $stats['datapoints']);
            $stats['hidden'] = 0;

            $event->set($stats);
            $event->update();
        }
        $db->commit();
    }
}