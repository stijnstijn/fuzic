<?php
/**
 * Session controller
 *
 * @package Fuzic-site
 */
namespace Fuzic\Site;

use Fuzic\Lib;
use Fuzic\Models;


/**
 * Session contoller
 */
class SessionController extends Lib\Controller
{
    const REF_CLASS = 'Session';
    const DEFAULT_ORDER = 'DESC';
    const PAGE_SIZE = 10;

    /**
     * Show session data
     *
     * @throws \ErrorException
     * @internal param int $sessionID The session to show information for
     *
     * @access   public
     */
    public function single() {
        $session = new Models\Session($this->parameters['id']);
        $data = new Models\SessionData($this->parameters['id']);
        $stream = new Models\Stream($session->get('stream'));

        $this->tpl->assign('previous', Models\Session::find(array(
            'where' => 'stream = '.$stream->get_ID().' AND end < '.$session->get('end'),
            'order_by' => 'end',
            'order' => 'DESC',
            'limit' => 1,
            'return' => Lib\Model::RETURN_SINGLE
        )));

        $this->tpl->assign('next', Models\Session::find(array(
            'where' => 'stream = '.$stream->get_ID().' AND start > '.$session->get('start'),
            'order_by' => 'start',
            'order' => 'ASC',
            'limit' => 1,
            'return' => Lib\Model::RETURN_SINGLE
        )));

        $this->tpl->set_title('Session for '.$stream->get('real_name').' at '.date('j F Y', $session->get('start')));
        $this->tpl->add_breadcrumb('Streams', '/streams/');
        $this->tpl->add_breadcrumbs($stream);
        $this->tpl->add_breadcrumb('Session '.$session->get_ID(), $session->get_url());
        $this->tpl->add_CSS('stream.css');
        $this->tpl->add_JS('charts.js');

        if (!empty($stream->get('team'))) {
            $team = new Models\Team($stream->get('team'));
            $this->tpl->assign('team', $team->get_all_data());
        }

        $chart = Lib\Highcharts::get_session($data, true);

        $events = $session->get_events(ACTIVE_GAME);
        $event_controller = new EventController(null);

        $params = ['where' => Models\Event::IDFIELD.' IN ('.implode(',', array_map(function($a) { return $a[Models\Event::IDFIELD]; }, $events)).')'];
        $events = $event_controller->get_view(self::filter_view($_GET, $params));

        $session_data = array_merge($session->get_all_data(), $data->get_all_data());

        $this->tpl->assign('live', false);
        $this->tpl->assign('rank', Lib\Ranking::get_current_week($stream));
        $this->tpl->assign('stats', Lib\Ranking::get_alltime($stream));
        $this->tpl->assign('stream', $stream->get_all_data());
        $this->tpl->assign('events', $events);
        $this->tpl->assign('session', $session_data);
        $this->tpl->assign('chart', $chart);

        $this->tpl->layout('Session/single.tpl');
    }

    /**
     * Default order by for views
     *
     * @return  string    Field name by which to order
     *
     * @access protected
     */
    protected function get_default_order_by() {
        return 'start';
    }
}