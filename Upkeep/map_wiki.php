<?php
/**
 * Retrieve information for streams, teams and events from Liquipedia
 */
namespace Fuzic\Upkeep;

use Fuzic;
use Fuzic\Lib;
use Fuzic\Models;


chdir(dirname(__FILE__));
require '../init.php';

/**
 * Displays a message in console
 *
 * @param string $message Message to display.
 *
 * @package Fuzic
 */
function console_log($message) {
    echo $message."\n";
}

$loop = false;
$games = json_decode(file_get_contents(dirname(dirname(__FILE__)).'/games.json'), true);
$skipped = false;

/**
 * Get liquipedia information for each player, if it exists
 */
while (true) {
    //begin with a clean slate
    unset($player, $wiki_name, $stream_name);

    //get player that is notable and may have crawlable info
    $player = Models\Stream::find([
        'return' => 'single_object',
        'order_by' => 'last_crawl',
        'order' => 'asc',
        'where' => [
            'last_crawl < '.(time() - 86400),
            "last_seen > ".(time() - (86400 * 90)),
            "provider IN ('azubu', 'twitch', 'afreeca')",
            '(tl_featured = 1 OR '.Models\Stream::IDFIELD.' IN ( SELECT stream FROM '.Models\Session::TABLE.' WHERE peak > 400 ) OR team != '.Models\Team::TEAMLESS_ID.')'
        ],
        'limit' => 1
    ]);

    if(!$player) {
        console_log('('.time().') No streams that need Liquipedia crawling, waiting for '.(Fuzic\Config::CHECK_DELAY * 2).' seconds');
        sleep(Fuzic\Config::CHECK_DELAY * 2);
        continue;
    }

    $player->set('last_crawl', time());
    $player->update();

    //see what to search for
    $wiki_name = $games[$player->get('last_game')]['wiki'];
    $stream_name = $player->get('remote_id');

    try {
        /*
         * Try getting info from wikipedia first
         */
        $wiki_search = get_url('http://wiki.teamliquid.net/'.$wiki_name.'/api.php?action=query&list=search&srwhat=text&srsearch=insource:%22'.$player->get('provider').'%3D'.$stream_name.'%22&srprop=timestamp&format=json');
        $wiki_search = json_decode($wiki_search, true);
        if(!$wiki_search || empty($wiki_search['query']['search'])) {
            throw new \ErrorException('No liquipedia search result');
        }

        sleep(10); //one liquipedia request per 10 seconds
        $wiki_page = json_decode(get_url('http://wiki.teamliquid.net/'.$wiki_name.'/api.php?format=xml&action=query&titles='.urlencode($wiki_search['query']['search'][0]['title']).'&prop=revisions&rvprop=content&format=json'), true);
        $wiki_page = array_pop($wiki_page['query']['pages']);

        $page_name = $wiki_page['title'];
        $wiki_page = $wiki_page['revisions'][0]['*'];

        $info_block = preg_split('/\{\{Infobox [pP]layer/siU', $wiki_page);
        if(count($info_block) > 1) {
            //oh boy
            $info_block = $info_block[1];
            $chunks = explode('}}', $info_block);
            $i = 0;
            while(!isset($found) && $i < count($chunks)) {
                $i += 1;
                $substring = implode('}}', array_slice($chunks, 0, $i)).'}}';
                if(substr_count($substring, '{{') != substr_count($substring, '}}')) {
                    $info_block = '{{'.$substring;
                    $found = true;
                }
            }
        } else {
            $info_block = $wiki_page;
        }

        preg_match("/\|id[ ]*=[ ]*([^\n]+)\n/si", $info_block, $name);
        preg_match("/\|team[ ]*=[ ]*([^\n]+)\n/si", $info_block, $team);
        preg_match("/\|twitter[ ]*=[ ]*([^\n]+)\n/si", $info_block, $twitter);
        preg_match("/\|tlstream[ ]*=[ ]*([^\n]+)\n/si", $info_block, $tl_id);

        //gather and process the matched data
        $player->set('wiki', $wiki_name.'/'.$page_name);
        if($name) {
            $player->set('real_name', str_replace('&amp;', '&', $name[1]));
        }
        if($twitter) {
            $player->set('twitter', $twitter[1]);
        }
        if($tl_id && empty($player->get('tl_id'))) {
            $player->set('tl_id', $tl_id[1]);
        }
        if($team) {
            $team = explode('|', $team[1]);
            $team = $team[0];

            $team = str_replace(array('[[', ']]', '_'), array('', '', ' '), $team);
            $teams[] = $team;

            try {
                $team = new Models\Team(['team' => $team]);
            } catch(Lib\ItemNotFoundException $e) {
                $team = Models\Team::create([
                    'team' => $team,
                    'url' => friendly_url($team)
                ]);
            }

            $player->set('team', $team->get_ID());
        }

        $player->update();
        console_log('('.time().') Got Liquipedia info for '.$player->get('remote_id'));
        sleep(10);

    } catch (\ErrorException $e) {
        /*
         * If nothing was found on liquipedia, see if we can get something useful from the stream profile page
         */
        console_log('('.time().') Could not get useful information from liquipedia for '.$player->get('remote_id').' ('.$e->getMessage().')');
        $player->set('wiki', '');
        $player->set('team', Models\Team::TEAMLESS_ID);

        if($player->get('provider') == 'twitch') {
            try {
                $twitch_info = get_url(
                    'https://api.twitch.tv/api/channels/'.$stream_name.'/panels',
                    ['Accept' => 'application/vnd.twitchtv.v3+json', 'Client-ID' => Fuzic\Config::TWITCH_API_KEY]
                );
            } catch(\ErrorException $e) {
                console_log('('.time().') Could not fetch Twitch panels for '.$player->get('remote_id').' ('.$e->getMessage().')');
                continue;
            }
            $data = json_decode($twitch_info, true);
            if(!$data) {
                console_log('('.time().') Could not parse Twitch panels for '.$player->get('remote_id'));
            } else {

                $html = '';
                foreach($data as $panel) {
                    if(isset($panel['html_description']) && $panel['html_description']) {
                        $html .= $panel['html_description'];
                    }
                    if(isset($panel['data']) && isset($panel['data']['link']) && !empty($panel['data']['link'])) {
                        preg_match('/twitter.com\/([^\/\'">]+)/si', $panel['data']['link'], $twitter);
                        if(isset($twitter[1])) {
                            $player->set('twitter', $twitter[1]);
                        }
                    }
                }
                if(!isset($update['twitter']) || empty($update['twitter'])) {
                    preg_match_all('/twitter.com\/([^\/\'">]+)/si', $html, $twitter);
                    if(count($twitter) > 0 && count($twitter[1]) == 1) {
                        $player->set('twitter', $twitter[1][0]);
                    }
                }
                console_log('('.time().') Checked Twitch page for '.$player->get('remote_id'));
            }
        }

        $player->update();
    }

    console_log('('.time().') ---------------------- Finished crawling for '.$player->get('real_name'));
}