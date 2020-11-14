<?php
/**
 * Team model
 *
 * @package Fuzic
 */
namespace Fuzic\Models;

use Fuzic\Lib;


/**
 * Team model
 */
class Team extends Lib\Model
{
    const TABLE = 'teams';
    const IDFIELD = 'id';
    const LABEL = 'team';

    const TEAMLESS_ID = 74;
}