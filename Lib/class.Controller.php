<?php
/**
 * Controller class
 *
 * @package    cleanroom
 * @package    Fuzic-site
 * @author     stijn / http://www.stijnpeeters.nl
 */
namespace Fuzic\Lib;

use Fuzic\Models;


/**
 * Controller class
 */
abstract class Controller
{
    /**
     * Default user rights (most strict)
     */
    const CREATOR_CAN_EDIT = false;
    const ADMIN_LEVEL = Models\User::LEVEL_ADMIN;
    const CREATOR_LEVEL = Models\User::LEVEL_ADMIN;

    /**
     * Size to be used in overviews
     */
    const PAGE_SIZE = 25;
    const PAGE_SCOPE = 3;
    const DEFAULT_ORDER = 'DESC';

    /**
     * Exception messages
     */
    const ERR_NOT_FOUND = 'Item not found.';
    const ERR_INVALID_PARAM = 'Invalid request mode';
    const ERR_USER_LEVEL = 'User level too low';

    /**
     * @var object    Reference to template handler
     */
    protected $tpl;

    /**
     * @var object    Reference to currently active user
     */
    public $user;

    /**
     * @var object    Reference to database handler
     */
    public $db;

    /**
     * @var array     Data model related to this controller
     */
    public $model;

    /**
     * @var array     Human-readable names of object attributes
     */
    protected $human_names = array();

    /**
     * @var array     Columns users are allowed to filter by via GET queries
     */
    protected $allowed_filter_columns = array();

    /**
     * Constructor
     *
     * @param   array  $parameters The parameters with which this controller
     *                             should work
     * @param   object $tpl        Templating engine (reference)
     *
     * @param null     $db
     * @param null     $user
     *
     * @throws ControllerException If the controller class is improperly
     * configured.
     */
    public function __construct($parameters = array(), $tpl = null, $db = null, $user = null) {
        $this->parameters = $parameters;
        $this->tpl = $tpl;
        $this->db = $db;
        $this->user = $user;

        if (!defined('static::REF_CLASS')) {
            throw new ControllerException('Controller "'.get_called_class().'" missing REF_CLASS');
        }

        if ($this->tpl) {
            $this->model = self::index_models(false, static::REF_CLASS);
            $this->tpl->assign('__model', $this->model);
            $this->tpl->assign('human_names', $this->human_names);
        }

        if (is_array($parameters) && !isset($parameters['mode'])) {
            $parameters['mode'] = NULL;
        }

        $class = new \ReflectionClass($this);
        $method = $class->getMethod("__construct");

        if (!empty($parameters) && $method->class == 'Fuzic\Lib\Controller') {
            switch ($parameters['mode']) {
            default:
                if (is_callable(array($this, $parameters['mode']))) {
                    $method = $class->getMethod($parameters['mode']);
                    if ($method->class != 'Controller' && !$method->isProtected()) {
                        $this->{$parameters['mode']}();
                    } else {
                        throw new ControllerException(self::ERR_INVALID_PARAM);
                    }
                } else {
                    $this->overview();
                }
                break;
            case 'save':
            case 'create':
            case 'new':
            case 'edit':
                if (isset($this->parameters['id'])) {
                    $this->save(array_merge($_GET, $_POST));
                } else {
                    $this->create(array_merge($_GET, $_POST));
                }
                break;
            case 'delete':
                $this->delete();
                break;
            case 'show':
                if (isset($parameters['id']) && !empty($parameters['id'])) {
                    $this->single();
                } else {
                    $this->overview();
                }
                break;
            }
        }
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
        $class = 'Fuzic\Models\\'.static::REF_CLASS;
        $search = !empty($return['query']) ? $class::LABEL." LIKE ".$this->db->escape('%'.$return['query'].'%') : '';

        if (!isset($parameters['params'])) {
            $parameters['params'] = array();
        }
        
        if(isset($parameters['params']['where']) && !is_array($parameters['params']['where']) && !empty($search)) {
            $parameters['params']['where'] = array($parameters['params']['where'], $search);
        } elseif(!empty($search) && is_array($parameters['params']['where'])) {
            $parameters['params']['where'][] = $search;
        }

        //get item count
        $c_params = [
            'where' => $search,
            'return' => Model::RETURN_AMOUNT
        ];

        $safe_params = $parameters['params'];
        unset($safe_params['join']);

        $item_count = $class::find(array_merge($c_params, $safe_params));

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
        $c_params = [
            'where' => $search,
            'limit' => $page_size,
            'offset' => $return['offset'],
            'order_by' => $return['order_by'],
            'order' => $return['order'],
            'follow_keys' => true
        ];

        $return['items'] = $class::find(array_merge($c_params, $parameters['params']));

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

    /**
     * Filter user input for view
     *
     * @param array $get        $_GET
     * @param array $parameters Other parameters to merge into $_GET
     *
     * @return array  Filtered parameters to be passed as the first argument to `get_view()`
     *
     * @access public
     */
    public static function filter_view($get, $parameters) {
        foreach ($get as $key => $value) {
            if (!in_array($key, array('in', 'page', 'order', 'order_by', 'in', 'query'))) {
                unset($get[$key]);
            }
        }

        return array_merge($get, array('params' => $parameters));
    }

    /**
     * Get set of pages to link to
     *
     * @param   integer $page       Current page
     * @param   integer $page_count Total amount of pages
     *
     * @return  array   Numeric array, with the page numbers to be linked as items.
     * `false` values indicate a 'break' in page sequence, e.g. if there is a large
     * number of pages only the first, last and those surrounding the current page
     * will need to be linked to. In between those will be a `false` item to indicate
     * a place to put, for example, an ellipsis.
     *
     * @access  public
     */
    public static function get_pages($page, $page_count) {
        if ($page_count < 10) {
            $pages = array();
            for ($i = 1; $i <= $page_count; $i += 1) {
                $pages[] = $i;
            }
            //if more, truncate some of the list
        } else {
            //first page is always linked
            $pages = array(1);

            //see what other pages are linked
            $start = clamp(($page - self::PAGE_SCOPE), 2, $page_count);
            $end = clamp(($page + self::PAGE_SCOPE), 0, $page_count - 1);

            //add ellipsis between 1 and second in list if there's a gap
            if ($start > 2) {
                $pages[] = false;
            }

            //add page numbers within boundaries
            for ($i = $start; $i <= $end; $i += 1) {
                $pages[] = $i;
            }

            //add ellipsis between second-to-last and last in list if there's a gap
            if ($i < ($page_count - 1)) {
                $pages[] = false;
            }

            //last page is always linked
            $pages[] = $page_count;
        }

        return array(
            'links' => $pages,
            'count' => $page_count,
            'page' => $page
        );
    }

    /**
     * Indexes available data models
     *
     * @param   boolean $ignore_hidden Include all models, even those with class
     *                                 const HIDDEN set tot `true`
     * @param   string  $class         Class to return info about, rather than all
     *                                 of them
     * @param   string  $key           What to use as key for the resulting array.
     *                                 Can be either 'canonical' (canonical class name) or 'table' (database table)
     *
     * @return  array                   Available data models. Array mapped as follows:
     * `canonical name` (to use in, for example, URLs)
     * - `class`: Name of the PHP class
     * - `table`: Database table in which data is stored
     * - `idfield`: Name of the database table field containing the item ID
     * - `item_label`: Name of the database table field containing the item name
     * - `canonical`: Same as key
     * - `name`: Name of the model to be used in e.g. page headers
     * In case the `$class` parameter is set, a one-dimensional array is returned instead.
     *
     * @access  public
     */
    public static function index_models($ignore_hidden = false, $class = '', $key = 'canonical') {
        $models_dir = dir(ROOT.'/Model/');
        $models = array();

        $files = array();

        if (empty($class)) {
            while (false !== ($file = $models_dir->read())) {
                $files[] = $file;
            }
        } else {
            $files[] = 'class.'.$class.'.php';
        }

        foreach ($files as $file) {
            if (preg_match('/class\.([^.]+)\.php/', $file, $model_name)) {
                $model_name[1] = 'Fuzic\Models\\'.$model_name[1];
                if ($ignore_hidden && defined($model_name[1].'::HIDDEN') && constant($model_name[1].'::HIDDEN') == 1) {
                    continue;
                }
                $canonical = defined($model_name[1].'::NAME') ? $model_name[1]::NAME : $model_name[1]::TABLE;
                $canonical = str_replace('_', '-', $canonical);
                $label = defined($model_name[1].'::HUMAN_NAME') ? $model_name[1]::HUMAN_NAME : str_replace(['-', '_'], ' ', $canonical);
                $model_key = ($key == 'canonical') ? $canonical : $model_name[1]::TABLE;
                $models[$model_key] = [
                    'class' => $model_name[1],
                    'table' => $model_name[1]::TABLE,
                    'idfield' => $model_name[1]::IDFIELD,
                    'item_label' => $model_name[1]::LABEL,
                    'canonical' => $canonical,
                    'name' => $label
                ];
            }
        }
        $models_dir->close();

        if (empty($class)) {
            return $models;
        } else {
            return array_pop($models);
        }
    }


    /**
     * Check user login status
     *
     * Displays a login form if the user is not logged in, or an error message if the
     * user is logged in but not of the appropriate level
     *
     * @param   $level  integer     Required user level. Should be a `User` class
     *                  constant.
     *
     * @throws  ControllerException If the user does not have the correct level.
     *
     * @access  public
     */
    public static function require_login($level = User::LEVEL_ADMIN) {
        global $user, $tpl;

        if ($user->is_anon()) {
            if (isset($_POST['login_attempt'])) {
                $tpl->add_notice(User::ERR_WRONG_CREDENTIALS);
            }
            $tpl->layout('layout/login_form.tpl');
            exit;
        } elseif (!$user->is_level($level)) {
            throw new ControllerException(self::ERR_USER_LEVEL);
        }
    }


    /**
     * Default order by for views
     *
     * @return  string    Field name by which to order
     *
     * @access protected
     */
    protected function get_default_order_by() {
        $class = 'Fuzic\Models\\'.static::REF_CLASS;
        return $class::IDFIELD;
    }

    /**
     * Define sub-navigation for this type of item
     *
     * To be overridden by child classes.
     */
    protected function subnav() {
        $this->tpl->assign('__subnav', []);
    }

    /**
     * Show overview of all items of this type
     */
    protected function overview() {
        $class = explode('\\', get_called_class());
        $tpl_dir = str_replace('Controller', '', array_pop($class));
        $class = 'Fuzic\Models\\'.$tpl_dir;

        if (defined($class.'::HIDDEN') && constant($class.'::HIDDEN') == 1) {
            throw new ControllerException(self::ERR_INVALID_PARAM);
        }

        $parameters = $_GET;
        $parameters['where'] = array();
        $fields = $class::get_data_structure();
        if (isset($fields['deleted']) && !$this->user->is_level($class::ADMIN_LEVEL)) {
            $parameters['where']['deleted = 0'] = ['relation' => 'AND'];
        }

        if (isset($fields['hidden'])) {
            $parameters['where']['hidden = 0'] = ['relation' => 'AND'];
        }

        if (method_exists($this, 'before_overview')) {
            $parameters = $this->before_overview($parameters);
        }

        if (!isset($parameters['filters'])) {
            $parameters['filters'] = array();
        }

        foreach ($parameters as $key => $value) {
            if (in_array($key, $this->allowed_filter_columns) && !empty($parameters[$key])) {
                $parameters['params']['where']['?? = ?'] = [$key, $parameters['type'], 'relation' => 'AND'];
                unset($parameters['type']);
                $parameters['filters'][$key] = $value;
            }
        }

        $view = $this->get_view($parameters);

        if (method_exists($this, 'filter_overview')) {
            $view = $this->filter_overview($view);
        }

        if (method_exists($this, 'before_display')) {
            list($data, $links) = $this->before_display();
        }

        $this->tpl->assign('view', $view);

        $this->subnav();

        if ($this->tpl->template_exists($tpl_dir.'/overview.tpl')) {
            $this->tpl->layout($tpl_dir.'/overview.tpl');
        } else {
            $this->tpl->layout('generic/overview.tpl');
        }
    }

    /**
     * Display single item.
     *
     * @throws ControllerException   If the item does not exist.
     */
    protected function single() {
        $class = explode('\\', get_called_class());
        $tpl_dir = str_replace('Controller', '', array_pop($class));
        $class = 'Fuzic\Models\\'.$tpl_dir;

        if (defined($class.'::HIDDEN') && constant($class.'::HIDDEN') == 1) {
            throw new ControllerException(self::ERR_INVALID_PARAM);
        }

        $params = [
            'where' => [$class::IDFIELD.' = ?' => [$this->parameters['id']]],
            'return' => Model::RETURN_SINGLE,
            'follow_keys' => true
        ];

        if (method_exists($this, 'before_single')) {
            $params = $this->before_single($params);
        }

        $item = $class::find($params);

        if (!$item) {
            throw new ControllerException(self::ERR_NOT_FOUND);
        }

        $obj = new $class($item, true);

        if (method_exists($this, 'filter_single')) {
            $data = $this->filter_single($obj->get_all_data());
        } else {
            $data = $obj->get_all_data();
        }

        $links = $obj->get_links(false, true);

        if (method_exists($this, 'before_display')) {
            list($data, $links) = $this->before_display();
        }

        if (method_exists($this, 'before_display_single')) {
            list($data, $links) = $this->before_display_single($data, $links);
        }

        $this->tpl->assign('item', $data);
        $this->tpl->assign('links', $links);
        $this->tpl->set_display_mode('single');

        $this->subnav();


        if ($this->tpl->template_exists($tpl_dir.'/single.tpl')) {
            $this->tpl->layout($tpl_dir.'/single.tpl');
        } else {
            $this->tpl->layout('generic/single.tpl');
        }
    }

    /**
     * Edit an item
     *
     * @param $input
     *
     * @throws ControllerException If the item does not exist.
     */
    protected function save($input) {
        $class = explode('\\', get_called_class());
        $tpl_dir = str_replace('Controller', '', array_pop($class));
        $class = 'Fuzic\Models\\'.$tpl_dir;

        if (defined($class.'::HIDDEN') && constant($class.'::HIDDEN') == 1) {
            throw new ControllerException(self::ERR_INVALID_PARAM);
        }

        if (isset($this->parameters['id'])) {
            try {
                $item = new $class($this->parameters['id']);
            } catch (ItemNotFoundException $e) {
                throw new ControllerException($e->getMessage());
            }

            $user_ID = $this->user->get_ID();
            if (!($class::CREATOR_CAN_EDIT && $user_ID == $item->get('user')) && !$this->user->is_level($class::ADMIN_LEVEL)) {
                throw new ControllerException(User::ERR_NO_PRIVILEGES);
            }

            $this->tpl->set_display_mode('edit');

            if (!empty($input)) {
                $errors = array();

                if (method_exists($item, 'filter_save')) {
                    $filtered = $item->filter_save($input, $this);
                } else {
                    $filtered = array();
                }

                //set non-special attributes, as long as it's allowed
                foreach ($input as $field => $value) {
                    if (!in_array($field, $item->readonly) && $item->has_attribute($field)) {
                        unset($input[$field]);
                    }
                }

                $input = array_merge($input, $filtered);

                foreach ($input as $field => $value) {
                    $validated = $class::validate($field, $value);
                    if (!$validated) {
                        $errors[] = $field;
                    } else {
                        $item->set($field, $validated);
                    }
                }

                //remove deleted pivot items from database
                if (isset($item->__pivot) && isset($input['deleted-items'])) {
                    $deleted = json_decode($input['deleted-items']);
                    foreach ($deleted as $pivot_class => $items) {
                        if (in_array($pivot_class, $item->__pivot)) {
                            foreach ($items as $item) {
                                try {
                                    $item = new $pivot_class($item);
                                    $item->delete();
                                } catch (ItemNotFoundException $e) {
                                    $errors[] = 'Pivoted item could not be deleted.';
                                }
                            }
                        }
                    }
                }

                if (isset($input['deleted-items'])) {
                    unset($input['deleted-items']);
                }

                //for all pivoted items, see if they're new or just changed and process their data
                if (isset($item->__pivot)) {
                    $changed = array();
                    $new = array();

                    foreach ($item->__pivot as $pivot_class) {
                        $pivot_structure = $pivot_class::get_data_structure();
                        unset($pivot_structure[$item::TABLE]);

                        //basically, pivoted items are to be submitted with the following field name format:
                        //[pivot class]-[index]-[attr], e.g. attachment-1-id
                        //if [attr] is id, the pivoted item already exists, and the value of the field is its id
                        //if not, and no id is given for a particular [index], the item is new and will be added
                        //to the database
                        foreach ($input as $field => $value) {
                            if (strpos($field, $pivot_class.'-') === 0) {
                                unset($input[$field]);
                                $ex_key = explode('-', $field);
                                if (isset($ex_key[3])) {
                                    continue;
                                }
                                $pivot_index = $ex_key[1];
                                if ($ex_key[2] == 'id' && !empty($value) && $value != 0) {
                                    $changed[$pivot_class][$pivot_index][$ex_key[2]] = $value;
                                    if (isset($new[$pivot_class][$pivot_index])) {
                                        $changed[$pivot_class][$pivot_index] = array_merge($new[$pivot_class][$pivot_index], $changed[$pivot_class][$pivot_index]);
                                        unset($new[$pivot_class][$pivot_index]);
                                    }
                                } elseif (isset($changed[$pivot_class][$pivot_index])) {
                                    $changed[$pivot_class][$pivot_index][$ex_key[2]] = $value;
                                } elseif (!empty($value)) {
                                    $new[$pivot_class][$pivot_index][$ex_key[2]] = $value;
                                }
                            }
                        }
                    }

                    //create new pivoted items
                    foreach ($new as $pivot_class => $items) {
                        foreach ($items as $item) {
                            if (isset($item[$pivot_class::IDFIELD]) && empty($item[$pivot_class::IDFIELD])) {
                                unset($item[$pivot_class::IDFIELD]);
                            }
                            if (!isset($item[$item::TABLE])) {
                                $item[$item::TABLE] = $item->get_ID();
                            }
                            $pivot_class::create($item);
                        }
                    }

                    //update edited pivoted items
                    foreach ($changed as $pivot_class => $items) {
                        foreach ($items as $item) {
                            try {
                                $item_object = new $pivot_class($item[$pivot_class::IDFIELD]);
                                unset($item[$pivot_class::IDFIELD]);
                                $item_object->set($item);
                                $item_object->update();
                            } catch (ItemNotFoundException $e) {
                                $errors[] = 'Pivoted item could not be updated.';
                            }
                        }
                    }
                }

                $item->update();

                $this->subnav();

                if (method_exists($this, 'before_display')) {
                    list($data, $links) = $this->before_display();
                }

                if (!empty($errors)) {
                    $this->tpl->assign('errors', $errors);
                    $this->tpl->error('Item was saved, but with the following errors.');
                } else {
                    $this->tpl->add_notice($this->user, 'The item was edited succesfully.');
                    $this->tpl->redirect($item->get_url());
                }
            } else {
                $this->tpl->assign('item', $item->get_all_data());
                $this->tpl->assign('structure', $item->get_form_settings());

                if ($this->tpl->template_exists($tpl_dir.'/input.tpl')) {
                    $this->tpl->layout($tpl_dir.'/input.tpl');
                } else {
                    $this->tpl->layout('generic/input.tpl');
                }
            }
        }
    }

    /**
     * Create an item
     *
     * @param   array $input Form input.
     *
     * @throws  ControllerException   If the user does not have the right
     * privileges to create an item of this type.
     */
    protected function create($input) {
        $class = explode('\\', get_called_class());
        $tpl_dir = str_replace('Controller', '', array_pop($class));
        $class = 'Fuzic\Models\\'.$tpl_dir;

        if (!defined($class.'::ALLOW_CREATE_BARE') || (defined($class.'::HIDDEN') && constant($class.'::HIDDEN') == 1)) {
            throw new ControllerException(self::ERR_INVALID_PARAM);
        }

        if (!$this->user->is_level($class::CREATOR_LEVEL)) {
            throw new ControllerException(User::ERR_NO_PRIVILEGES);
        }

        if (!empty($input)) {
            $errors = array();
            $item = $class::create([], true);

            foreach ($input as $field => $value) {
                if (!$item->has_attribute($field) || in_array($field, $item->readonly)) {
                    unset($input[$field]);
                }
            }

            if (method_exists($item, 'filter_create')) {
                $filtered = $item->filter_create($input, $this, $errors);
            } else {
                $filtered = array();
            }

            $input = array_merge($input, $filtered);

            foreach ($input as $field => $value) {
                $validated = $class::validate($field, $value);
                if ($validated === false) {
                    $errors[] = $field;
                } else {
                    $item->set($field, $validated);
                }
            }

            if (isset($item->__pivot)) {
                foreach ($item->__pivot as $pivot_class) {
                    $items = array();
                    $fields = $pivot_class::get_data_structure();
                    ksort($fields);
                    foreach ($fields as $key => $value) {
                        //ignore id fields and value that links it to this object; those
                        //will be set automatically
                        if ($key == $pivot_class::IDFIELD || $key == $class::TABLE) {
                            unset($fields[$key]);
                        }
                    }

                    //loop through submitted form data and save relevant values
                    foreach ($input as $key => $value) {
                        if (strpos($key, $pivot_class.'-') === 0) {
                            $ex_key = explode('-', $key);
                            $id = intval($ex_key[1]);
                            //if the key has 4 components, it's a control parameter, so
                            //ignore it - else, save it
                            if (isset($fields[$ex_key[2]]) && !isset($ex_key[3])) {
                                $items[$id][$ex_key[2]] = $value;
                            }

                            //unset so the script doesn't choke on it later
                            unset($input[$key]);
                        }

                    }

                    //create new items
                    foreach ($items as $data) {
                        $data_keys = array_keys($data);
                        sort($data_keys);

                        //update if the given data matches the required data
                        if ($data_keys = $fields) {
                            $pivot_items[$pivot_class][] = $data;
                        } else {
                            $errors[] = 'Insufficient data for pivoted item of type '.$pivot_class;
                        }
                    }
                }
            }

            if (empty($errors)) {
                if ($item->has_attribute('timestamp')) {
                    $item->set('timestamp', time());
                }
                $item->update();

                //create pivot items only now we're sure the item ID is known
                foreach ($pivot_items as $pivot_class => $items) {
                    foreach ($items as $item) {
                        try {
                            $pivot_class::create(array_merge($item, [$class::TABLE => $item->get_ID()]));
                        } catch (ErrorException $e) {
                            $this->tpl->add_notice('Could not create pivoted item of type '.$class.': '.$e->getMessage());
                        }
                    }
                }

                Notice::create($this->user, 'The item was created succesfully.', 10);
                $this->tpl->redirect($item->get_url());
            } else {
                $this->tpl->assign('errors', $errors);
            }
        }

        $this->subnav();

        if (method_exists($this, 'before_display')) {
            list($data, $links) = $this->before_display();
        }

        if (!isset($item)) {
            $item = new $class([], true);
        }
        $this->tpl->assign('structure', $item->get_form_settings());

        if ($this->tpl->template_exists($tpl_dir.'/input.tpl')) {
            $this->tpl->layout($tpl_dir.'/input.tpl');
        } else {
            $this->tpl->layout('generic/input.tpl');
        }
    }

    /**
     * Delete an item
     *
     * @throws  ControllerException   If the item is hidden to the user or the item does
     * not exist
     */
    protected function delete() {
        $class = str_replace('Controller', '', get_called_class());

        if (defined($class.'::HIDDEN') && constant($class.'::HIDDEN') == 1) {
            throw new ControllerException(self::ERR_INVALID_PARAM);
        }

        try {
            $item = new $class($this->parameters['id']);
        } catch (ItemNotFoundException $e) {
            throw new ControllerException(self::ERR_NOT_FOUND);
        }

        $level = $class::CREATOR_CAN_DELETE ? $class::CREATOR_LEVEL : $class::ADMIN_LEVEL;

        $this->require_login($level);

        $this->tpl->set_display_mode('delete');
        $this->tpl->assign('item', $item->get_all_data());
        $this->tpl->assign('model', $this->model);

        if ($this->tpl->confirm('Are you sure you want to delete this item?')) {
            $item->delete();
        }
    }
}

class ControllerException extends \ErrorException
{
}