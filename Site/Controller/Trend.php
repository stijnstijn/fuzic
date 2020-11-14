<?php
namespace Fuzic\Site;

use Fuzic\Lib;
use Fuzic\Models;


class TrendController extends Lib\Controller
{
    const REF_CLASS = 'Trend';

    protected function before_display() {
        $this->tpl->add_JS('trends.js');
        $this->tpl->add_CSS('stream.css');

        $this->tpl->add_breadcrumb('Trends', '/trends/');
    }

    protected function before_overview() {
        $chart_month = $this->chart();
        $chart_year = $this->chart('year');

        $first_crawl = Models\Trend::find([
            'where' => ['game = ?' => [ACTIVE_GAME]],
            'order_by' => ['year' => 'ASC', 'month' => 'ASC', 'day' => 'ASC'],
            'limit' => 1,
            'return' => Lib\Model::RETURN_SINGLE
        ]);

        $this->tpl->assign('first_crawl', $first_crawl);
        $this->tpl->assign('id', 'trend-month');
        $this->tpl->assign('per', !empty($this->parameters['per']) ? $this->parameters['per'] : 'month');
        $this->tpl->assign('chart_month', array('data' => array_values($chart_month), 'labels' => array_keys($chart_month)));
        $this->tpl->assign('chart_year', array('data' => array_values($chart_year), 'labels' => array_keys($chart_year)));
    }

    public function chart($per = 'month') {
        $per = empty($this->parameters['per']) ? $per : $this->parameters['per'];
        $this_year = date('Y');
        $this_month = date('n');
        $prev_year = 2013; //($this_month == 12) ? $this_year : $this_year - 1;
        $prev_month = ($this_month == 12) ? 1 : $this_month + 1;
        $where = ($per == 'year') ? ['game = ?' => [ACTIVE_GAME]] : ['game = ?' => [ACTIVE_GAME, $prev_year, $prev_month, $this_year, $this_month]];
        $data = Models\Trend::find([
            'where' => $where,
            'order_by' => ['year' => 'ASC', 'month' => 'ASC', 'day' => 'ASC']
        ]);

        if ($per == 'month') {
            $timestamp_format = '%B %Y';
        } elseif ($per == 'week') {
            $timestamp_format = 'Week %U %Y';
        } else {
            $timestamp_format = '%Y';
        }

        $periods = array();
        foreach ($data as $day) {
            if(!isset($periods[$day['year']][$day[$per]])) {
                $periods[$day['year']][$day[$per]] = array('days' => 0, 'peak' => 0, 'average' => 0, 'vh' => 0, 'data' => array(), 'timestamp' => strftime($timestamp_format, period_start($per, $day[$per], $day['year'])));
            }

            $periods[$day['year']][$day[$per]]['days'] += 1;
            $periods[$day['year']][$day[$per]]['data'][] = $day;
            $periods[$day['year']][$day[$per]]['average'] += $day['average'];
            $periods[$day['year']][$day[$per]]['vh'] += $day['vh'];
            if($periods[$day['year']][$day[$per]]['peak'] < $day['peak']) {
                $periods[$day['year']][$day[$per]]['peak'] = floatval($day['peak']);
            }
        }

        foreach ($periods as $year => $data) {
            foreach($data as $period => $stats) {
                $periods[$year][$period]['average'] = $stats['days'] > 0 ? floor($stats['average'] / $stats['days']) : 0;
            }
        }


        $chart = array();
        $key = (isset($this->parameters['type']) && in_array($this->parameters['type'], ['peak', 'average', 'vh'])) ? $this->parameters['type'] : 'average';
        foreach ($periods as $year => $data) {
            foreach($data as $period => $stats) {
                $chart[$stats['timestamp']] = $stats[$key];
            }
        }

        if (isset($_GET['async'])) {
            $this->tpl->json_response(array(
                'data' => array_values($chart),
                'labels' => array_keys($chart),
                'type' => $key,
                'years' => array_map(function ($d) {
                    preg_match('/([0-9]{4})$/siU', $d, $match);
                    return $match[1];
                }, array_keys($chart))
            ));
        } else {
            return $chart;
        }

    }
}