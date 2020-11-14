<?php
namespace Fuzic\Crawler\Stream;

use Fuzic\Lib;
use Fuzic\Models;
use Fuzic\Crawler;


/**
 * Class AfreecaChecker
 *
 * Checks the Afreeca API to acquire viewer data for live streams using that service.
 *
 * @package Fuzic
 */
class AfreecaChecker extends StreamProviderFetcher {
    /**
     * ID prefix as used for streams from this provider in the database
     */
    const PROVIDER_PREFIX = 'af-';
    /**
     * Identifier used by TeamLiquid to mark streams as using this service
     */
    const LIQUID_PROVIDER_ID = 'Afreeca';
    /**
     * Identifier used internally for this service
     */
    const DB_PROVIDER_ID = 'afreeca';

    /**
     * Gather data about currently broadcasting streams from the Afreeca API
     *
     * The Afreeca API contains info about which game a stream is broadcasting. Therefore, the game is determined by
     * the API response and all streams using the server are reported rather than only those listed on external indexes.
     */
    public function run() {
        try {
            $afreeca_data = substr(str_replace('var oBroadListData = ', '', get_url('http://live.afreeca.com:8057/afreeca/broad_list_api.php', array(), false, false, 10)), 0, -1);
        } catch(\ErrorException $e) {
            $this->log('No connection to Afreeca while fetching stream list', Crawler\StreamChecker::LOG_WARNING);

            return false;
        }

        //afreeca sends super shitty malformed json
        $afreeca_data = str_replace(array('\&', "\t"), array('&', "\\t"), fix_JSON(@iconv("CP949", "UTF-8", $afreeca_data)));
        $afreeca_data = json_decode($afreeca_data, true);

        if(!$afreeca_data || empty($afreeca_data)) {
            $this->log('Empty Afreeca response.', Crawler\StreamChecker::LOG_WARNING);

            return;
        }

        $afreeca_data = $afreeca_data['CHANNEL']['REAL_BROAD']; //byzanthine format

        $game_map = array();
        foreach($this->games as $code => $data) {
            foreach($data['afreeca'] as $cat_id) {
                $game_map[$cat_id] = $code;
            }
        }

        foreach($afreeca_data as $stream) {
            if(!isset($game_map[$stream['broad_cate_no']])) {
                continue;
            }

            $game = $game_map[$stream['broad_cate_no']];

            $this->add_stream($stream['user_id'], array(
                'viewers' => $stream['total_view_cnt'],
                'name' => $stream['user_id'],
                'real_name' => $stream['user_id'],
                'avatar' => 'http://stimg.afreeca.com/LOGO/'.substr($stream['user_id'], 0, 2).'/'.$stream['user_id'].'/'.$stream['user_id'].'.jpg',
                'title' => $stream['broad_title'],
                'provider' => static::DB_PROVIDER_ID,
                'game' => $game
            ));
        }
    }
}