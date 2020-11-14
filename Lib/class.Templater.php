<?php
/**
 * Templating engine
 *
 * @package j2o-site
 */
namespace Fuzic\Site;

use Fuzic\Lib;
use Fuzic\Models;


/**
 * Extension to Smarty templating engine with various site-specific enhancements
 */
class Templater extends \Smarty
{
    /**
     * @var string  Output buffer
     */
    private $buffer;
    /**
     * @var array   CSS files to include in page
     */
    private $extra_CSS;
    /**
     * @var array   javascript files to include in page
     */
    private $extra_JS;
    /**
     * @var array   notes to display on page
     */
    private $notes;
    /**
     * @var array   Breadcrumb chain
     */
    private $breadcrumbs;
    /*
     * @var array	  Notices to be displayed on the page
     */
    private $notices = array();
    /**
     * @var string  Display mode
     */
    private $display_mode = 'undefined';
    /**
     * @var object  Reference to error handler
     */
    private $error_handler;
    /**
     * @var object  Reference to user object
     */
    private $user;
    /**
     * @var object  Reference to database interface
     */
    private $db;
    /**
     * @var object  Reference to cache handler
     */
    private $cache;

    /**
     * Set up templating engine
     *
     * Defines Smarty configuration variables and internal variables for
     * later use; also pre-assigns some useful variables.
     *
     * @param  User    $user_object Object representing currently
     *                              active user. Defaults to `null`.
     * @param  mysqldb $db          Database interface.
     *
     *
     * @param          $cache
     *
     * @internal param ErrorHandler $error_handler Error handler.
     * @access   public
     */
    public function __construct(&$user_object, &$db, &$cache) {
        parent::__construct();

        $this->template_dir = ROOT.'/Site/templates/';
        $this->compile_dir = ROOT.'/Site/templates/compiled/';
        $this->config_dir = dirname(__FILE__).'/Smarty-3.1.18/config/';
        $this->cache_dir = ROOT.'/Site/cache/smarty/';

        $this->caching = false;
        $this->cache = $cache;

        $this->assign('app_name', 'fuzic');
        $this->buffer = '';
        $this->breadcrumbs = array();

        $this->extra_CSS = array(array('href' => '//netdna.bootstrapcdn.com/font-awesome/4.1.0/css/font-awesome.min.css')
        );
        $this->extra_JS = array(array('src' => '//ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js')
        );

        if (defined('WEBROOT')) {
            $this->assign('__urlpath', WEBROOT);
        } else {
            $this->assign('__urlpath', '');
        }

        $this->error_handler = &$error_handler;
        $this->db = &$db;

        if ($user_object && $user_object instanceof Lib\Model) {
            $this->user = &$user_object;
            $this->assign('__userdata', $user_object->get_all_data());
            $this->assign('__user', $user_object);
        }

        $this->load_custom_plugins();
    }

    /**
     * Load custom modifiers
     *
     * Looks in `/lib/SmartyPlugins` for valid modifier files and loads them
     *
     * @access  protected
     */
    protected function load_custom_plugins() {
        $dir = dir(ROOT.'/Site/SmartyPlugins');
        while (false !== ($file = $dir->read())) {
            if ($file != '.' && $file != '..') {
                include ROOT.'/Site/SmartyPlugins/'.$file;
                $plugin = explode('.', $file);
                $this->registerPlugin($plugin[0], $plugin[1], 'smarty_'.$plugin[0].'_'.$plugin[1]);
            }
        }
    }

    /**
     * Set custom template directory
     *
     * @param   string $path Path to the directory containing template files
     */
    public function set_template_dir($path) {
        $this->template_dir = $path;
    }

    /**
     * Set buffer contents
     *
     * @param   string $source What to set the buffer to
     *
     * @access  public
     */
    public function set_buffer($source) {
        $this->buffer = $source;
    }

    /**
     * Add CSS to page
     *
     * @param   string $href File name of the CSS file
     *
     * @access  public
     */
    public function add_css($href) {
        $href = (substr($href, 0, 1) == '/') ? $href : $this->getTemplateVars('__urlpath').'/assets/style/'.$href;
        $this->extra_CSS[] = array('href' => $href);
    }

    /**
     * Add javascript to page
     *
     * @param   string $src File name of the Javascript file
     *
     * @access  public
     */
    public function add_js($src) {
        $src = (substr($src, 0, 1) == '/' || substr($src, 0, 4) == 'http') ? $src : $this->getTemplateVars('__urlpath').'/assets/scripts/'.$src;
        $this->extra_JS[] = array('src' => $src);
    }

    /**
     * Add note to display on page
     *
     * @param   string $text Note text
     *
     * @access  public
     */
    public function add_note($text) {
        $this->notes[] = $text;
    }

    /**
     * Parse template and add to buffer
     *
     * @param   string $file Template to parse
     *
     * @access  public
     */
    public function add_to_buffer($file) {
        $this->buffer .= $this->fetch($file);
    }

    /**
     * Redirect to URL
     *
     * @param   string $url URL to redirect to
     *
     * @access  public
     */
    public function redirect($url) {
        header('Location: '.$this->getTemplateVars('__urlpath').$url);
        exit;
    }

    /**
     * Adds breadcrumbs
     *
     * Adds items to the page's breadcrumb trail, in the reverse order of how they are
     * passed to this method (the first argument is the last breadcrumb). Items can be:
     * - Objects, in which case they are assumed to be an `Model` and the URL and label
     *   of the item is extracted.
     * - Arrays, in which case the `__url` and `name` keys are used as URL and page name
     *   respecitvely
     *
     * @param   mixed $source
     *
     * @access  public
     */
    public function add_breadcrumbs($source) {
        $args = func_get_args();

        while (null !== ($crumb = array_pop($args))) {
            if (is_object($crumb)) {
                $url = $crumb->get_url();
                $label = $crumb->get_label();
            } elseif (is_array($crumb) && (isset($crumb['name']) || isset($crumb['real_name']))) {
                $url = $crumb['__url'];
                $label = isset($crumb['real_name']) ? 'real_name' : 'name';
                $label = $crumb[$label];
            } else {
                continue;
            }
            $this->add_breadcrumb($label, $url);
        }
    }

    /**
     * Adds a single breadcrumb to the trail
     *
     * @param   string $label Page name
     * @param   string $url   Page URL
     *
     * @access  public
     */
    public function add_breadcrumb($label, $url) {
        $this->breadcrumbs[] = array('label' => $label, 'url' => $url);
    }

    public function confirm() {
        if (isset($_GET['confirmed'])) {
            return true;
        }

        parse_str($_SERVER['QUERY_STRING'], $res);
        $res['confirmed'] = 1;

        $url = explode('?', $_SERVER['REQUEST_URI']);
        $this->assign('url', $url[0].'?'.http_build_query($res));
        $this->assign('postdata', $_POST);
        $this->layout('layout/confirm.tpl');

        exit;
    }

    public function add_notice($message) {
        $this->notices[] = ['text' => $message];
    }

    /*
     * Set display mode
     *
     * @param   string    $mode       Display mode
     */
    public function set_display_mode($mode = 'undefined') {
        $this->display_mode = $mode;
    }


    public function add_twittercard($values) {
        $html = '';
        foreach ($values as $key => $value) {
            $html .= '  <meta name="twitter:'.$key.'" content="'.$value.'">'."\n";
        }
        $this->assign('__twittercard', $html);
    }

    /**
     * Set page title
     *
     * @param   string $title Page title
     *
     * @access public
     */
    public function set_title($title) {
        $this->assign('__title', $title);
    }

    /**
     * Check whether a template exists
     *
     * @param   string $path Path to template
     *
     * @return  boolean         Whether it exists
     *
     * @access  public
     */
    public function template_exists($path) {
        foreach ($this->template_dir as $folder) {
            if (is_readable($folder.$path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Show error message
     *
     * @param   string $error The error message
     *
     * @access  public
     */
    public function error($error) {
        $this->assign('error', $error);
        $this->layout('layout/error.tpl');
    }

    /**
     * Show output buffer in site layout
     *
     * Passes several variables - CSS files, JS files, breadcrumbs, output buffer - to the
     * site layout template file, which is then parsed and displayed.
     *
     * @param   string $file Optional; if specified, this template file will be parsed
     *                       and added to the buffer before further processing.
     *
     * @access public
     */
    public function layout($file = '') {
        global $session, $__parsetime;

        $this->assign('__breadcrumbs', $this->breadcrumbs);
        $this->assign('__css', $this->extra_CSS);
        $this->assign('__javascript', $this->extra_JS);
        $this->assign('__notices', $this->notices);
        $this->assign('__session', $session);
        $this->assign('__calendar', $this->cache->get('nav-calendar'));


        if ($this->display_mode == 'undefined' && !empty($file)) {
            $this->display_mode = explode('/', $file);
            $this->display_mode = array_pop($this->display_mode);
            $this->display_mode = explode('.', $this->display_mode);
            $this->display_mode = array_shift($this->display_mode);
        }

        $this->assign('__displaymode', $this->display_mode);

        //get active notices
        /*
        $notices = Models\Notice::find([
            'where' => [
                'user = ? AND (expires = 0 OR expires > ?)' => [$this->user->get_ID(), time()]
            ],
            'order_by' => 'id',
            'order' => 'DESC'
        ]);
        $notices += $this->notices;
        $this->assign('__notices', $notices);
        */

        //parse page template
        if (!empty($file)) {
            $this->add_to_buffer($file);
            $class = explode('/', $file);
            $class = explode('.', array_pop($class));
            $this->assign('__bodyclass', $class[0]);
        } else {
            $this->assign('__bodyclass', 'fuzic');
        }
        $this->assign('__body', '  '.str_replace("\n<", "\n    <", $this->buffer));

        //display the whole shebang
        if ($this->display_mode == 'async') {
            echo $this->buffer;
        } else {
            $this->assign('__parsetime', microtime(true) - $__parsetime);
            $this->assign('__queries', $this->db->query_count);
            $this->display('layout/layout.tpl');
        }
        $this->buffer = '';

        exit;
    }

    /**
     * Output JSON response
     *
     * @param  array $data Data to display
     *
     * @access public
     */
    public function json_response($data) {
        echo json_encode($data);
        exit;
    }


}