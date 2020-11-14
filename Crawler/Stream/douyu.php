<?php
namespace Fuzic\Crawler\Stream;

use Fuzic\Lib;
use Fuzic\Models;
use Fuzic\Crawler;


/**
 * Class DouyuChecker
 *
 * Checks the Douyu.tv API to acquire viewer data for live streams using that service.
 *
 * @package Fuzic
 */
class InactiveDouyuChecker extends StreamProviderFetcher {
    /**
     * ID prefix as used for streams from this provider in the database
     */
    const PROVIDER_PREFIX = 'dy-';
    /**
     * Identifier used by TeamLiquid to mark streams as using this service
     */
    const LIQUID_PROVIDER_ID = 'Douyutv';
    /**
     * Identifier used internally for this service
     */
    const DB_PROVIDER_ID = 'douyu';

    /**
     * Gather data about currently broadcasting streams from the Douyu.tv API
     *
     * This service's API contains contains info about which game a stream is broadcasting. Therefore, the game is
     * determined by the API response and all streams using the server are reported rather than only those listed on
     * external indexes.
     */
    public function run() {
        $time = time();

        foreach($this->games as $code => $data) {
            if(!isset($data['douyu'])) {
                continue;
            }

            try {
                $html = get_url('http://www.douyutv.com/directory/game/'.$data['douyu']);
            } catch(\ErrorException $e) {
                $this->log('No connection to Douyu.tv', Crawler\StreamChecker::LOG_WARNING);
                continue;
            }


            $dom = new \DOMDocument();
            @$dom->loadHTML($html);

            if(!$dom) {
                $this->log('Could not parse HTML for Douyu, game: '.$code, Crawler\StreamChecker::LOG_WARNING);
                continue;
            }

            $list = $dom->getElementById('item_data');
            if(!$list) {
                $this->log('Could not parse item list for Douyu, game: '.$code, Crawler\StreamChecker::LOG_WARNING);
                continue;
            }

            $streams = $list->getElementsByTagName('li');
            foreach($streams as $stream) {
                $streamDOM = new \DOMDocument();
                $streamDOM->appendChild($streamDOM->importNode($stream, true));
                $streamHTML = $streamDOM->saveHTML();

                preg_match('/<a href="\/([^"]+)" class="list"/siU', $streamHTML, $id);
                preg_match('/<span class="view">([^<]+)<\/span>/siU', $streamHTML, $viewers);
                preg_match('/<span class="nnt">([^<]+)<\/span>/siU', $streamHTML, $name);
                preg_match('/<h1 class="title">([^<]+)<\/h1>/siU', $streamHTML, $title);

                if(empty($id[1]) || empty($viewers[1]) || empty($name[1]) || empty($title[1])) {
                    $this->log('Invalid data for Douyu stream for game '.$code, Crawler\StreamChecker::LOG_WARNING);
                    continue;
                }

                if(strpos($viewers[1], '&#19975') !== false) {
                    $viewers[1] = floatval(str_replace('&#19975', '', $viewers[1])) * 10000;
                }

                $h = fopen('douyu_log', 'a');
                fwrite($h, $time.'|'.$code.'|'.$id[1].'|'.$viewers[1].'|'.$title[1]."\n");
                fclose($h);

                $this->add_stream(trim($id[1]), array(
                    'viewers' => 0,
                    'name' => $name[1],
                    'title' => $title[1],
                    'provider' => static::DB_PROVIDER_ID,
                    'game' => Crawler\StreamChecker::map_game_name($data['name'])
                ));
            }
        }
    }
}