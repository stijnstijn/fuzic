<?php
namespace Fuzic\Tools;

use Fuzic\Lib;
use Fuzic\Models;


chdir(dirname(__FILE__));
require '../init.php';

$id = trim(file_get_contents('linker.id'));
if (empty($id)) {
    $id = 0;
}

$events = Models\Event::find(['where' => [Models\Event::IDFIELD.' >= 5723 AND end > ?' => [1436572741]]]);
foreach ($events as $event) {
    echo print chr(27)."[2J".chr(27)."[;H";
    $event_pot = array();
    $sessions = Models\Session::find(['where' => [
        'game = ? AND peak > 50 AND ((start < ? AND end > ?) OR (start < ? AND end > ?) OR (start > ? AND end < ?))' => [$event['game'], $event['start'], $event['start'], $event['end'], $event['end'], $event['start'], $event['end']]
    ]
    ]);

    if (count($sessions) > 0) {
        foreach ($sessions as $session) {
            $do = false;
            $stream = new Models\Stream($session['stream']);

            $astart = ($session['start'] < $event['start']) ? $event['start'] : $session['start'];
            $diffs = $event['start'] - $session['start'];
            if ($diffs < 0) {
                $diffs = 0;
            }
            $aend = ($session['end'] > $event['end']) ? $event['end'] : $session['end'];
            $diffe = $session['end'] - $event['end'];
            $diff = $diffs + $diffe;
            if ($diff < 0) {
                $diff = 0;
            }
            $time = ($aend - $astart) - $diff;
            if ($time < (($event['end'] - $event['start']) / 2)) {
                continue;
            }

            $overlap = (round($time / ($event['end'] - $event['start']), 2) * 100);

            $more = Models\EventStream::find(['where' => ['stream = ?' => [$stream->get_ID()]], 'return' => Lib\Model::RETURN_AMOUNT]);
            $franch = Models\EventStream::find(['where' => ['stream = ? AND event IN ( SELECT '.Models\Event::IDFIELD.' FROM '.Models\Event::TABLE.' WHERE franchise = ? )' => [$stream->get_ID(), $event['franchise']]], 'return' => Lib\Model::RETURN_AMOUNT]);

            $event_pot[$session[Models\Session::IDFIELD]] = ['session' => $session, 'pct' => $overlap, 'stream' => $stream, 'franch' => $franch, 'more' => $more];
        }
    }

    $amount = Models\EventStream::find(['where' => ['event = ?' => [$event[Models\Event::IDFIELD]]], 'return' => Lib\Model::RETURN_AMOUNT]);

    echo "\n".$event['name'].", currently ".$amount." streams linked\n";
    foreach ($event_pot as $sessid => $data) {
        $bit = ($data['franch'] > 15 && $data['more'] > 15) ? ', will be auto-linked' : '';
        echo '  '.$data['stream']->get('real_name').'/'.$data['stream']->get('name').' ('.$data['pct'].'%, '.$data['franch'].' in franchise'.$bit.')'."\n";
    }
    echo "\n";
    foreach ($event_pot as $sessid => $data) {
        $cmd = '';
        echo 'Link '.$data['stream']->get('real_name').'/'.$data['stream']->get('name').' ('.$data['pct'].'%, '.$data['franch'].' in franchise)? ';
        if (!($data['franch'] > 15 && $data['more'] > 15) && !($data['pct'] > 90)) {
            $cmd = ''; //trim(fgets(STDIN));
            $do = ($cmd == 'y' || $cmd == '1');
        } else {
            $do = true;
            echo "\n";
        }
        if ($do) {
            $start = ($data['session']['start'] < $event['start']) ? $event['start'] : $data['session']['start'];
            $end = ($data['session']['end'] > $event['end']) ? $event['end'] : $data['session']['end'];
            if (Models\EventStream::find(['where' => ['event = ? AND stream = ?' => [$event[Models\Event::IDFIELD], $data['stream']->get_ID()]], 'return' => Lib\Model::RETURN_BOOLEAN])) {
                echo '    skipped, exists already'."\n";
                continue;
            }
            Models\EventStream::create([
                'event' => $event[Models\Event::IDFIELD],
                'stream' => $data['stream']->get_ID(),
                'start' => $start,
                'end' => $end
            ]);
        }
    }

    echo print chr(27)."[2J".chr(27)."[;H";
}