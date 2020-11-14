<?php
namespace Fuzic\Crawler\Index;

use Fuzic\Lib;
use Fuzic\Models;
use Fuzic\Crawler;


class TeamliquidIndexChecker extends IndexFetcher {
    const INDEX_ID = 'teamliquid';

    public function run() {
        $xml_urls = array(
            Crawler\Event\Teamliquidsc2Checker::CALENDAR_PREFIX => 'http://www.teamliquid.net/video/streams/?xml=1',
            Crawler\Event\TeamliquidhearthstoneChecker::CALENDAR_PREFIX => 'http://www.liquidhearth.com/stream/?xml=1'
        );
        $liquid_data = new \DOMDocument();
        $liquid_data->loadXML('<streams></streams>');

        //merge all source XML stream indexes
        foreach($xml_urls as $calendar_prefix => $url) {
            try {
                $liquid_response = get_url($url, ['User-Agent' => Crawler\StreamChecker::APP_ID]);
            } catch(\ErrorException $e) {
                $this->log('Could not retrieve TeamLiquid stream data from '.$url.' ('.$e->getMessage().')', Crawler\StreamChecker::LOG_WARNING);
                $liquid_response = '';
            }
            $xml_data = new \DOMDocument();
            try {
                if(empty($liquid_response) || !$xml_data->loadXML($liquid_response)) {
                    throw new \Exception('Could not load XML data');
                }
            } catch(\Exception $e) {
                $this->log('Could not retrieve TeamLiquid stream info from '.$url.' ("'.$e->getMessage().'")', Crawler\StreamChecker::LOG_WARNING);

                return;
            }
            foreach($xml_data->getElementsByTagName('stream') as $stream) {
                $stream = $liquid_data->importNode($stream, true);
                $stream->setAttribute('calendar-prefix', $calendar_prefix);
                $liquid_data->getElementsByTagName('streams')->item(0)->appendChild($stream);
            }
        }

        $streams_indexed = 0;

        //process each stream and save relevant info to an array for further processing
        foreach($liquid_data->getElementsByTagName('stream') as $stream) {
            $calendar_prefix = $stream->getAttribute('calendar-prefix');
            $channel = $stream->getElementsByTagName('channel')->item(0);
            $game = Crawler\StreamChecker::map_game_name($stream->attributes->getNamedItem('type')->nodeValue);
            $remote_ID = $channel->nodeValue;

            if(!$game) {
                continue;
            }

            if($stream->hasAttribute('owner')) {
                $tl_name = $stream->attributes->getNamedItem('owner')->nodeValue;
            } else {
                $tl_name = preg_replace('/http:\/\/www.liquid[a-z]+.(com|net)\/stream\//siU', '', $stream->getElementsByTagName('link')->item(0)->nodeValue);
            }

            $language = $stream->hasAttribute('language') ? $stream->attributes->getNamedItem('language')->nodeValue : '';

            $provider = Crawler\StreamChecker::map_provider_id($channel->attributes->getNamedItem('type')->nodeValue);

            $data = array(
                'tl_name' => $tl_name,
                'game' => $game,
                'featured' => $stream->attributes->getNamedItem('featured')->nodeValue,
                'remote_ID' => $remote_ID,
                'provider' => $provider,
                'language' => $language
            );

            if($stream->hasAttribute('event-id')) {
                $data['event'] = $stream->getAttribute('event-id');
                $data['event_internal_ID'] = $calendar_prefix.$stream->getAttribute('event-id');
            }
            $this->add_stream($data);

            $streams_indexed += 1;
        }

        $this->log('TeamLiquid profiles acquired: '.$streams_indexed, Crawler\StreamChecker::LOG_NOTICE);
    }
}