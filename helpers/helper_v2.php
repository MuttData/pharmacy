<?php

function v2_fetch($url, $method = 'GET', $content = []) {

  $context = stream_context_create([
      "http" => [
          'method'  => $method,
          'content' => json_encode($content),
          'header'  => "Content-Type: application/json\r\n".
                       "Accept: application/json\r\n".
                       "Authorization: Basic ".base64_encode(V2_USER.':'.V2_PWD)
      ]
  ]);

  $response = file_get_contents(V2_IP.$url, false, $context);
  return json_decode($response, true);
}
