<?php
/**
 * Defines an abstract class and related exceptions to be used for data models
 *
 * @package Fuzic
 */
namespace Fuzic\Lib;

/**
 * Object representation of data record
 *
 * Provides methods for interacting with data from a database by instantiating
 * an object representing a data record from that database.
 *
 * @todo Eliminate conditional globals
 * @todo Construct queries in a smarter way
 */
abstract class Model {
    /**
     * Database table containing data records of this class.
     *
     * @const string
     */
    const TABLE = '';

    /**
     * Name of the database column containing the ID field of data records of objects of
     * this class.
     *
     * @const string
     */
    const IDFIELD = 'id';

    /**
     * Name of the database column to be used as a label for data records
     *
     * @const string
     */
    const LABEL = 'id';

    /**
     * Return types for `find()`
     *
     * @const string
     */
    const RETURN_ALL = 'all';
    const RETURN_OBJECTS = 'objects';
    const RETURN_SINGLE = 'single';
    const RETURN_SINGLE_OBJECT = 'single_object';
    const RETURN_SINGLE_FIELD = 'field';
    const RETURN_FIELDS = 'fields';
    const RETURN_AMOUNT = 'count';
    const RETURN_KEYS = 'keys';
    const RETURN_BOOLEAN = 'boolean';
    /**
     * @var  bool    Whether the object has been deleted by `delete()`.
     */
    protected $is_deleted = false;
    /**
     * @var  array   Object data.
     */
    private $object_data = false;
    /**
     * @var  bool    Original object data, before possible changes.
     */
    private $original_object_data = false;
    /**
     * @var  array   Updates to the data record.
     */
    private $updates = array();
    /**
     * @var  array   Database connection
     */
    private $db = array();

    /**
     * @var  array   Database schema connection
     */
    private $dbschema = NULL;

    /**
     * Constructor
     *
     * Sets up an object representing a database record and retrieves its data.
     *
     * @param   array|integer  $objectID The ID of the object to be instantiated. Can
     *                                   either be the ID itself, or an array of attributes that should match; this is
     *                                   an associative array with key-value pairings corresponding to database values.
     *                                   The first result (ordered by the ID field of this type of object, descending)
     *                                   is then used as data for the object.
     *
     * @param   boolean        $no_check If this parameter is set to `true` and
     *                                   `$objectID` is an array, the array is not used to match a record from the
     *                                   database but used as object data immediately. This can be used if data has
     *                                   already been retrieved from the database somewhere else in the script.
     *
     * @param   object|boolean $db       Database handler
     *
     * @param bool             $dbschema
     *
     * @throws ItemNotFoundException If `$no_check` is `false` and the object with
     * the given ID does not exist.
     * @throws \ErrorException If no object ID is specified.
     * @access  public
     */
    public function __construct($objectID, $no_check = false, $db = false, $dbschema = false) {
        if(!$db) {
            global $db;
        }
        if(!$dbschema) {
            global $dbschema;
        }

        $this->db = $db;
        $this->dbschema = $dbschema;

        if(!isset($objectID)) {
            throw new \ErrorException('No object ID given.');
        }

        //array passed to constructor?
        if(is_array($objectID)) {
            //instantiate it as object, if we're told not to check it
            if($no_check) {
                $object_data = $objectID;

                //else, find a database record matching the data we got
            } else {
                $where = '';
                foreach($objectID as $key => $value) {
                    $where .= $db->escape_identifier($key).' LIKE '.$db->escape($value).' AND ';
                }
                $where = substr($where, 0, -5);
            }

            //else use the value as an ID and find the record that matches
        } else {
            $where = static::IDFIELD." = ".$db->escape($objectID);
        }

        //get data from database
        if(!$no_check || !is_array($objectID)) {
            $object_data = $db->fetch_single("SELECT * FROM ".static::TABLE." WHERE ".$where.' ORDER BY '.static::IDFIELD.' DESC LIMIT 1');

            //not found? uh oh
            if(!$object_data) {
                $type = get_called_class();
                $ID_error = (is_string($objectID) || is_numeric($objectID)) ? ': Unknown object ID ('.$objectID.')' : '.';

                $stack_trace = array_reverse(debug_backtrace());
                $call_location = array_pop($stack_trace);

                throw new ItemNotFoundException($type.' not found'.$ID_error.' at '.$call_location['file'].':'.$call_location['line']);
            }
        }

        $this->object_data = $object_data;
        $this->original_object_data = $object_data;
    }

    /**
     * Find all items matching a specific query
     *
     * This is different from `find()` in that while `find()` requires very specific
     * parameters, `search()` will take a text query as its parameter and simply find
     * all items that match that query somehow.
     *
     * By default the ID field and the label field of all items are checked for matches.
     *
     * @param   string $query       The query to search for.
     * @param    array $find_params Parameters to pass on to `find()`, which is used
     *                              to retrieve results. Note that the `where` and `mapping_function` parameters are
     *                              used internally by this method and that overwriting them is therefore probably a
     *                              bad idea.
     *
     * @param bool     $db
     *
     * @return array Found items.
     *
     * @throws \ErrorException
     * @access  public
     */
    public static function search($query, $find_params = array(), $db = false) {
        if(!$db) {
            global $db;
        }

        $label_field = static::LABEL;

        $params = array(
            'where' => [
            ],
            'order_by' => $label_field,
            'order' => 'ASC',
            'limit' => 100,
            'mapping_function' => function ($item) use ($label_field) {
                return $item[$label_field];
            }
        );

        if(!empty($query)) {
            $query = '%'.$query.'%';
            $params['where'] = array(
                static::LABEL.' LIKE ?' => [$query],
                static::IDFIELD.' LIKE ?' => [$query, 'relation' => 'OR']
            );
        }

        $params = array_merge($params, $find_params);

        return static::find($params, $db);
    }

    /**
     * Retrieve data records of this class
     *
     * @param   array      $parameters Criteria for what items are returned. An
     *                                 associative array containing the following items, all optional:
     *                                 - `fields`: Array, containing the database fields/attributes to include in the
     *                                 response. Defaults to `*` (all fields).
     *                                 - `where`: SQL `WHERE` clause, to be used for filtering results. Can be any of
     *                                 the following:
     *                                 - String, the `WHERE` clause
     *                                 - Array of strings, which will be concatenated by AND
     *                                 - Associative array, where the keys are the `WHERE` clauses and the values are
     *                                 arrays of parameter mappings; for example `'column = ?' => ['value']`
     *                                 - `limit`: Integer, the upper limit to the amount of results returned.
     *                                 - `offset`: Integer, the offset within the complete result set. Combine with
     *                                 `limit` to acquire a specific subset of items.
     *                                 - `order`: String, either `ASC` or  `DESC`, for ascending or descending.
     *                                 Defaults
     *                                 to `ASC`.
     *                                 - `order_by`: Can be any of the following:
     *                                 - A string, the field by which to order (the order determined by `order`)
     *                                 - An array, the fields by which to order (the order determined by `order`)
     *                                 - An associative array, mapped as `field => order`, which allows specifying
     *                                 the order per field
     *                                 - `return`: String; the manner in which results are returned. Can be any of the
     *                                 following:
     *                                 - `Model::RETURN_ALL` (default): Returns all items as associative arrays
     *                                 - `Model::RETURN_OBJECTS`: Same as `all`, but all items are objects rather than
     *                                 arrays
     *                                 - `Model::RETURN_KEYS`: Only return the attribute names of the found items
     *                                 - `Model::RETURN_SINGLE`: Only returns the first item, as an associative array
     *                                 - `Model::RETURN_SINGLE_OBJECT`: Only returns the first item, as an object
     *                                 - `Model::RETURN_AMOUNT`: Only returns the amount of items found
     *                                 - `Model::RETURN_BOOLEAN`: Returns whether any items were found or not
     *                                 - `key`: String; the field by which to index the items if `return` is either
     *                                 `all` or `objects` - will be ignored if the key is not in `fields`. Defaults to
     *                                 the field given in the class' `IDFIELD` constant.
     *                                 - `follow_keys`: Boolean; if `return` is RETURN_ALL or RETURN_SINGLE, and this
     *                                 is
     *                                 set to`true`, fields that are (in the database) set to be a FOREIGN KEY to
     *                                 anotherdatabase table will have their value be replaced by the data of the item
     *                                 they refer to, as long as there is a data model (based on the `Model` class)
     *                                 available for that table.
     *                                 - `make_url`: Boolean, whether or not to include the following metadata in the
     *                                 return value if `return` is RETURN_ALL or RETURN_SINGLE (defaults to `true`):
     *                                 - __url: Canonical URL for a page representing this item, as made by
     *                                 `build_url()`
     *                                 - __adminurl: Canonical URL for an admin page to manage this item, as made by
     *                                 `build_admin_url()`
     *                                 - __id: Item ID, if part of the response (as specified by `fields`)
     *                                 - __label: Item name, if part of the response (as specified by `fields`)
     *                                 - `mapping_function`: A callback function that every item is passed through when
     *                                 `return` is RETURN_SINGLE or RETURN_ALL.
     *                                 - Other items are treated as constraints; e.g. `column` => `value` will add a
     *                                 `column = value` `WHERE` clause.
     *                                 Note that passing an empty array as parameter will return an array with all
     *                                 records of this type, as associative arrays.
     *
     * @param  object|bool $db         Database handler
     *
     * @return  mixed   The items as found according to the criteria listed
     * in `$parameters`
     *
     * @throws  \ErrorException          If the `$parameters` argument is of the wrong
     * type.
     *
     * @access  public
     */
    public static function find($parameters = array(), $db = false) {
        if(!$db) {
            global $db;
        }

        if(!is_array($parameters)) {
            throw new \ErrorException('find() expects an array as parameter');
        }

        //default values
        $constraints = array();
        $fields = $db->escape_identifier(static::TABLE).'.*';
        $where = '';
        $order = '';
        $limit = '';
        $join = false;
        $joinbit = '';
        $do_URL = true;
        $follow_keys = false;
        $follow_deep = false;
        $index_key = $db->escape_identifier(static::TABLE).'.'.$db->escape_identifier(static::IDFIELD);
        $debug = false;

        if(isset($parameters['return']) && ($parameters['return'] == static::RETURN_SINGLE || $parameters['return'] == static::RETURN_SINGLE_OBJECT)) {
            $parameters['limit'] = 1;
        }

        if(!isset($parameters['return'])) {
            $parameters['return'] = 'all';
        }

        if(isset($parameters['debug']) && $parameters['debug']) {
            unset($parameters['debug']);
            $debug = true;
            $db->enable_debug(true);
        }

        //sanitize parameters and build query
        foreach($parameters as $param => $value) {
            if(!$value && $param != 'make_url') {
                continue;
            }

            switch($param) {
            case 'fields':
                //which fields to include
                if(is_array($value)) {
                    foreach($value as $key => $field) {
                        $value[$key] = $db->escape_identifier(static::TABLE).'.'.$db->escape_identifier($field);
                        $parameters['fields'][$key] = $value[$key];
                    }
                    $fields = implode(', ', $value);
                } else {
                    throw new ModelParamException('"fields" must be NULL or an array');
                }
                break;
            case 'join':
                if(isset($value['on']) && isset($value['table'])) {
                    $join = $value;
                }
                break;
            case 'constraint':
            case 'where':
                //build WHERE clause
                //array: build WHERE clause
                if(is_array($value)) {
                    foreach($value as $constraint_key => $constraint_value) {
                        //if key is an int, it's a numeric array, just add it to the where clause
                        if(is_int($constraint_key)) {
                            $where .= (empty($where) ? '' : ' AND ').$constraint_value;
                            //if it's an array, it's a parameterized string, so parse it
                        } elseif(is_array($constraint_value)) {
                            $relation = (isset($constraint_value['relation']) && strtolower($constraint_value['relation']) == 'or') ? ' OR ' : ' AND ';
                            if(isset($constraint_value['relation'])) {
                                unset($constraint_value['relation']);
                            }
                            $where .= (empty($where) ? '' : $relation).vsprintf(str_replace('?', '%s', $constraint_key), array_map(array($db, 'escape'), $constraint_value));
                            //else, interpret it as `key` = `value` and parse as such
                        } else {
                            $constraints[$constraint_key] = $constraint_value;
                        }
                    }
                    //string: WHERE clause already built
                } elseif(!empty($value)) {
                    $where .= '('.$value.')';
                }
                break;
                //key by which values will be indexed
            case 'key':
                $index_key = $value;
                break;
            case 'follow_keys':
                $follow_keys = !!$value;
                break;
            case 'follow_deep':
                $follow_deep = !!$value;
                break;
                //order by; default order is ASC
            case 'order_by':
                $direction = isset($parameters['order']) ? strtoupper($parameters['order']) : 'ASC';
                $direction = ($direction == 'ASC') ? 'ASC' : 'DESC';

                //if it's an array, sort by multiple fields
                if(is_array($value)) {
                    $order = ' ORDER BY ';
                    //if it's a numeric array, take the given order
                    if(is_int(key($value))) {
                        foreach($value as $field) {
                            $order .= $field.' '.$direction.', ';
                        }
                        $order = substr($order, 0, -2);

                        //if it's associative, assume field => order mapping
                    } else {
                        foreach($value as $field => $field_order) {
                            $field_order = (strtolower($field_order) == 'desc') ? 'DESC' : 'ASC';
                            $order .= $field.' '.$field_order.', ';
                        }
                        $order = substr($order, 0, -2);
                    }

                    //else, it's simple: order by the given field
                } else {
                    $order = ' ORDER BY '.$value.' '.$direction;
                }
                break;
                //limit number of returned values, with offset is needed
            case 'limit':
                if(isset($parameters['offset'])) {
                    $limit = ' LIMIT '.intval($parameters['offset']).', '.intval($value);
                } else {
                    $limit = ' LIMIT '.intval($value);
                }
                break;
                //whether to include a canonical URL for each item
            case 'make_url':
                $do_URL = !!$value;
                break;
                //values that are parsed elsewhere
            case 'return':
            case 'offset':
            case 'order':
            case 'mapping_function':
                break;
                //if not parsed up til here, assume it's a WHERE mapping
            default:
                $constraints[$param] = $value;
                break;
            }
        }

        //do joins
        if(!empty($join)) {
            if(isset($parameters['return']) && !in_array($parameters['return'], [static::RETURN_KEYS, static::RETURN_SINGLE, static::RETURN_ALL])) {
                throw new ModelParamException('If "join" is specified, "return" must be RETURN_SINGLE or RETURN_ALL');
            }

            //prepare sql statement for join
            $on_left = is_array($join['on']) ? array_shift($join['on']) : $join['on'];
            $on_right = is_array($join['on']) ? array_shift($join['on']) : $join['on'];

            $joinbit = ' LEFT JOIN '.$db->escape_identifier($join['table']).' ON '.
                $db->escape_identifier($join['table']).'.'.$db->escape_identifier($on_left).' = '.
                $db->escape_identifier(static::TABLE).'.'.$db->escape_identifier($on_right).' ';

            //add fields selected out of joined table to field list
            if(!isset($join['fields']) || empty($join['fields']) || !is_array($join['fields'])) {
                $fields .= ', '.$db->escape_identifier($join['table']).'.*';
                $parameters['fields'][] = $db->escape_identifier($join['table']).'.*';
            } else {
                foreach($join['fields'] as $field) {
                    $fields .= ', '.$db->escape_identifier($join['table']).'.'.$db->escape_identifier($field);
                    $parameters['fields'][] = $db->escape_identifier($join['table']).'.'.$db->escape_identifier($field);
                }
            }
        } else {
            $joinbit = '';
        }

        //build WHERE clause, escape key and value
        foreach($constraints as $field => $value) {
            if(!empty($where)) {
                $where .= ' AND '.$db->escape_identifier(static::TABLE).'.'.$db->escape_identifier($field).' = '.$db->escape($value);
            } else {
                $where = $db->escape_identifier(static::TABLE).'.'.$db->escape_identifier($field).' = '.$db->escape($value);
            }
        }

        if(!empty($where)) {
            $where = ' WHERE '.$where;
        }

        if(isset($parameters['return']) && $parameters['return'] == static::RETURN_FIELDS) {
            //only return the first fields
            $field = strpos($fields, '*') !== false ? $db->escape_identifier(static::TABLE).'.'.$db->escape_identifier(static::LABEL) : $parameters['fields'][0];
            $return = array_keys($db->fetch_all_indexed('SELECT '.$fields.' FROM '.static::TABLE.$where.$order.$limit, $field, $index_key));

        } elseif(isset($parameters['return']) && $parameters['return'] == static::RETURN_BOOLEAN) {
            //only return boolean for results >? 0
            $items = $db->fetch_field('SELECT COUNT(*) FROM '.static::TABLE.$where.$order.$limit);
            $return = ($items != 0);
            unset($items);

        } elseif(isset($parameters['return']) && $parameters['return'] == static::RETURN_SINGLE_FIELD) {
            //only return the first field
            $return = $db->fetch_field('SELECT '.$fields.' FROM '.static::TABLE.$where.$order.$limit);

        } elseif(isset($parameters['return']) && ($parameters['return'] == static::RETURN_SINGLE || $parameters['return'] == static::RETURN_SINGLE_OBJECT)) {
            //only return the first item
            $return = $db->fetch_single('SELECT '.$fields.' FROM '.static::TABLE.$where.$order.$limit);

            if($parameters['return'] == static::RETURN_SINGLE_OBJECT) {
                if($return) {
                    $return = new static($return, true);
                }
            } elseif($return && $do_URL) {
                $return['__url'] = static::build_url($return);
                $return['__adminurl'] = static::build_admin_url($return);
                if(isset($return[static::IDFIELD])) {
                    $return['__id'] = $return[static::IDFIELD];
                }
                if(isset($return[static::LABEL])) {
                    $return['__label'] = $return[static::LABEL];
                }
            }

            if(isset($parameters['mapping_function']) && is_callable($parameters['mapping_function'])) {
                $return = $parameters['mapping_function']($return);
            }

            if($follow_keys) {
                $dependencies = static::get_dependencies($db);
                foreach($return as $field => $value) {
                    if(isset($dependencies[$field]) && $dependency_class = self::find_table($dependencies[$field]['REFERENCED_TABLE_NAME'])) {
                        $return[$field] = $dependency_class::find([$dependency_class::IDFIELD => $value, 'return' => static::RETURN_SINGLE, 'follow_keys' => $follow_deep, 'follow_deep' => $follow_deep], $db);
                    }
                }
            }

        } elseif(isset($parameters['return']) && $parameters['return'] == static::RETURN_AMOUNT) {
            //return the amount of items
            $return = $db->fetch_field('SELECT COUNT(*) FROM '.static::TABLE.$where.$order.$limit);

        } else {
            //return everything
            if(strpos($fields, '*') !== false || strpos($fields, $index_key)) {
                $return = $db->fetch_all_indexed('SELECT '.$fields.' FROM '.static::TABLE.$joinbit.$where.$order.$limit, $index_key);
            } else {
                $return = $db->fetch_all('SELECT '.$fields.' FROM '.static::TABLE.$joinbit.$where.$order.$limit);
            }
            if($return) {
                //add special metadata fields for each item
                if($do_URL) {
                    foreach($return as $ID => $item) {
                        $return[$ID]['__url'] = static::build_url($item);
                        $return[$ID]['__adminurl'] = static::build_admin_url($item);
                        if(isset($item[static::IDFIELD])) {
                            $return[$ID]['__id'] = $item[static::IDFIELD];
                        }
                        if(isset($item[static::LABEL])) {
                            $return[$ID]['__label'] = $item[static::LABEL];
                        }
                    }
                }

                if($follow_keys) {
                    $dependencies = static::get_dependencies($db);
                    foreach($return as $ID => $item) {
                        foreach($item as $field => $value) {
                            if(isset($dependencies[$field]) && $dependency_class = self::find_table($dependencies[$field]['REFERENCED_TABLE_NAME'])) {
                                $return[$ID][$field] = $dependency_class::find([$dependency_class::IDFIELD => $value, 'return' => static::RETURN_SINGLE, 'follow_keys' => $follow_deep, 'follow_deep' => $follow_deep], $db);
                            }
                        }
                    }
                }

                //as object?
                if(isset($parameters['return']) && ($parameters['return'] == 'object' || $parameters['return'] == static::RETURN_OBJECTS)) {
                    foreach($return as $ID => $item) {
                        $return[$ID] = new static($item, true);
                    }

                    //or only the keys
                } elseif(isset($parameters['return']) && $parameters['return'] == static::RETURN_KEYS) {
                    $return = array_keys($return);
                } elseif(isset($parameters['mapping_function']) && is_callable($parameters['mapping_function'])) {
                    foreach($return as $key => $item) {
                        $return[$key] = $parameters['mapping_function']($item);
                    }
                }
            }
        }

        if($debug) {
            $db->disable_debug();
        }

        return static::map_find_results($return, $parameters);
    }

    /**
     * Create URL based on supplied data
     *
     * For use in static contexts
     *
     * @param    array $data Data to build the URL from. The method looks
     *                       for an 'url' parameter or if that is not found, uses the `LABEL` class
     *                       constant.
     *
     * @return  string  Constructed URL
     *
     * @access  public
     */
    public static function build_url($data) {
        $label = isset($data['url']) ? $data['url'] : $data[static::IDFIELD].'-'.friendly_url($data[static::LABEL]);
        $class = strtolower(get_called_class());
        if(defined($class.'::HUMAN_NAME')) {
            $cat = strtolower(constant($class.'::HUMAN_NAME'));
        } else {
            $class = explode('\\', $class);
            $class = array_pop($class);
            $cat = $class.'s';
        }

        return '/'.$cat.'/'.$label.'/';
    }

    /**
     * Create admin URL based on data
     *
     * For use in static contexts
     *
     * @param    array $data Data to build the URL from. The method looks
     *                       for an 'url' parameter or if that is not found, uses the `LABEL` class
     *                       constant.
     *
     * @return  string  Constructed URL
     *
     * @access  public
     */
    public static function build_admin_url($data) {
        return '/admin/manage/'.self::get_canonical().'/'.$data[static::IDFIELD].'/';
    }

    /**
     * Get label to use for this class in URLs
     *
     * @return  string
     *
     * @access  public
     */
    public static function get_canonical() {
        $label = defined('static::NAME') ? static::NAME : static::TABLE;

        return strtolower(str_replace(['_', ' '], '-', $label));
    }

    /**
     * Find FOREIGN KEYs for linked database table
     *
     * @param bool $db
     * @param bool $dbschema
     *
     * @return array Mapped like `attribute =>
     * ['TABLE_NAME', 'COLUM_NAME', 'REFERENCED_TABLE_NAME', 'REFERENCED_COLUMN_NAME']`
     * @access  public
     */
    public static function get_dependencies($db = false, $dbschema = false) {
        if(!$db) {
            global $db;
        }

        if(!$dbschema) {
            global $dbschema;
        }

        $dependencies = $dbschema->fetch_all_indexed("
            SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
              FROM KEY_COLUMN_USAGE
             WHERE TABLE_NAME = '".static::TABLE."' AND TABLE_SCHEMA = '".$db->get_database_name()."' AND REFERENCED_TABLE_NAME IS NOT NULL",
            'COLUMN_NAME');

        return $dependencies;
    }

    /**
     * Get the class name of the class that has a certain table as reference
     *
     * @param   string $table Database table name
     *
     * @return  string      The class name, or `false` if none was found
     *
     * @access  public
     */
    public static function find_table($table) {
        $classes = get_declared_classes();
        foreach($classes as $class) {
            if(defined($class.'::TABLE') && $class::TABLE == $table) {
                return $class;
            }
        }

        return false;
    }

    /**
     * Filter results of `find()`
     *
     * To be overridden by child classes; dummy by default
     *
     * @param   mixed $results    Results to filter
     * @param   array $parameters Parameters for the original `find()` query
     *
     * @return  mixed                   Results
     *
     * @access  protected
     */
    public static function map_find_results($results, $parameters) {
        return $results;
    }

    /**
     * Retrieve all items linked to this object via pivot tables
     *
     * @param   array $params Parameters for `find()`, with which items will be
     *                        retrieved
     *
     * @return  array       Linked items and object types, as an array with the following
     * keys:
     * - `structure`: result of `get_data_structure` for the pivoted class
     * - `idfield`: name of the attribute of this class' ID field
     * - `table`: name of this class' database table
     * - `items`: linked items
     *
     * @throws \ErrorException  When pivot data isn't supplied as an Array.
     *
     * @access  public
     */
    public function get_pivoted($params = array()) {
        if(!isset($this->__pivot)) {
            return false;
        }

        if(!is_array($this->__pivot)) {
            throw new \ErrorException('get_pivoted() expects an array of pivoted classes');
        }

        $return = array();

        foreach($this->__pivot as $class) {
            $structure = $class::get_data_structure($this->db);
            unset($structure[static::TABLE]);
            $return[$class] = [
                'structure' => $structure,
                'idfield' => static::IDFIELD,
                'table' => static::TABLE,
                'items' => $class::find(array_merge($params, ['make_url' => false, 'where' => [$this->db->escape_identifier(static::TABLE).' = ?' => [$this->get_ID()]]]), $this->db)
            ];
        }

        return $return;
    }

    /**
     * Retrieve unique identifier for the data record.
     *
     * @return  integer     the ID
     *
     * @access public
     */
    public function get_ID() {
        return $this->get(static::IDFIELD);
    }

    /**
     * Get object attribute value
     *
     * @param   string $attribute The attribute to return
     *
     * @return  mixed   The specified attribute's value, or `null` if the object does not
     * have the specified attribute.
     *
     * @access public
     */
    public function get($attribute) {
        if($this->object_data && $this->has_attribute($attribute)) {
            return $this->object_data[$attribute];
        }

        return NULL;
    }

    /**
     * Check whether the object has a certain data attribute
     *
     * @param   string $attribute The attribute to check for
     *
     * @return  boolean
     *
     * @access  public
     */
    public function has_attribute($attribute) {
        return array_key_exists($attribute, $this->object_data);
    }

    /**
     * Change object ID
     *
     * Object ID cannot be changed via `set()` as it requires some extra checks and
     * actions. Unlike `set()`, this updates the database immediately.
     *
     * @param   mixed $object_ID New object ID.
     *
     * @return  boolean                         Whether the change was succesful.
     *
     * @access  public
     */
    public function set_ID($object_ID) {
        //update the database
        $update = $this->db->update(
            $this->db->escape_identifier(static::TABLE),
            [static::IDFIELD => $object_ID],
            $this->db->escape_identifier(static::IDFIELD).' = '.$this->db->escape($this->get_ID())
        );

        $rows = $this->db->num_rows();

        //if update was succesful, update object data as well
        if($rows > 0) {
            $this->object_data[static::IDFIELD] = $object_ID;
            $this->original_object_data[static::IDFIELD] = $object_ID;
        }

        return $update;
    }

    /**
     * Push object data to data record in database
     *
     * @param   boolean $force_update By default data is checked for whether it is
     *                                actually different from the current values before updating the database. Setting
     *                                this parameter to `true` skips this check.
     *
     * @return  boolean     `true` on success, `false` on failure.
     *
     * @throws  \ErrorException              If the object is not valid (according to
     * `is_valid()`)
     *
     * @access public
     */
    public function update($force_update = false) {
        if(!$this->is_valid()) {
            throw new \ErrorException('Cannot update an invalid object');
        }

        //only run a query if there are any changes
        if(count($this->updates) > 0 || $force_update) {

            //if the object does not exist yet, create it first
            if($this->get_ID() === false) {
                $new = static::create($this->updates, false, $this->db);
                $this->object_data = $new->get_all_data();
                $this->original_object_data = $this->object_data;
                $this->updates = array();

                return true;
            }

            $update_success = $this->db->update(
                static::TABLE,
                $this->updates,
                static::IDFIELD.' = '.$this->db->escape($this->original_object_data[static::IDFIELD]));

            //if the update was succesful, forget all old data
            if($update_success) {
                $this->original_object_data = $this->object_data;
                $this->updates = array();

                return true;
            } else {
                return false;
            }
        }

        return true;
    }

    /**
     * Check whether the object is valid
     *
     * An object is considered valid if it has succesfully retrieved a data record from
     * the database and has not been deleted.
     *
     * @return  boolean
     *
     * @access  public
     */
    public function is_valid() {
        return (!$this->is_deleted && $this->object_data !== false);
    }

    /**
     * Create new database row for an object of this class.
     *
     * @param   array   $data    The data to store in the database. Should
     *                           be an associative array with key => value pairs matching database table columns.
     * @param   boolean $delayed If set to `true` (default `false`), no
     *                           database record is created yet. This has to be triggered manually instead via
     *                           `update()`.
     *
     * @param bool      $db
     *
     * @return object An object representing the record that was just inserted.
     *
     * @throws \ErrorException If the item could not be created.
     * @access  public
     */
    public static function create($data, $delayed = false, $db = false) {
        if(!$db) {
            global $db;
        }

        //create an "empty" object if using delayed inserts and return that object
        if($delayed) {
            $empty = new static([], true);
            $empty->set(static::IDFIELD, false, true);

            return $empty;
        }
        $new_ID = $db->insert_fetch_id(static::TABLE, $data);

        if($new_ID !== false) {
            //this only happens in some exotic cases where tables don't have an auto_increment
            //ID column
            if($new_ID == '0') {
                return new static($data[static::IDFIELD]);
                //in all other cases, just create a new object based on the ID retrieved
            } else {
                return new static($new_ID);
            }
        } else {
            throw new \ErrorException('Could not push new object to database: database said "'.$db->get_error().'"');
        }
    }

    /**
     * Set object attribute value
     *
     * Note: the ID attribute cannot be changed. If the value passed to this method is an
     * object of class `Model` or one of its child classes, the ID of that object
     * (retrieved via `get_ID()`) is used as value.
     *
     * Values are first passed through `validate()` before being set.
     *
     * @param   string  $attribute The attribute to change
     * @param   mixed   $value     The value to set the attribute to
     * @param   boolean $unsafe    If set to `true`, checks that would guarantee the
     *                             integrity of the object (e.g. not changing the ID, changing invalid attributes) are
     *                             not done
     *
     * @return  mixed   The value that was set, or `null` if the object does
     * not have the specified attribute.
     *
     * @throws  \ErrorException              If the object is not valid (according to
     * `is_valid()`)
     * @throws  \ErrorException              If the attribute to be changed is the object's
     * ID
     * @throws  \ErrorException              If the object does not have the attribute
     * that is to be changed
     *
     * @access public
     */
    public function set($attribute, $value = NULL, $unsafe = false) {
        if(!$this->is_valid()) {
            throw new \ErrorException('Cannot update an invalid object');
        }

        if(!$unsafe && $attribute == static::IDFIELD) {
            throw new \ErrorException('Cannot change object ID of object of class "'.get_called_class().'"');
        }

        if(is_array($attribute)) {
            foreach($attribute as $key => $value) {
                $this->set($key, $value, $unsafe);
            }

            return true;
        }

        if(!$unsafe && !$this->has_attribute($attribute)) {
            throw new \ErrorException('Object of class "'.get_called_class().'" has no attribute "'.$attribute.'"');
        }

        if($value instanceof Model) {
            $value = $value->get_ID();
        }

        if($this->object_data !== false && ($unsafe || array_key_exists($attribute, $this->object_data))) {
            $value = $this->validate($attribute, $value);
            if($unsafe || $value !== $this->original_object_data[$attribute]) {
                $this->updates[$attribute] = $value;
                $this->object_data[$attribute] = $value;
            }

            return $value;
        }

        return NULL;
    }

    /**
     * Validates new values for attributes
     *
     * Dummy function, to be overridden by child classes.
     *
     * @param  string $attribute The name of the attribute being updated.
     * @param  mixed  $value     The value to be validated.
     *
     * @return  mixed  The validated value.
     *
     * @access public
     */
    protected function validate($attribute, $value) {
        return $value;
    }

    /**
     * Delete data record from database
     *
     * @return  bool    `true` on success, `false` on failure.
     *
     * @throws  \ErrorException  If the object is not a valid record representation.
     *
     * @access public
     */
    public function delete() {
        if(!$this->is_valid()) {
            throw new \ErrorException('Cannot delete an invalid object.');
        }

        //delete links
        $links = $this->get_links();
        foreach($links as $link) {
            $class = $link['class'];
            foreach($link['items'] as $item) {
                $item = new $class($item[$class::IDFIELD]);
                $item->delete();
            }
        }

        //delete the object itself
        $deleted = $this->db->delete(static::TABLE, static::IDFIELD.' = '.$this->db->escape($this->get_ID()));

        if($deleted) {
            $this->is_deleted = true;
            $this->object_data = false;
        }

        return $this->is_deleted;
    }

    /**
     * Find linked items
     *
     * Find all database items to which this object is linked via FOREIGN KEYs
     *
     * @param   boolean  $ignore_origin
     * @param   boolean  $table_names   Whether or not to use the table name
     *                                  as item key (default `false`)
     * @param    boolean $ignore_hidden If set to `true`, links to objects
     *                                  of hidden classes are ignored (default `false`)
     *
     * @return  array   Array with linked items, with items mapped as follows:
     * `table of item class => items as array`
     * The items are as returned by `find(['follow_keys' => true])`
     *
     * @access  public
     */
    public function get_links($ignore_origin = false, $table_names = false, $ignore_hidden = false) {
        $models = Controller::index_models(false, '', 'table');

        $links = $this->dbschema->fetch_all("
            SELECT *
              FROM KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ".$this->dbschema->escape($this->db->get_database_name())."
               AND (
                    /* TABLE_NAME = ".$this->dbschema->escape(static::TABLE)." OR */
                    REFERENCED_TABLE_NAME = ".$this->dbschema->escape(static::TABLE)."
                   )");

        $linked_items = array();
        foreach($links as $link) {
            $table = $link['TABLE_NAME'];
            if(!isset($models[$table]) || ($ignore_hidden && defined($models[$table]['class'].'::HIDDEN') && $models[$table]['class']::HIDDEN)) {
                continue;
            }

            $label = (!$table_names) ? $link['CONSTRAINT_NAME'] : $table;

            if(!isset($linked_items[$table])) {
                $name = defined($models[$table]['class'].'::HUMAN_NAME') ? $models[$table]['class']::HUMAN_NAME : $models[$table]['class']::LABEL;
                $linked_items[$label] = array('name' => $name, 'class' => $models[$table]['class'], 'items' => []);
            }

            $class = $models[$table]['class'];
            $items = $class::find([
                'where' => [$link['COLUMN_NAME'].' = ?' => [$this->get_ID()]],
                'follow_keys' => true
            ], $this->db);

            if($ignore_origin) {
                foreach($items as $key => $item) {
                    unset($items[$key][$link['COLUMN_NAME']]);
                }
            }

            $linked_items[$label]['items'] = $items;
        }

        return $linked_items;
    }

    /**
     * Get canonical URL referring to admin edit page of this item
     *
     * @return  string
     *
     * @access  public
     */
    public function get_admin_url() {
        return static::build_admin_url($this->get_all_data());
    }

    /**
     * Retrieve all object data
     *
     * @param   boolean $add_meta Whether to include metadata such as URLs
     * @param   boolean $filter   Pass results through `map_find_results()`?
     *
     * @return  array
     *
     * @access public
     */
    public function get_all_data($add_meta = true, $filter = true) {
        $all_data = $this->object_data;

        if($add_meta) {
            $all_data['__url'] = $this->get_url();
            $all_data['__adminurl'] = static::build_admin_url($all_data);
            $all_data['__label'] = $all_data[static::LABEL];
        }

        if($filter) {
            $all_data = static::map_find_results($all_data, array('return' => 'single'));
        }

        return $all_data;
    }

    /**
     * Get canonical URL referring to this item
     *
     * @return  string
     *
     * @access  public
     */
    public function get_url() {
        $label = $this->get('url');
        if(!$label) {
            $label = $this->get_ID().'-'.friendly_url($this->get_label());
        }

        //try to make a fancy friendly URL based on class data
        if(defined(get_class($this).'::HUMAN_NAME')) {
            $cat = strtolower(static::HUMAN_NAME);

            //if that fails just use the class name
        } else {
            $class = explode('\\', get_class($this));
            $cat = strtolower(array_pop($class)).'s';
        }

        return '/'.$cat.'/'.$label.'/';
    }

    /**
     * Get 'canonical name'
     *
     * @return  string
     *
     * @access  public
     */
    public function get_label() {
        return $this->get(static::LABEL);
    }

    /**
     * Get settings needed to create an HTML form for items of this type
     *
     * @return  array       Form settings, mapped like `field => settings`,
     * with `field` being a attribute/database column name and `settings` being an array
     * containing the following items:
     * - `type`: input type to use in an HTML form. `textarea`/`text`/`select`/`checkbox`
     * /`multibox`.
     * - `label`: label to use for this field.
     * - `current`: current value.
     * - `class`: if `type` is `multibox`, this contains the class name of the items to
     * fill the multibox with.
     * - `options`: if `type` is `select`, this contains the options for the selectbox.
     * - `max_length`: if `type` is `text` and there is a max length, this contains it.
     * - `datatype`: column type in the database. Not set for `type = multibox`.
     *
     * @access  public
     */
    public function get_form_settings() {
        $structure = $this->get_data_structure($this->db);
        $dependencies = $this->get_dependencies($this->db);

        $form_settings = array();

        foreach($structure as $field => $settings) {
            if(isset($dependencies[$field]) && $class = self::find_table($dependencies[$field]['REFERENCED_TABLE_NAME'])) {
                $amount = $class::find(['return' => static::RETURN_AMOUNT], $this->db);
                if($amount < 100) {
                    $options = $class::find([
                        'mapping_function' => function ($item) use ($class) {
                            return $item[$class::LABEL];
                        },
                        'order_by' => $class::LABEL
                    ], $this->db);
                    $form_settings[$field] = ['type' => 'select', 'options' => $options];
                } else {
                    $current = $class::find([
                        $class::IDFIELD => $this->get($field),
                        'return' => static::RETURN_SINGLE
                    ], $this->db);
                    $label = $class::get_canonical();
                    $form_settings[$field] = ['type' => 'multibox', 'label' => $label, 'class' => $class, 'current' => $current[$class::LABEL]];
                }
                $form_settings['datatype'] = 'foreign';
            } elseif(substr($settings['type'], -4, 4) == 'text') {
                $form_settings[$field] = ['type' => 'textarea', 'datatype' => 'text'];
            } elseif($settings['type'] == 'enum' || $settings['type'] == 'set') {
                $options = array_map(function ($a) {
                    return substr($a, 1, -1);
                }, explode(',', $settings['params']));
                $options = array_combine($options, $options);
                $options = ['' => ''] + $options;
                $form_settings[$field] = ['type' => 'select', 'options' => $options, 'datatype' => 'enum'];
            } elseif($settings['type'] == 'bit' || (substr($settings['type'], -3, 3) == 'int' && $settings['params'] == '1')) {
                $form_settings[$field] = ['type' => 'checkbox', 'datatype' => 'boolean'];
            } else {
                if(!empty($settings['params'])) {
                    $form_settings[$field] = ['type' => 'text', 'datatype' => $settings['type'], 'max_length' => intval($settings['params'])];
                } else {
                    $form_settings[$field] = ['type' => 'text', 'datatype' => $settings['type']];
                }
            }
        }

        if(isset($this->__pivot)) {
            foreach($this->__pivot as $class) {
                $subdeps = $class::get_dependencies($this->db, $this->dbschema);
                $class_fields = $class::get_data_structure($this->db);
                $fields = array();
                foreach($class_fields as $class_field => $type) {
                    if($class_field == static::TABLE) {
                        continue;
                    }
                    if($class_field == $class::IDFIELD) {
                        continue;
                    }
                    if(isset($subdeps[$class_field])) {
                        $fields[$class_field] = ['type' => 'multibox', 'label' => $class_field, 'class' => $subdeps[$class_field]['REFERENCED_TABLE_NAME']];
                    } else {
                        $fields[$class_field] = ['type' => 'text', 'label' => $class_field];
                    }
                }
                $form_settings[$class] = ['type' => 'multitem', 'datatype' => '__pivot__', 'class' => $class, 'fields' => $fields];
            }
        }

        return $form_settings;
    }

    /**
     * Get data structure of the database table in which records are stored
     *
     * @param bool $db
     *
     * @return array The table structure, mapped as `field name` =>
     * `type`
     * @access  public
     */
    public static function get_data_structure($db = false) {
        if(!$db) {
            global $db;
        }

        $db->query("DESCRIBE ".static::TABLE);
        $structure = array();

        while($field = $db->fetch_row()) {
            $type = explode('(', $field['Type']);
            if(count($type) > 1) {
                $chunks = explode(')', $type[1]);
                $params = array_shift($chunks);
            } else {
                $params = '';
            }
            $structure[$field['Field']] = [
                'settings' => $field,
                'type' => $type[0],
                'params' => $params
            ];
        }

        return $structure;
    }

    /**
     * Check whether an attribute is also a FOREIGN KEY
     *
     * @param   string $attribute Attribute to check
     *
     * @return  boolean
     *
     * @throws  \ErrorException              If the object does not have the attribute
     * that is to be checked
     *
     * @access  public
     */
    public function attribute_is_key($attribute) {
        if(!$this->has_attribute($attribute)) {
            throw new \ErrorException('Object of class "'.get_called_class().'" has no attribute "'.$attribute.'"');
        }

        $dependencies = $this->get_dependencies($this->db, $this->dbschema);

        return isset($dependencies[$attribute]);
    }
}

/**
 * Exception when item not found
 */
class ItemNotFoundException extends \ErrorException {
}

class ModelParamException extends \ErrorException {
}