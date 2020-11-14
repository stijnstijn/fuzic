<?php
/**
 * Ajax request controller
 *
 * @package Fuzic-site
 */
namespace Fuzic\Site;

use Fuzic\Lib;
use Fuzic\Models;


/**
 * Ajax request contoller
 */
class AjaxController extends Lib\Controller
{
    const REF_CLASS = 'Stream';

    /**
     * Search for items matching a query
     *
     * @return  string          JSON-encoded array of search results, mapped
     * `id` => `item label`
     *
     * @access  public
     */
    public function search() {
        $type = $this->parameters['type'];
        $query = $_GET['filter'];

        $models = $this->index_models(Models\User::LEVEL_ADMIN);
        header('Content-type: application/json');

        if (!isset($models[$type])) {
            echo json_encode([]);
            exit;
        }

        $model = $models[$type];
        $class = $model['class'];

        $items = $class::find([
            'where' => [
                'hidden != 1',
                $model['item_label'].' LIKE ?' => ['%'.$query.'%'],
                $model['idfield'].' LIKE ? ' => ['%'.$query.'%', 'relation' => 'OR']
            ],
            'limit' => 15,
            'order_by' => ['vh' => 'desc', $model['item_label'] => 'asc'],
            'mapping_function' => function ($item) use ($model) {
                return $item[$model['item_label']];
            }
        ]);

        echo json_encode($items);
        exit;
    }
}