<?php

chdir(dirname(__FILE__));

// PHP settings
error_reporting(E_ALL);
ini_set('display_errors', 1);

ini_set('max_execution_time', 0);
set_time_limit(0);

// Settings
$settings = json_decode(file_get_contents('settings.json'), true);

$feeds = $settings['feeds'];
$time_limit = strtotime($settings['time_limit']);
$database = $settings['database'];
$timeout = $settings['timeout'];
$user_agent = $settings['user_agent'];

// Includes
require_once 'vendor/autoload.php';

// Logging utilities
$logs = array();
$logs_start = time();

function info($message) {
	global $logs;
	$logs[] = '[' . date('Y-m-d H:i:s') . '] - ' . $message;
}

function logs_as_string($separator = PHP_EOL) {
	global $logs;
	return implode($separator, $logs);
}

function finish() {
	if (php_sapi_name() == 'cli') {
		echo logs_as_string();
	}
	else {
		$log = mb_convert_encoding(logs_as_string('<br>'), 'HTML-ENTITIES', 'UTF-8');
		echo '<code>' . $log . '</code>';
	}
	die();
}

// Misc functions
function in_array_deep($needle, $needle_field, $haystack) {
    foreach ($haystack as $item)
        if (isset($item[$needle_field]) && $item[$needle_field] == $needle)
            return true;
}

function filter_content($content) {
    $c = htmlentities(substr(trim(strip_tags($content)), 0, 128));

	if (strlen($c) == 0)
		$c = '[...]';

    return mb_convert_encoding($c, "UTF-8", "HTML-ENTITIES");
}

// Open existing json database (if any)
$output = array();

if (file_exists($database)) {
    info('Opening existing database');
    $output = json_decode(file_get_contents($database), true);
}
else {
    info('No database found, creating a new one');
}

// Remove older entries
info('Removing older entries');

$count = count($output);
$output = array_filter($output, function($item) {
    global $time_limit;
    return $item['date'] >= $time_limit;
});

info('Removed ' . ($count - count($output)) . ' item(s)');

// Feed crawler
use PicoFeed\Config\Config;
use PicoFeed\Reader\Favicon;
use PicoFeed\Reader\Reader;
use PicoFeed\Scraper\Scraper;
use PicoFeed\PicoFeedException;

$items_added = 0;
$grabber = new Scraper();

foreach ($feeds as $feed) {
    $name = $feed['name'];
    $url = $feed['url'];
    $scraper = $feed['scraper'];

    $source_hash = md5($url);

    try {
        $config = new Config;
        $config->setClientUserAgent('My custom RSS Reader')
               ->setClientTimeout($timeout)
               ->setClientUserAgent($user_agent)
               ->setGrabberTimeout($timeout)
               ->setGrabberUserAgent($user_agent);

        $reader = new Reader;
        $resource = $reader->download($url);

        $parser = $reader->getParser(
            $resource->getUrl(),
            $resource->getContent(),
            $resource->getEncoding()
        );

        $feed = $parser->execute();

        // Fetch favicon
        {
            $favicon = new Favicon;
            $icon_link = $favicon->find($feed->getSiteUrl());
            $file_path = './cache/' . $source_hash . '.png';

            if (file_exists($file_path) && filemtime($file_path) > $time_limit) {
                info('Skipped favicon fetching for "' . $name . '"');
            }
            else if (strlen($icon_link) > 0) {
                info('Getting favicon for "' . $name . '"');
                $icon_content = $favicon->getContent();
                file_put_contents($file_path, $icon_content);
            }
            else {
                info('Favicon not found for "' . $name . '"');
            }
        }

        $count = 0;
        foreach ($feed->items as $item) {
            $timestamp = $item->getDate()->getTimestamp();
            $id = $item->getId();

            // Skip old items
            if ($timestamp < $time_limit)
                continue;

            // Skip if item exists
            if (in_array_deep($id, 'id', $output))
                continue;

			$item_url = $item->getUrl();
			$content = '';

			if ($scraper)
			{
				$grabber->setUrl($item_url);
				$grabber->execute();
				$content = $grabber->hasRelevantContent()
					? $grabber->getFilteredContent()
					: $item->getContent();
			}
			else $content = $item->getContent();

            $output[] = array(
                'source' => $name,
                'source_hash' => $source_hash,
                'id' => $id,
                'title' => filter_content($item->getTitle()),
                'url'=> $item_url,
                'date' => $timestamp,
                'excerpt' => filter_content($content),
                'content' => $content
            );

            $count++;
        }

		$items_added += $count;

        info('Parsed "' . $name . '" (' . $count . ' items)');
    }
    catch (PicoFeedException $e) {
        info('Caught exception ("' . $name . '"): ' . $e->getMessage());
    }
}

info('Added ' . $items_added . ' item(s)');

// Sort feeds by date
usort($output, function($item1, $item2) {
    if ($item1['date'] == $item2['date']) return 0;
    return $item1['date'] > $item2['date'] ? -1 : 1;
});

// Output to json
$json = json_encode($output);
file_put_contents($database, $json);

// Debug
//echo '<pre>' . var_export($output, true) . '<pre>';
$logs[] = 'Total elapsed time: ' . (time() - $logs_start) . ' second(s)';
finish();

?>
