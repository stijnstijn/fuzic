<?php
/**
 * Franchise model
 *
 * @package Fuzic
 */
namespace Fuzic\Models;

use Fuzic\Lib;


/**
 * Franchise model
 */
class Franchise extends Lib\Model
{
    const TABLE = 'franchises';
    const LABEL = 'tag';
    const INDIE_ID = 458;

    public static function search_params($query = '') {
        return array(
            'name LIKE ?' => [$query, 'relation' => 'OR'],
            'real_name LIKE ?' => [$query, 'relation' => 'OR']
        );
    }
}