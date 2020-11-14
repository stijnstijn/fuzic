<?php
/**
 * Stream controller
 *
 * @package Fuzic-site
 */
namespace Fuzic\Site;

use Fuzic;
use Fuzic\Lib;
use Fuzic\Models;


/**
 * Stream contoller
 */
class StreamController extends Lib\Controller
{
    const REF_CLASS = 'Stream';
    const DEFAULT_ORDER = 'DESC';

    /**
     * Called before template display
     */
    protected function before_display() {
        $this->tpl->add_breadcrumb('Streams', '/streams/');
        $this->tpl->add_CSS('stream.css');
    }

    /**
     * Show stream information
     *
     * @access  public
     */
    protected function single() {
        $stream = new Models\Stream($this->parameters['id']);
        if ($stream->get('team')) {
            try {
                $team = new Models\Team($stream->get('team'));
                $team = $team->get_all_data();
            } catch (\ErrorException $e) {
                $team = false;
            }
        } else {
            $team = false;
        }

        $mode = (isset($_GET['per']) && in_array($_GET['per'], array('week', 'month', 'year'))) ? $_GET['per'] : 'week';
        $this->tpl->assign('stats_per', $mode);

        $stats = Lib\Ranking::get_alltime($stream);
        if ($mode == 'week') {
            $offset = isset($_GET['week-offset']) ? intval($_GET['week-offset']) : 0;
            $rank = Lib\Ranking::get_current_week($stream);
            $progression = Lib\Highcharts::get_weekly($stream->get_ID(), 12, $offset);
        } elseif ($mode == 'month') {
            $offset = isset($_GET['month-offset']) ? intval($_GET['month-offset']) : 0;
            $rank = Lib\Ranking::get_current_month($stream);
            $progression = Lib\Highcharts::get_monthly($stream->get_ID(), 12, $offset);
        } else {
            $rank = Lib\Ranking::get_current_year($stream);
            $progression = Lib\Highcharts::get_yearly($stream->get_ID(), 12);
        }

        $session_controller = new SessionController(null);
        $params = ['where' => ['game = ? AND stream = ?' => [ACTIVE_GAME, $stream->get_ID()]],
                   'join' => ['table' => Models\SessionData::TABLE, 'on' => [Models\SessionData::IDFIELD, Models\Session::IDFIELD], 'fields' => ['title']]];
        $sessions = $session_controller->get_view(self::filter_view($_GET, $params));

        //make sure the Franchise class is declared
        $franchise_check = new Models\Franchise([], true);
        unset($franchise_check);

        $events = $stream->get_events(ACTIVE_GAME);
        $event_controller = new EventController(null);

        $params = ['where' => Models\Event::IDFIELD.' IN ('.implode(',', array_map(function($a) { return $a[Models\Event::IDFIELD]; }, $events)).')'];
        $events = $event_controller->get_view(self::filter_view($_GET, $params));

        $is_live = ((time() - $stream->get('last_seen')) < (Fuzic\Config::CHECK_DELAY * 5)) && ($stream->get('last_game') == ACTIVE_GAME);
        if ($is_live) {
            $current_eventstream = Models\EventStream::find([
                'where' => [
                    'stream = ?' => [$stream->get_ID()],
                    'end > ?' => [time() - (Fuzic\Config::CHECK_DELAY * 5)]
                ],
                'return' => 'single'
            ]);
            if ($current_eventstream) {
                $live_event = new Models\Event($current_eventstream['event']);
                $this->tpl->assign('live_event', $live_event->get_all_data());
            }
            $current_viewers = Models\Datapoint::find([
                'stream' => $stream->get_ID(),
                'game' => ACTIVE_GAME,
                'return' => 'field',
                'fields' => ['viewers'],
                'order_by' => 'time',
                'order' => 'desc',
                'limit' => 1
            ]);
            $current_start = Models\Datapoint::find([
                'game' => ACTIVE_GAME,
                'stream' => $stream->get_ID(),
                'return' => 'field',
                'fields' => ['time'],
                'order_by' => 'time',
                'order' => 'asc',
                'limit' => 1
            ]);

            $live_title = Models\Datapoint::find([
                'where' => [
                    'stream = ?' => [$stream->get_ID()]
                ],
                'order_by' => ['time' => 'DESC'],
                'limit' => 1
            ]);
            $live_title = array_pop($live_title);

            $this->tpl->assign('live_title', $live_title['title']);

            array_unshift($sessions['items'], [
                'start' => $current_start,
                'time' => (time() - $current_start),
                'live' => true
            ]);
        } else {
            $current_viewers = 0;
        }

        //played in matches
        $event_controller = new EventController(null);
        $events_played = $event_controller->get_view(self::filter_view($_GET, ['where' => Models\Event::IDFIELD.' IN ( SELECT event FROM '.Models\Match::TABLE." WHERE game = '".ACTIVE_GAME."' AND (player1 = ".$this->db->escape($stream->get_ID()).' OR player2 = '.$this->db->escape($stream->get_ID()).'))']));

        $this->tpl->set_title($stream->get('real_name'));
        $this->before_display();
        $this->tpl->add_JS('charts.js');

        $this->tpl->add_breadcrumbs($stream);

        $this->tpl->add_twittercard([
            'card' => 'product',
            'title' => $stream->get('real_name'),
            'image' => $stream->get('avatar'),
            'label1' => 'Average viewers',
            'data1' => $stats['average'],
            'label2' => 'Peak viewers',
            'data2' => $stats['peak']
        ]);

        $this->tpl->assign('indie_ID', Models\Franchise::INDIE_ID);
        $this->tpl->assign('rank', $rank);
        $this->tpl->assign('live', $is_live);
        $this->tpl->assign('current_viewers', $current_viewers);
        $this->tpl->assign('sessions', $sessions);
        $this->tpl->assign('stats', $stats);
        $this->tpl->assign('progression', $progression);
        $this->tpl->assign('team', $team);
        $this->tpl->assign('events', $events);
        $this->tpl->assign('events_played', $events_played);
        $this->tpl->assign('stream', $stream->get_all_data());

        $this->tpl->layout('Stream/single.tpl');
    }

    /**
     * Show chart data for stream
     *
     * @access  public
     */
    public function chart() {
        $stream = $this->parameters['id'];
        $type = $this->parameters['type'];
        $data = $this->parameters['data'];

        if ($type == 'week') {
            $stream = new Models\Stream($stream);
            $offset = isset($_GET['week-offset']) ? intval($_GET['week-offset']) : 0;

            $progression = Lib\Highcharts::get_weekly($stream, 12, $offset, $data);
            echo json_encode($progression);
            exit;
        } elseif ($type == 'month') {
            $stream = new Models\Stream($stream);
            $offset = isset($_GET['month-offset']) ? intval($_GET['month-offset']) : 0;

            $progression = Lib\Highcharts::get_monthly($stream, 12, $offset, $data);
            echo json_encode($progression);
            exit;
        }
    }

    /**
     * Show stream overview
     *
     * @access  public
     */
    public function overview() {
        $subset = isset($this->parameters['subset']) && in_array($this->parameters['subset'], ['players', 'casters']) ? $this->parameters['subset'] : '';

        $constraint = "last_game = '".ACTIVE_GAME."'";
        if ($subset == 'players') {
            $constraint .= ' AND player = 1';
        } elseif ($subset == 'casters') {
            $constraint .= ' AND player = 0';
        }

        //make sure the Team class is declared
        $t = new Models\Team([], true);
        unset($t);

        $view = $this->get_view(self::filter_view($_GET, ['where' => $constraint]));

        $this->tpl->set_title('Streams');
        $this->before_display();

        $this->tpl->assign('view_settings', $view);
        $this->tpl->assign('streams', $view['items']);
        $this->tpl->assign('subset', $this->parameters['subset']);
        $this->tpl->assign('teamless_ID', Models\Team::TEAMLESS_ID);
        $this->tpl->assign('check_delay', Fuzic\Config::CHECK_DELAY);

        if ($subset != '') {
            $this->tpl->add_breadcrumb(ucfirst($subset), '/streams/'.$subset.'/');
        }

        $this->tpl->layout('Stream/overview.tpl');
    }


    /**
     * Default order by for views
     *
     * @return  string    Field name by which to order
     *
     * @access protected
     */
    protected function get_default_order_by() {
        return ["(last_game = '".ACTIVE_GAME."')" => 'desc', 'last_seen' => 'desc', 'notable' => 'desc', "wiki != ''" => 'desc', "twitter != ''" => 'desc'];
    }
}
