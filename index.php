<?php
require_once __DIR__ . '/vendor/autoload.php';
use Symfony\Component\HttpFoundation\Request;

$app = new Silex\Application();
$app->post('/callback', function (Request $request) use ($app) {
    $client = new GuzzleHttp\Client();

    $body = json_decode($request->getContent(), true);

    define(CONTENT_TYPE_TEXT, 1);
    define(CONTENT_TYPE_IMAGE, 2);

    define(OMIKUJI_APP, 1);
    define(CAT_APP, 2);

    foreach ($body['result'] as $msg) {

        $callback = false;
        $app_type = 0;

        if (preg_match('/(fortune|おみくじ|mikuji|占い|運勢)/i', $msg['content']['text'])) {
            $app_type = OMIKUJI_APP;
        } elseif (preg_match('/(pic|image|img|cat|meow|neko|画像|ネコ|猫)/i', $msg['content']['text'])) {
            $app_type = CAT_APP;
        }

        try {
            $requestOptions = make_request($msg, $app_type);
            $client->request('post', 'https://trialbot-api.line.me/v1/events', $requestOptions);
        } catch (Exception $e) {
            error_log($e->getMessage());
        }
    }
    return 'OK';
});

$app->run();

function make_request($msg, $app_type = null) {
    $requestOptions = api_post_request($msg, $app_type);
    return $requestOptions;
}

function api_post_request($msg, $app_type = null) {
    $requestOptions = [
        'body' => json_encode([
            'to' => [$msg['content']['from']],
            'toChannel' => 1383378250, # Fixed value
            'eventType' => '138311608800106203', # Fixed value
            'content' => get_content($msg, $app_type),
        ]),
        'headers' => [
            'Content-Type' => 'application/json; charset=UTF-8',
            'X-Line-ChannelID' => getenv('LINE_CHANNEL_ID'),
            'X-Line-ChannelSecret' => getenv('LINE_CHANNEL_SECRET'),
            'X-Line-Trusted-User-With-ACL' => getenv('LINE_CHANNEL_MID'),
        ],
        'proxy' => [
            'https' => getenv('FIXIE_URL'),
        ],
    ];
    return $requestOptions;
}

function get_content($msg, $app_type = null) {
    if ($app_type == OMIKUJI_APP) {
        $ret = get_omikuji($msg);
    } elseif ($app_type == CAT_APP) {
        $ret = get_cat();
    } else {
        $content = $msg['content'];
        $content['text'] = $content['text'].'にゃ〜';
        $ret = $content;
    }
    error_log(var_export($ret, true));
    return $ret;
}

function get_cat() {
    $url_list = array();
    $xml = simplexml_load_file('http://thecatapi.com/api/images/get?format=xml');
    $img_id     = (string)$xml->data->images->image->id;
    $img_url    = (string)$xml->data->images->image->url;
    $source_url = (string)$xml->data->images->image->source_url;

    $ret['contentType'] = CONTENT_TYPE_IMAGE;
    $ret['toType'] = 1;
    $ret['originalContentUrl'] = $img_url;
    $ret['previewImageUrl']    = $img_url;
    $ret['text']               = $source_url;

    return $ret;
}

function get_omikuji ($msg) {
    $content = $msg['content'];
    $omikuji_list = array(
        100 => '吉瀬美智子',
        400 => 'カト吉',
        500 => '超吉',
        3000 => '大吉',
        3000 => '中吉',
        3000 => '吉',
    );

    $index = 0;
    foreach ($omikuji_list as $r => $l) {
        $total_index += $r;
    }

    $current_index = 0;
    uasort($omikuji_list, function() { return mt_rand(-1, 1); });
    $rand = mt_rand(0, $total_index);
    foreach ($omikuji_list as $rate => $luck) {
        $current_index += $rate;
        if ($rand <= $current_index) {
            $content['text'] = $luck.' nya!';
            return $content;
        }
    }
}
