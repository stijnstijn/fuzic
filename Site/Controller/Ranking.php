<?php
/**
 * Ranking controller
 *
 * @package Fuzic-site
 */
namespace Fuzic\Site;

use Fuzic\Lib;
use Fuzic\Models;


/**
 * Ranking controller
 */
class RankingController extends Lib\Controller
{
    const REF_CLASS = '';
    const DEFAULT_ORDER = 'ASC';

    /**
     * Show ranking overview
     *
     * @access private
     */
    protected function overview() {
        global $tpl;

        if ($this->parameters['subset'] == 'players') {
            $constraint = ' s.player = 1';
        } elseif ($this->parameters['subset'] == 'casters') {
            $constraint = ' s.player = 0';
        } else {
            $constraint = '';
            $this->parameters['subset'] = '';
        }

        //get data for the page
        $view = $this->get_view(self::filter_view($_GET, ['where' => $constraint]));

        //determine what template to load based on what kind of item we're showing
        $folder = ucfirst($this->parameters['item']);

        //page settings
        $tpl->set_title($folder.' ranking');
        $tpl->add_breadcrumb('Rankings', '/rankings/');
        $tpl->add_breadcrumb($folder.'s', '/rankings/'.$this->parameters['item'].'s/');

        if ($this->parameters['item'] == 'stream') {
            $tpl->assign('subset', $this->parameters['subset']);
        }

        $tpl->assign('ranking', array(
            'type' => $this->parameters['type'],
            'period' => $this->parameters['period'],
            'year' => $this->parameters['year']
        ));
        $tpl->assign('settings', $view);
        $tpl->add_CSS('stream.css');
        $tpl->layout($folder.'/ranking.tpl');
    }

    /**
     * Sanitize view parameters
     *
     * Constructs an array of view settings based on input and educated guesses.
     *
     * @param   array  $parameters Array from which to extract view data. Possible
     *                             keys, all optional:
     *                             - `page`: current page
     *                             - `order`: how items are ordered, either `ASC` or `DESC`
     *                             - `order_by`: the attribute by which items are ordered
     *                             - `filter`: search string, filters on the `LABEL` field of the corresponding class
     * @param   string $view_id    ID of the view. Page and order parameters will be
     *                             ignored if the `in` parameter is not the same as this ID.
     *
     * @return  array   Array with view settings, with the following keys:
     * - `page`: the current page - defaults to 1
     * - `offset`: the item offset, based on current page and `PAGE_SIZE`
     * - `order`: how items are ordered, either `ASC` or `DESC`
     * - `order_by`: the attribute by which items are ordered, can only contain
     *   alphanumeric characters
     * - `page_count`: amount of pages, based on total number of items and `PAGE_SIZE`
     *
     * @access  public
     */
    public function get_view($parameters = array(), $view_id = '') {
        /** speshul */
        if (isset($this->parameters['week'])) {
            $this->parameters['type'] = 'week';
            $this->parameters['period'] = intval($this->parameters['week']);
        } else {
            $this->parameters['type'] = 'month';
            $this->parameters['period'] = intval($this->parameters['month']);
        }

        $return = array();

        //determine view ID
        $ignore = false;
        if (empty($view_id)) {
            $class = get_called_class();
            $class = explode('\\', $class);
            $class = array_pop($class);
            $view_id = str_replace('controller', '', strtolower($class));
        }
        if (isset($parameters['in']) && $parameters['in'] != $view_id) {
            $ignore = true;
        }

        //apply search
        $return['query'] = isset($parameters['query']) ? preg_replace('/[^a-zA-Z0-9_ ]/si', '', $parameters['query']) : '';
        $field = $this->parameters['item'] == 'event' ? 'e.name' : 's.real_name';
        $search = !empty($return['query']) ? ' AND '.$field." LIKE ".$this->db->escape('%'.$return['query'].'%') : '';

        if (!isset($parameters['params'])) {
            $parameters['params'] = array();
        }

        //get item count
        if (!empty($this->parameters['subset'])) {
            $playersonly = ' AND s.player = '.($this->parameters['subset'] == 'players' ? '1' : '0').' ';
        } else {
            $playersonly = '';
        }

        if ($this->parameters['item'] == 'stream') {
            if (!empty($this->parameters['subset'])) {
                $playersonly = ' AND s.player = '.($this->parameters['subset'] == 'players' ? '1' : '0').' ';
            } else {
                $playersonly = '';
            }
            $item_count = ceil($this->db->fetch_field("
            SELECT COUNT(*)
              FROM ranking_".$this->parameters['type']."  AS r,
                   ".Models\Stream::TABLE." AS s
             WHERE r.game = '".ACTIVE_GAME."'
               AND r.".$this->parameters['type']." = ".$this->parameters['period']."
               AND r.year = ".$this->parameters['year']."
               AND r.stream = s.".Models\Stream::IDFIELD.
                $search.$playersonly));
        } else {
            $item_count = ceil($this->db->fetch_field("
            SELECT COUNT(*)
              FROM ranking_".$this->parameters['type']."_event  AS r,
                   ".Models\Event::TABLE." AS e
             WHERE r.game = '".ACTIVE_GAME."'
               AND r.".$this->parameters['type']." = ".$this->parameters['period']."
               AND r.year = ".$this->parameters['year']."
               AND r.event = e.".Models\Event::IDFIELD.
                $search));

        }

        if (isset($parameters['params']['limit']) && $item_count > $parameters['params']['limit']) {
            $item_count = $parameters['params']['limit'];
        }

        //calculate page numbers and sanitize other input, apply defaults where appropriate
        $page_size = isset($parameters['page_size']) ? intval($parameters['page_size']) : static::PAGE_SIZE;
        $page = !$ignore && (isset($parameters['page']) && is_numeric($parameters['page'])) ? abs(intval($parameters['page'])) : 1;
        $page_count = ceil($item_count / $page_size);
        $default_order = $this->get_default_order_by();

        $return['offset'] = ($page - 1) * $page_size;
        $return['order'] = (!$ignore && isset($parameters['order']) && in_array($parameters['order'], array('asc', 'desc'))) ? strtoupper($parameters['order']) : static::DEFAULT_ORDER;
        $return['order_by'] = !$ignore && isset($parameters['order_by']) ? preg_replace('/[^a-zA-Z0-9_]/si', '', $parameters['order_by']) : $default_order;
        $return['in'] = $view_id;

        //get page navigation
        $return['pages'] = self::get_pages($page, $page_count);
        $return['pages']['each'] = $page_size;
        $return['count'] = $item_count;

        //filter vars for proper urls
        $return['filters'] = isset($parameters['filters']) ? $parameters['filters'] : array();

        //retrieve items within this view
        if ($this->parameters['item'] == 'stream') {
            $return['items'] = $this->db->fetch_all("
                SELECT s.".Models\Stream::IDFIELD.", s.remote_id, s.player, s.notable, s.real_name AS real_name, r.rank, r.average, r.peak, r.vh, r.time
                  FROM ranking_".$this->parameters['type']." AS r,
                       ".Models\Stream::TABLE." AS s
                 WHERE r.game = '".ACTIVE_GAME."'
                   AND s.".Models\Stream::IDFIELD." = r.stream
                   AND r.".$this->parameters['type']." = ".$this->parameters['period']."
                   AND r.year = ".$this->parameters['year']."
                   ".$search.$playersonly."
                 ORDER BY r.".$return['order_by']." ".$return['order']."
                 LIMIT ".$return['offset'].", ".static::PAGE_SIZE);
        } else {
            $return['items'] = $this->db->fetch_all("
                SELECT e.name, e.".Models\Event::IDFIELD.", e.short_name, e.name AS real_name, r.rank, e.average, e.peak, e.vh, (e.end - e.start) AS time
                  FROM ranking_".$this->parameters['type']."_event AS r,
                       ".Models\Event::TABLE." AS e
                 WHERE r.game = '".ACTIVE_GAME."'
                   AND e.".Models\Event::IDFIELD." = r.event
                   AND r.".$this->parameters['type']." = ".$this->parameters['period']."
                   AND r.year = ".$this->parameters['year']."
                   ".$search."
                 ORDER BY ".$return['order_by']." ".$return['order']."
                 LIMIT ".$return['offset'].", ".static::PAGE_SIZE);
        }


        $return['urlbit'] = '&amp;in='.urlencode($view_id);
        if(!empty($return['query'])) {
            $return['urlbit'] .= '&amp;query='.urlencode($return['query']);
        }
        if($return['order_by'] != $default_order && !is_array($return['order_by'])) {
            $return['urlbit'] .= '&amp;order='.urlencode($return['order']).'&amp;order_by='.urlencode($return['order_by']);
        }
        $return['urlbit'] = strtolower($return['urlbit']);

        return $return;
    }

    protected function get_default_order_by() {
        return 'rank';
    }
}
