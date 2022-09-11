<?php

function RESTServer(): void
{
    // find the function/method to call
    $callback = null;
    if (preg_match('#rest\/([^\/]+)#i', $_SERVER['REQUEST_URI'], $m) && isset($GLOBALS['RESTmap'][$_SERVER['REQUEST_METHOD']][$m[1]])) {
        $callback = $GLOBALS['RESTmap'][$_SERVER['REQUEST_METHOD']][$m[1]];
    }

    if ($callback) {
        // get the request data
        $data = null;
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $data = $_GET;
        } elseif ($tmp = file_get_contents('php://input')) {
            $data = json_decode($tmp, null, 512, JSON_THROW_ON_ERROR);
        }

        $response = call_user_func($callback, $data);
        if (is_scalar($response)) {
            print $response;
            return;
        }

        print json_encode($response, JSON_THROW_ON_ERROR);
    }
}
