<?php
$domain = "https://animesilver.com,https://www.animesilver.com,http://animesilver.com,http://www.animesilver.com";
header("Access-Control-Allow-Origin: " . $domain);
header("Access-Control-Allow-Credentials: true");

//ignore timeout
ini_set('max_execution_time', 0);

function curl_write_flush($curl_handle, $chunk)
{
    echo $chunk;

    ob_flush(); // flush output buffer (Output Control configuration specific)
    flush();    // flush output body (SAPI specific)

    return strlen($chunk); // tell Curl there was output (if any).
};

//setting cookie on post request on condition
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_SERVER['HTTP_REFERER']))
        $referer = $_SERVER['HTTP_REFERER'];
    else {
        header('HTTP/1.1 404 File Not Found');
        echo '404 File Not Found';
        exit();
    }
    $md5 = $_POST['c'];
    $referer = "animesilveryousnoozyoulose" . $referer;
    $md5_2 = md5($referer);

    if ($md5 == $md5_2) {
        setcookie("w", md5($md5_2), time() + 10800, "/", "", false, false);
        exit();
    } else {
        exit();
    }
}

if (isset($_GET['e']) && isset($_GET['b'])) {
    $enc = $_GET['e'];
    $key = $_GET['b'];
}

if (isset($enc) && isset($key) && isset($_COOKIE['w'])) {
    //check referer to confirm that request camed from animesilver.com
    if (isset($_SERVER['HTTP_REFERER'])) {
        $referer = $_SERVER['HTTP_REFERER'];
        if (!strpos($referer,"animesilver.com")) {
            header('HTTP/1.1 400 Bad Request');
            echo '400 Bad Request';
            exit();
        }
    } else {
        header('HTTP/1.1 403 Forbidden');
        echo '403 Forbidden';
        exit();
    }

    $keys = array("mM1MwSstiZ", "jhKuTVrMNw", "evVqiIwJnb", "BN3Utqw3Hy", "K8VIP9REFe", "yftMzx8Niz", "qNGmebI2NQ", "hTk2y4kzsf", "tIs27kFnn6", "c9oAiaFcST", "t13G8FyS90", "DRZvWF6xPk");

    $key_index = rand(0, 11);
    $encrypt_method = "AES-256-CBC";
    $secret_iv = "you snooze you lose";

    $key = hash('sha256', $keys[$key]);

    $iv = substr(hash('sha256', $secret_iv), 0, 16);

    $id_quality = openssl_decrypt(base64_decode($enc), $encrypt_method, $key, 0, $iv);

    //get mid and quality
    $mid = explode('&', $id_quality)[0];
    $quality = explode('&', $id_quality)[1];

    //generate a request for the api
    $api_url = "https://ok.ru/dk?cmd=videoPlayerMetadata&mid=" . $mid; //1261715917562
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $api_url);
    curl_setopt($ch, CURLOPT_POST, 1);

    $headers = ['User-Agent: Mozilla/5.0 (X11; Linux x86_64; rv:60.0) Gecko/20100101 Firefox/60.0', 'Content-Type: text/plain;charset=UTF-8'];
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    //get response
    $res = curl_exec($ch);
    curl_close($ch);

    //convert response to json
    $json_res = json_decode($res);
    $videos = $json_res->videos;
    $url = "";
    foreach ($videos as $video) {
        /*       if ($quality=='140p' && $video->name =="mobile")
        {
            $url=$video->url;
        }
        else if ($quality=='240p' && $video->name =="lowest")
        {
            $url=$video->url;
        }*/
        if ($quality == '360p' && $video->name == "low") {
            $url = $video->url;
        }
        /*else if ($quality=='480p' && $video->name =="sd")
        {
            $url=$video->url;
        }
        else if ($quality=='720p' && $video->name =="hd")
        {
            $url=$video->url;
        }
        else if ($quality=='1080p' && $video->name =="full")
        {
            $url=$video->url;
        }*/
    }

    //Check if 360p exists
    if ($url=="")
    {
        header('HTTP/1.1 404 File Not Found');
        echo '404 File Not Found';
        exit();
    }

    //getting the content length of file
    $useragent = "Mozilla/5.0 (X11; Linux x86_64; rv:60.0) Gecko/20100101 Firefox/60.0";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_VERBOSE, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 222222);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, $useragent);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $info = curl_exec($ch);
    $size2 = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
    $filesize = $size2;
    $offset = 0;
    $length = $filesize;

    //headers for the request
    $headers = array();

    //adding range header to request header if user jumped in the video
    if (isset($_SERVER['HTTP_RANGE'])) {
        $partialContent = "true";
        preg_match('/bytes=(\d+)-(\d+)?/', $_SERVER['HTTP_RANGE'], $matches);
        $offset = intval($matches[1]);
        $length = $size2 - $offset - 1;
    } else {
        $partialContent = "false";
    }
    if ($partialContent == "true") {
        header('HTTP/1.1 206 Partial Content');
        header('Accept-Ranges: bytes');
        header('Content-Range: bytes ' . $offset .
            '-' . ($offset + $length) .
            '/' . $filesize);
        $new_length = $filesize - $offset;
        header('Content-length: ' . $new_length);
    } else {
        header("Content-length: " . $size2);
        header('Accept-Ranges: bytes');
    }

    //initialize curl 
    $ch = curl_init();
    //adding range headers for the request
    if (isset($_SERVER['HTTP_RANGE'])) {
        $partialContent = true;
        preg_match('/bytes=(\d+)-(\d+)?/', $_SERVER['HTTP_RANGE'], $matches);
        $offset = intval($matches[1]);
        $length = $filesize - $offset - 1;
        array_push($headers, 'Range: bytes=' . $offset .
            '-' . ($offset + $length) .
            '');
    }

    //adding user-agent and other headers for the request header
    array_push($headers, 'User-Agent: Mozilla/5.0 (X11; Linux x86_64; rv:60.0) Gecko/20100101 Firefox/60.0');
    array_push($headers, 'Accept-Language: en-US,en;q=0.5');
    array_push($headers, 'Accept-Encoding: gzip, deflate, br');

    //setting response header to video/mp4 to play video
    header("Content-Type: video/mp4");

    curl_setopt($ch, CURLOPT_VERBOSE, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 222222);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_NOBODY, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIE, 'tstc=p');
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, 'curl_write_flush'); //calling for chunked function on request declared above
    //streaming video
    curl_exec($ch);
    curl_close($ch);
    exit();
} else {
    header('HTTP/1.1 403 Forbidden');
    echo '403 Forbidden';
    exit();
}
