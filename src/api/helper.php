<?php
function getDb() {
  $host   = 'localhost';
  $dbname = 'cvrcek';
  $user   = 'cvrcek';
  $pass   = 'heslo';

  // $pdo = new PDO("pgsql:host=" . $settings['host'] . ";dbname=" . $settings['dbname'] . ";options='-c client_encoding=utf8'",
  //     $settings['user'], $settings['pass']);
  // $pdo = new PDO("pgsql:host=localhost;dbname=cvrcek;options='--client_encoding=utf8'", "cvrcek", "h3sl0");
  // $pdo = new PDO("pgsql:host=" . $host . ";dbname=" . $dbname, $user, $pass);
  $pdo = new PDO("pgsql:host=" . $host . ";dbname=" . $dbname . ";user=" . $user . ";password=" . $pass);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
  return $pdo;
}

function postgresToPhpArray($postgresArray) {
  if ($postgresArray && $postgresArray != "{}") {
    return explode(",", trim($postgresArray, "{}"));
  }
  return null;
}
  
function phpToPostgresArray($phpArray) {
  return "{".join(",", $phpArray)."}";
}

function getJson($request) {
  return json_decode($request->getBody());
}

function getResponse($response, $data = NULL, $status = NULL) {
  if ($data) {
    $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
  } else if (!$status) {
    $status = 204;
  }
  if ($status) {
    return $response
        // ->withHeader('Access-Control-Allow-Origin', '*')
        // ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        // ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
        ->withHeader('Content-Type', 'application/json; charset=utf-8')
        ->withStatus($status);
  } else {
    return $response
        // ->withHeader('Access-Control-Allow-Origin', '*')
        // ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        // ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
        ->withHeader('Content-Type', 'application/json; charset=utf-8');
  }
}

function getErrorResponse($response, $data, $status = NULL) {
  return getResponse($response, $data, $status == NULL ? 500 : $status);
}
