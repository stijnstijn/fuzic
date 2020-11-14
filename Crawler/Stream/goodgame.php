<?php
namespace Fuzic\Crawler\Stream;

use Fuzic\Lib;
use Fuzic\Models;
use Fuzic\Crawler;


/**
 * Class GoodgameChecker
 *
 * Checks the Goodgame.ru API to acquire viewer data for live streams using that service.
 *
 * @package Fuzic
 */
class GoodgameChecker extends StreamProviderFetcher {
    /**
     * ID prefix as used for streams from this provider in the database
     */
    const PROVIDER_PREFIX = 'g-';
    /**
     * Identifier used by TeamLiquid to mark streams as using this service
     */
    const LIQUID_PROVIDER_ID = 'Goodgame';
    /**
     * Identifier used internally for this service
     */
    const DB_PROVIDER_ID = 'goodgame';

    /**
     * Gather data about currently broadcasting streams from the Goodgame.ru API
     *
     * This service's API contains info about which game a stream is broadcasting. Therefore, the game is determined by
     * the API response and all streams using the server are reported rather than only those listed on external indexes.
     */
    public function run() {
        foreach($this->games as $code => $game) {
            if(!isset($game['goodgame'])) {
                continue;
            }

            foreach($game['goodgame'] as $url_bit) {
                try {
                    $source = get_url('http://goodgame.ru/api/getchannelsbygame?game='.$url_bit);
                } catch(\ErrorException $e) {
                    continue;
                }

                $xml = new \DOMDocument();
                if(!@$xml->loadXML($source)) {
                    $this->log('Could not load GG.ru XML for '.$code, Crawler\StreamChecker::LOG_WARNING);
                    continue;
                }

                $streams = $xml->getElementsByTagName('stream');
                foreach($streams as $stream) {
                    $id = trim($stream->getElementsByTagName('key')->item(0)->nodeValue);
                    $status = $stream->getElementsByTagName('status')->item(0)->nodeValue;

                    if(strtolower(trim($status)) != 'live') {
                        continue;
                    }

                    if(strpos($stream->getElementsByTagName('embed')->item(0)->nodeValue, 'twitch.tv') !== false) {
                        continue;
                    }

                    $this->add_stream($id, array(
                        'viewers' => $stream->getElementsByTagName('viewers')->item(0)->nodeValue,
                        'name' => $stream->getElementsByTagName('key')->item(0)->nodeValue,
                        'avatar' => $stream->getElementsByTagName('img')->item(0)->nodeValue,
                        'title' => $stream->getElementsByTagName('title')->item(0)->nodeValue,
                        'provider' => static::DB_PROVIDER_ID,
                        'game' => Crawler\StreamChecker::map_game_name($game['name'])
                    ));
                }
            }
        }
    }
}