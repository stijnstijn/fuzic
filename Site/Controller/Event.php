<?php
/**
 * Event controller
 *
 * @package Fuzic-site
 */
namespace Fuzic\Site;

use Fuzic;
use Fuzic\Lib;
use Fuzic\Models;


/**
 * Event contoller
 */
class EventController extends Lib\Controller
{
    const REF_CLASS = 'Event';
    const DEFAULT_ORDER = 'DESC';

    /**
     * Called before display. Used for init.
     *
     * @access  public
     */
    public function before_display() {
        $this->tpl->add_css('stream.css');
        $this->tpl->add_js('charts.js');
        $this->tpl->add_breadcrumb('Events', '/events/');

        $this->tpl->assign('check_delay', Fuzic\Config::CHECK_DELAY);
    }

    /**
     * Show event data
     *
     * @access  public
     */
    public function single() {
        $eventID = $this->parameters['id'];
        $ajax = $this->parameters['ajax'];

        $event = new Models\Event([Models\Event::IDFIELD => $eventID, 'game' => ACTIVE_GAME]);
        $chart = Lib\Highcharts::get_event($event);

        $this->tpl->assign('streams', $chart['legend']);
        $this->tpl->assign('chart', $chart);
        $overall = new Lib\Interval($event->get_stream_IDs(), $event->get('start'), $event->get('end'));

        //get stats for each event stream
        $stream_stats = array();
        foreach ($chart['streams'] as $stream) {
            $stats = new Lib\Interval($stream, $event->get('start'), $event->get('end'));
            $stream_overview = $stats->get_stats();
            $stream_overview += $stream;
            $stream_overview['start'] = $stream['start'];
            $stream_overview['end'] = $stream['end'];
            $stream_stats[] = $stream_overview;
        }

        //get franchise data
        $franchise = $event->get('franchise');
        if (!empty($franchise)) {
            try {
                $franchise = new Models\Franchise($franchise);
                $franchise = $franchise->get_all_data();
            } catch (Lib\ItemNotFoundException $e) {
                $franchise = false;
            }
        } else {
            $franchise = false;
        }

        //get event ranks
        $all_time_rank = Models\Event::find([
            'return' => Lib\Model::RETURN_AMOUNT,
            'where' => [
                'game = ? AND vh > ?' => [ACTIVE_GAME, $event->get('vh')]
            ]
        ]);

        $month_start = period_start('month', date('n', $event->get('start')), date('Y', $event->get('start')));
        $month_end = period_end('end', date('n', $event->get('start')), date('Y', $event->get('start')));
        $month_rank = Models\Event::find([
            'return' => Lib\Model::RETURN_AMOUNT,
            'where' => [
                'start > ? AND start < ? AND vh > ? AND game = ?' => [$month_start, $month_end, $event->get('vh'), ACTIVE_GAME]
            ]
        ]);

        //get matches
        $current = false;
        $matches = Models\Match::find(['where' => ['event = ?' => $event->get_ID()], 'order_by' => ['end' => 'ASC']]);
        foreach ($matches as $i => $match) {
            $players = explode(' vs ', $match['match']);
            if (!empty($match['player1'])) {
                $match['player1'] = new Models\Stream($match['player1']);
                $match['player1'] = $match['player1']->get_all_data();
            } else {
                $match['player1'] = $players[0];
            }
            if (!empty($match['player2'])) {
                $match['player2'] = new Models\Stream($match['player2']);
                $match['player2'] = $match['player2']->get_all_data();
            } else {
                $match['player2'] = $players[1];
            }
            if ($match['start'] <= time() && $match['end'] >= (time() - Fuzic\Config::MAX_SESSION_PAUSE)) {
                $current = $match;
            }

            $match['rel_start'] = array_search($match['start'], $chart['labels']);
            $match['rel_end'] = array_search($match['end'], $chart['labels']);

            if ($match['rel_start'] && !$match['rel_end']) {
                $match['rel_end'] = end($chart['labels']);
            }

            $matches[$i] = $match;
        }

        if (!$ajax) {
            //usually you just want a straight up html page
            $this->tpl->set_title($event->get('name'));
            $this->before_display();
            $this->tpl->add_breadcrumbs($event, $franchise);

            $this->tpl->add_twittercard([
                'card' => 'summary',
                'site' => 'fuzicnl',
                'title' => 'Event: '.$event->get('name').', '.date(Fuzic\Constants::DATETIME_SHORTEST, $event->get('start')),
                'description' => 'Average viewers for this event: '.number_format(round($overall->get_average())).', peak viewers: '.number_format(round($overall->get_peak()))
            ]);

            $this->tpl->assign('rank_all_time', $all_time_rank);
            $this->tpl->assign('rank_month', $month_rank);
            $this->tpl->assign('franchise', $franchise);
            $this->tpl->assign('streams', $stream_stats);
            $this->tpl->assign('current', $current);
            $this->tpl->assign('stats', $overall->get_stats());
            $this->tpl->assign('event', $event->get_all_data());
            $this->tpl->assign('matches', $matches);
            $this->tpl->assign('delay', Fuzic\Config::CHECK_DELAY);


            $this->tpl->layout('Event/single.tpl');
        } else {
            //ajax requests are for auto-refreshed updates
            $stats = $overall->get_stats();
            header('Content-type: application/json');
            $data = array(
                'time' => smarty_modifier_time_approx($event->get('end') - $event->get('start')),
                'average' => smarty_modifier_thousands($stats['average']),
                'peak' => smarty_modifier_thousands($stats['peak']),
                'vh' => smarty_modifier_thousands($stats['vh']),
                'rank_month' => '#'.smarty_modifier_thousands($month_rank + 1),
                'rank_overall' => '#'.smarty_modifier_thousands($all_time_rank + 1),
                'streams' => array(),
                'is_live' => ($event->get('end') > (time() - (Fuzic\Config::CHECK_DELAY * 5))),
                'chart' => $chart
            );
            foreach ($stream_stats as $stream) {
                $data['streams'][$stream[Models\Stream::IDFIELD]] = array(
                    'peak' => smarty_modifier_thousands($stream['peak']),
                    'average' => smarty_modifier_thousands($stream['average']),
                    'vh' => smarty_modifier_thousands($stream['vh']),
                    'time' => smarty_modifier_date_span($stream['start'], $stream['end'])
                );
            }
            echo json_encode($data);
            exit;
        }
    }

    /**
     * Show event image
     */
    public function graph() {
        ini_set('memory_limit', '3G');
        $eventID = $this->parameters['id'];

        $resfolder = '../Site/assets/';
        $font_black = $resfolder.'fonts/Aaux ProBlack.ttf';
        $font_medium = $resfolder.'fonts/Aaux ProMedium.ttf';

        $event = new Models\Event($eventID);
        $cachefile = $resfolder.'graphs/'.$eventID.'-'.friendly_url($event->get('name')).'.png';
        if($event->get('end') < time() - 3601 && is_file($cachefile)) {
            header('Content-type: image/png');
            echo file_get_contents($cachefile);
            return;
        }

        include '../Lib/pChart2.1.4/class/pData.class.php';
        include '../Lib/pChart2.1.4/class/pDraw.class.php';
        include '../Lib/pChart2.1.4/class/pImage.class.php';

        //positioning of plotted chart on output image
        define('CHART_X', 115);
        define('CHART_Y', 250);
        define('CHART_W', 1495);
        define('CHART_H', 725);
        define('CHART_TOP', 20);
        define('CHART_BOTTOM', 0);

        //get game color
        $games = json_decode(file_get_contents('../games.json'), true);
        $game = $games[$event->get('game')];
        $colour = $game['color'];
        $rgb = get_color_values($colour);
        define('R', $rgb[0]);
        define('G', $rgb[1]);
        define('B', $rgb[2]);
        define('DARKEN', 0.8);

        //prepare data; use highcharts stat generator
        $chart_data = new \pData();
        $chart = Lib\Highcharts::get_event($event, true);
        $interval = ceil(count($chart['data']) / 65); //smooth the data so it's not too jagged, limit to 65 datapoints
        $new = array('data' => array(), 'labels' => array(), 'legend' => $chart['legend'], 'streams' => $chart['streams']);
        foreach($chart['data'] as $index => $viewers) {
            if($index % $interval == 0) {
                $new['data'][] = $viewers;
                $new['labels'][] = $chart['labels'][$index];
            }
        }

        $chart = $new;

        //prepare image
        $frame = imagecreatefrompng($resfolder.'graphs/frame.png');
        $template = imagecreatetruecolor(imagesx($frame), imagesy($frame));
        imagefill($template, 1, 1, imagecolorallocate($template, R, G, B));
        imagecopy($template, $frame, 0, 0, 0, 0, imagesx($frame), imagesy($frame));
        $white = imagecolorallocate($template, 255, 255, 255);
        $black = imagecolorallocate($template, 0, 0, 0);

        //prepare plot
        $chart_data->setAxisName(0, "Viewers");
        $chart_data->addPoints($chart['labels'], "Time");
        $chart_data->setSerieDescription("Time", "Time");
        $chart_data->setAbscissa("Time");
        $chart_data->setAxisDisplay(0, AXIS_FORMAT_METRIC);
        $chart_data->setXAxisDisplay(AXIS_FORMAT_TIME, "H:i");
        $chart_img = new \pImage(CHART_W, CHART_H + 40, $chart_data);
        $chart_img->Antialias = true;
        $chart_img->setFontProperties(array("FontName" => $font_medium, "FontSize" => 10, 'R' => 128, 'G' => 128, 'B' => 128));
        $chart_img->setGraphArea(38, CHART_TOP, CHART_W, CHART_H - CHART_BOTTOM);

        //draw scale and grid
        $chart_img->drawScale(array(
                "DrawSubTicks" => true,
                'GridR' => 128,
                'GridG' => 128,
                'GridB' => 128,
                'AxisR' => 128,
                'AxisG' => 128,
                'AxisB' => 128,
                'TickR' => 128,
                'TickG' => 128,
                'TickB' => 128,
                'SubTickR' => 128,
                'SubTickG' => 128,
                'SubTickB' => 128,
                'DrawYLines' => ALL,
                'DrawXLines' => false,
                'Mode' => SCALE_MODE_MANUAL,
                'ManualScale' => [0 => ["Min" => 0, "Max" => ceil($event->get('peak') * 1.25)]],
                'LabelSkip' => 7
            )
        );

        //process streams
        $agg = array(); //this is because with the plot type we use the series don't stack so we're gonna do it manually

        $rect_size = 30; //legend settings, will be used later
        $rect_margin = floor($rect_size * 0.75);
        $rect_y = CHART_Y + CHART_H - 5 - $rect_margin - $rect_size + 90;
        $legend_w = 0;
        $skip = array();

        $included = 0;
        foreach($chart['legend'] as $index => $stream) {
            $points = array();
            $i = 0;
            foreach($chart['data'] as $viewers) {
                $viewers = array_values($viewers);
                $points[] = $viewers[$index] + (isset($agg[$i]) ? $agg[$i] : 0);
                if(isset($agg[$i])) {
                    $agg[$i] += $viewers[$index];
                } else {
                    $agg[$i] = $viewers[$index];
                }
                $i += 1;
            }
            $chart_data->addPoints($points, $stream);
            $chart_data->setSerieWeight($stream, 5);

            $fac = count($chart['legend']) - 1 - $index;
            $chart_data->setPalette(array($stream), array('R' => R * pow(DARKEN, $fac), 'G' => G * pow(DARKEN, $fac), 'B' => B * pow(DARKEN, $fac), 'Alpha' => 150));

        }

        for($index = count($chart['legend']) - 1; $index >= 0; $index -= 1) {
            //only show top 5 streams in legend
            $max = 0;
            foreach($chart['data'] as $viewers) {
                $viewers = array_values($viewers);
                $max = max($viewers[$index], $max);
            }
            if($max < 10 || $included > 5) {
                $skip[$index] = true;
            }

            if(isset($skip[$index])) {
                continue;
            }
            $stream = $chart['legend'][$index];
            //calculate legend width
            $bbox = imagettfbbox(16, 0, $font_medium, $stream);
            $width = $bbox[2] - $bbox[0];
            $legend_w += $rect_size + 10 + $width + 35;
            $included += 1;
        }

        $legend_w -= 35;
        $rect_x = CHART_X - 14 + $rect_margin + floor((1515 - $rect_margin) / 2) - floor($legend_w / 2);

        //plot and copy plot to composite image
        $chart_img->drawFilledSplineChart(["Weight" => 2]);
        $pic = &$chart_img->Picture;
        imagecopy($template, $pic, CHART_X, CHART_Y, 0, 0, imagesx($pic), imagesy($pic) - 3);

        //draw legend, centered at bottom of plot area
        $languages = array('de' => 'German', 'pl' => 'Polish', 'kr' => 'Korean', 'pt' => 'Portugese', 'es' => 'Spanish', 'nl' => 'Dutch', 'fr' => 'French', 'ru' => 'Russian', 'cn' => 'Chinese', 'en' => 'English', 'th' => 'Thai', 'jp' => 'Japanese');
        $i = 0;
        for($index = count($chart['legend']) - 1; $index >= 0; $index -= 1) {
            if(isset($skip[$index])) {
                continue;
            }
            $stream = $chart['legend'][$index];
            $fac = $i;
            $scolor = imagecolorallocate($template, R * pow(DARKEN, $fac), G * pow(DARKEN, $fac), B * pow(DARKEN, $fac));
            rounded_rect($template, $rect_x, $rect_y, $rect_size, $rect_size, $scolor);
            $bbox = imagettfbbox(16, 0, $font_medium, $stream);
            $width = $bbox[2] - $bbox[0];
            $height = $bbox[3] - $bbox[5];
            imagettftext($template, 16, 0, $rect_x + $rect_size + 10, $rect_y + $rect_size + 1 - floor($height / 2), $black, $font_medium, $stream);

            //draw language below stream name, if it's not english
            $stream_obj = new Models\Stream(['real_name' => $stream]);
            $language = $stream_obj->get('language');
            if(!empty($language) && $language != 'en' && isset($languages[$language])) {
                $bbox = imagettfbbox(10, 0, $font_medium, $languages[$language]);
                $lwidth = $bbox[2] - $bbox[0];
                imagettftext($template, 10, 0, $rect_x + $rect_size + 10 + ceil($width / 2) - ceil($lwidth / 2), $rect_y + $rect_size + 1 + 10, $black, $font_medium, $languages[$language]);
            }

            $rect_x += $rect_size + 10 + $width + 35;
            $i += 1;
        }

        //matches
        $matches = Models\Match::find(['return' => Lib\Model::RETURN_OBJECTS, 'where' => ['event = ?' => [$event->get_ID()]]]);
        $last = $chart['legend'][count($chart['legend']) - 1];

        $fac = 2; //darker version of base color for boxes
        $dark = imagecolorallocate($template, R * pow(DARKEN, $fac), G * pow(DARKEN, $fac), B * pow(DARKEN, $fac));

        $bound = 0;
        uasort($matches, function($a, $b) {
            if($a->get('start') > $b->get('start')) {
                return 1;
            } elseif($a->get('start') < $b->get('start')) {
                return -1;
            }

            return 0;
        });


        //this cuts some artifacts on the right side if a spline ends in a curve
        imagefilledrectangle($template, CHART_X + CHART_W - 17, CHART_Y, CHART_X + CHART_W + 1, CHART_Y + CHART_H + 3, $white);

        //draw a box for each match with the players and peak viewers
        $direction = true;
        foreach($matches as $match) {
            $mid = $match->get_max_time() - $event->get('start');
            $pct = $mid / ($event->get('end') - $event->get('start'));
            $viewers = $event->get_viewers_at($match->get('start'), $match->get('end'));
            $xy = $chart_img->getPointPosition($last, ceil(floor(count($chart['labels']) * $pct))); //get position where peak dot should be drawn
            rounded_rect($template, CHART_X + $xy[0] - 4, CHART_Y + $xy[1] - 4, 8, 8, $dark); //circle at peak

            //1.5k and 11k
            if($viewers > 10000) {
                $viewers = round($viewers / 1000, 0).'k';
            } elseif($viewers > 1000) {
                $viewers = round($viewers / 1000, 1).'k';
            }

            $matchname = $match->get('match').' ('.$viewers.')';
            $bbox = imagettfbbox(14, 0, $font_medium, $matchname);


            //draw arrow box for each match
            $width = $bbox[2] - $bbox[0];
            $height = $bbox[3] - $bbox[5];

            $margin = 10;
            $left = CHART_X + $xy[0] - ceil($width / 2) - $margin - 5;
            $right = CHART_X + $xy[0] + ceil($width / 2) + $margin + 5;

            //alternate box position if it would overlap with the previous one
            if($left < $bound) {
                $direction = !$direction;
            } else {
                $direction = true;
            }


            if($direction) {
                balloon_top($template, CHART_X + $xy[0], CHART_Y + $xy[1] - 5, $width + ($margin * 2), $height + ($margin * 2), $dark);
                imagettftext($template, 14, 0, CHART_X + $xy[0] - floor($width / 2), CHART_Y + $xy[1] - floor($height / 2) - $margin - 7, $white, $font_medium, $matchname);
            } else {
                balloon_bottom($template, CHART_X + $xy[0], CHART_Y + $xy[1] + 8, $width + ($margin * 2), $height + ($margin * 2), $dark);
                imagettftext($template, 14, 0, CHART_X + $xy[0] - floor($width / 2), CHART_Y + $xy[1] + floor($height / 2) + $margin + 19, $white, $font_medium, $matchname);
            }

            $bound = $right;
        }

        //logo tagline
        imagettftext($template, 17, 0, 1310, 165, $white, $font_black, 'eSports statistics and rankings');

        //event name, game and date¶
        $event_name = $event->get('name');
        $bbox = imagettfbbox(48, 0, $font_black, $event_name);
        $event_name_width = $bbox[2] - $bbox[0];
        if($event_name_width > 1100) {
            $event_name = $event->get('short_name');
            imagettftext($template, 48, 0, 100, 120, $white, $font_black, $event_name);
            $bbox = imagettfbbox(48, 0, $font_black, $event_name);
            $event_name_width = $bbox[2] - $bbox[0];
        }
        imagettftext($template, 48, 0, 100, 120, $white, $font_black, $event_name);

        $date = $game['name'].' - '.date('l, d F Y', $event->get('start')).' ('.time_approx($event->get('end') - $event->get('start')).')';
        imagettftext($template, 18, 0, 100, 60, $white, $font_black, $date);
        $bbox = imagettfbbox(18, 0, $font_black, $date);
        $date_width = $bbox[2] - $bbox[0];

        //divider line always as long as the widest element (name, date or stats)
        $length = max(560, $date_width, $event_name_width);
        imageline($template, 100, 139, 100 + $length, 139, $white);
        imageline($template, 100, 140, 100 + $length, 140, $white);

        //peak
        imagettftext($template, 16, 0, 100, 170, $white, $font_black, 'Peak viewers');
        imagettftext($template, 32, 0, 100, 210, $white, $font_black, number_format($event->get('peak')));

        //average
        imagettftext($template, 16, 0, 300, 170, $white, $font_black, 'Average');
        imagettftext($template, 32, 0, 300, 210, $white, $font_black, number_format($event->get('average')));

        //peak
        imagettftext($template, 16, 0, 500, 170, $white, $font_black, 'Hours watched');
        imagettftext($template, 32, 0, 500, 210, $white, $font_black, number_format($event->get('vh')));

        //ad text on bottom
        $url = 'find more stats and data at '.$game['subdomain'].'.fuzic.nl'.$event->get_url();
        $bbox = imagettfbbox(16, 0, $font_medium, $url);
        $width = $bbox[2] - $bbox[0];
        $height = $bbox[3] - $bbox[5];
        imagettftext($template, 16, 0, floor((imagesx($template) / 2) - ($width / 2)), imagesy($template) - $height - 5, $white, $font_medium, $url);

        //render to output
        header('Content-type: image/png');
        imagepng($template, $cachefile);
        imagepng($template);
    }


    /**
     * Export CSV data for event
     */
    public function export() {
        $eventID = $this->parameters['id'];

        $event = new Models\Event([Models\Event::IDFIELD => $eventID, 'game' => ACTIVE_GAME]);
        $chart = Lib\Highcharts::get_event($event);

        //get stats for each event stream
        $stream_stats = array();
        foreach ($chart['streams'] as $stream) {
            $stats = new Lib\Interval($stream, $event->get('start'), $event->get('end'));
            $stream_overview = $stats->get_stats();
            $stream_overview += $stream;
            $stream_overview['start'] = $stream['start'];
            $stream_overview['end'] = $stream['end'];
            $stream_stats[] = $stream_overview;
        }

        $csv = array(array('Time'));
        foreach($chart['legend'] as $stream) {
            $csv[0][] = $stream;
        }
        $csv[0][] = 'Total';
        $interval = 15;
        $i = 0;
        $agg = 0;
        $bank = array();
        foreach($chart['data'] as $i => $viewers) {
            if($i >= $interval && $i % $interval == 0) {
                $row = array(date('H:i', $chart['labels'][$i]));
                foreach($bank as $j => $num) {
                    $bank[$j] = floor($num / $agg);
                    $row[] = $bank[$j];
                }
                $row[] = array_sum($bank);
                $csv[] = $row;
                $bank = array();
                $agg = 0;
            } else {
                foreach($viewers as $j => $num) {
                    if(isset($bank[$j])) {
                        $bank[$j] += $num;
                    } else {
                        $bank[$j] = $num;
                    }
                }
                $agg += 1;
            }
            $i += 1;
        }

        ob_start();
        $buffer = fopen('php://output', 'w');
        fputs($buffer, $bom =( chr(0xEF) . chr(0xBB) . chr(0xBF) ));
        foreach($csv as $row) {
            fputcsv($buffer, $row);
        }
        fclose($buffer);
        $buffer = ob_get_clean();
        header('Content-type: text/csv');
        header('Content-Disposition: attachment; filename="export.csv"');
        header('Content-Length: '.strlen($buffer));
        echo $buffer;
        exit;
    }

    /**
     * Show event overview
     *
     * @access  public
     */
    public function overview() {
        $constraint = 'hidden = 0';
        $this->before_display();

        if (isset($this->parameters['franchise']) && !empty($this->parameters['franchise'])) {
            $franchise = Models\Franchise::find([
                'url' => $this->parameters['franchise'],
                'return' => 'single'
            ]);

            if (!$franchise) {
                throw new Lib\ItemNotFoundException('Invalid franchise ID');
            }

            $this->tpl->assign('franchise', $franchise);
            $this->tpl->add_breadcrumb('Franchises', '/franchises/');
            $this->tpl->add_breadcrumbs($franchise);

            $constraint .= ' AND franchise = '.$franchise[Models\Franchise::IDFIELD];
        }

        $constraint .= " AND game = '".ACTIVE_GAME."' AND start <= ".time();

        $view = $this->get_view(self::filter_view($_GET, ['where' => $constraint]));

        $this->tpl->set_title('Events');

        $this->tpl->assign('view_settings', $view);
        $this->tpl->assign('events', $view['items']);

        $this->tpl->layout('Event/overview.tpl');
    }

    /**
     * Compare two events
     *
     * @access public
     */
    public function compare() {
        try {
            $event1 = new Models\Event($this->parameters['event1']);
            $event2 = new Models\Event($this->parameters['event2']);
        } catch (Lib\ItemNotFoundException $e) {
            $this->tpl->error('That event does not exist.');
            exit;
        }

        $this->before_display();

        $month1 = Models\Event::find([
                'return' => 'count',
                'where' => [
                    'start > ?' => [period_start('month', date('n', $event1->get('start')), date('Y', $event1->get('start')))],
                    'start < ?' => [period_end('month', date('n', $event1->get('start')), date('Y', $event1->get('start')))],
                    'vh > ?' => [$event1->get('vh')]
                ]
            ]) + 1;

        $month2 = Models\Event::find([
                'return' => 'count',
                'where' => [
                    'start > ?' => [period_start('month', date('n', $event2->get('start')), date('Y', $event2->get('start')))],
                    'start < ?' => [period_end('month', date('n', $event2->get('start')), date('Y', $event2->get('start')))],
                    'vh > ?' => [$event2->get('vh')]
                ]
            ]) + 1;

        try {
            $franchise1 = new Models\Franchise($event1->get('franchise'));
            $franchise1 = $franchise1->get_all_data();
        } catch (\ErrorException $e) {
            $franchise1 = false;
        }

        try {
            $franchise2 = new Models\Franchise($event2->get('franchise'));
            $franchise2 = $franchise2->get_all_data();
        } catch (\ErrorException $e) {
            $franchise2 = false;
        }

        $overall1 = Models\Event::find(['return' => 'count', 'where' => ['vh > ?' => [$event1->get('vh')]]]) + 1;
        $overall2 = Models\Event::find(['return' => 'count', 'where' => ['vh > ?' => [$event2->get('vh')]]]) + 1;

        $this->tpl->assign('event1', ['franchise' => $franchise1, 'time' => ($event1->get('end') - $event1->get('start')), 'month' => $month1, 'all_time' => $overall1] + $event1->get_all_data());
        $this->tpl->assign('event2', ['franchise' => $franchise2, 'time' => ($event2->get('end') - $event2->get('start')), 'month' => $month2, 'all_time' => $overall2] + $event2->get_all_data());

        $this->tpl->set_title($event1->get('short_name').' compared with '.$event2->get('short_name'));
        $this->tpl->layout('Event/compare.tpl');
    }

    /**
     * Default order by for views
     *
     * @return  string    Field name by which to order
     *
     * @access protected
     */
    protected function get_default_order_by() {
        return 'end';
    }
}