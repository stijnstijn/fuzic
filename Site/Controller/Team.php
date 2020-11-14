<?php
/**
 * Team controller
 *
 * @package Fuzic-site
 */
namespace Fuzic\Site;

use Fuzic\Lib;
use Fuzic\Models;


/**
 * Team contoller
 */
class TeamController extends Lib\Controller
{
    const REF_CLASS = 'Team';

    /**
     * Called before template display
     */
    protected function before_display() {
        $this->tpl->add_breadcrumb('Teams', '/teams/');
        $this->tpl->add_CSS('stream.css');
    }

    /**
     * Show team data
     *
     * @access  public
     */
    public function single() {
        $teamID = $this->parameters['id'];

        try {
            $team = new Models\Team(['url' => $teamID]);
        } catch (Lib\ItemNotFoundException $e) {
            $this->tpl->error('That team does not exist.');
        }

        $streams = Models\Stream::find([
            'team' => $team->get_ID(),
            'order_by' => 'last_seen'
        ]);

        $this->tpl->set_title($team->get('team'));
        $this->before_display();
        $this->tpl->add_breadcrumbs($team);

        $this->tpl->assign('streams', $streams);
        $this->tpl->assign('team', $team->get_all_data());

        $this->tpl->layout('Team/single.tpl');
    }

    /**
     * Show team overview
     *
     * @access  public
     */
    public function overview() {
        $view = $this->get_view($_GET);

        foreach ($view['items'] as $i => $data) {
            $view['items'][$i]['players'] = Models\Stream::find([
                'where' => ['team = ? AND team != ?' => [$data[Models\Team::IDFIELD], Models\Team::TEAMLESS_ID]],
                'order_by' => 'last_seen',
                'order' => 'desc'
            ]);
        }

        $this->tpl->set_title('Teams');
        $this->before_display();
        $this->tpl->assign('view_settings', $view);
        $this->tpl->assign('teams', $view['items']);

        $this->tpl->layout('Team/overview.tpl');
    }


}