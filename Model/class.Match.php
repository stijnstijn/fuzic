<?php
/**
 * Match model
 *
 * @package Fuzic
 */
namespace Fuzic\Models;

use Fuzic\Lib;


/**
 * Datapoint model
 */
class Match extends Lib\Model
{
    const TABLE = 'matches';
    const LABEL = 'id';
    const HIDDEN = 1;

    public function get_max_time() {
        $event = new Event($this->get('event'));
        $stream_IDs = $event->get_stream_IDs();
        if (count($stream_IDs) == 0) {
            return false;
        }

        //calculate statistics
        $overall = new Lib\Interval($stream_IDs, $this->get('start'), $this->get('end'), false, $event->get('game'), false, $event->get_ID());
        $datapoints = $overall->get_datapoints();

        $max = 0;
        $timestamp = 0;
        foreach($datapoints as $time => $viewers) {
            if($viewers > $max) {
                $timestamp = $time;
                $max = $viewers;
            }
        }

        return $timestamp;
    }
}