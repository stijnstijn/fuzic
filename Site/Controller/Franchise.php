<?php
/**
 * Franchise controller
 *
 * @package Fuzic-site
 */
namespace Fuzic\Site;

use Fuzic\Lib;


/**
 * Franchise contoller
 */
class FranchiseController extends Lib\Controller
{
    const REF_CLASS = 'Franchise';
    const DEFAULT_ORDER = 'ASC';

    /**
     * Called before template vars are assigned and rendered
     */
    public function before_display() {
        $this->tpl->add_CSS('stream.css');
        $this->tpl->add_breadcrumb('Franchises', '/franchises/');
    }

    /**
     * Show franchise overview
     *
     *
     * @access  public
     */
    public function overview() {
        $view = $this->get_view($_GET, "name != ''");

        $this->before_display();
        $this->tpl->set_title('Franchises');

        $this->tpl->assign('view_settings', $view);
        $this->tpl->assign('franchises', $view['items']);

        $this->tpl->layout('Franchise/overview.tpl');
    }

    /**
     * Default order by for views
     *
     * @return    string        Field name by which to order
     *
     * @access protected
     */
    protected function get_default_order_by() {
        return 'name';
    }
}