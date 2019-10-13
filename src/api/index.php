<?php
// use Psr\Container\ContainerInterface as Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
// use Slim\Extras\Middleware\HttpBasicAuth;
use Tuupola\Middleware\HttpBasicAuthentication as HttpBasicAuthentication;
use Tuupola\Middleware\HttpBasicAuthentication\PdoAuthenticator as PdoAuthenticator;
// use Slim\Middleware\HttpBasicAuthentication as HttpBasicAuthentication;
// use Slim\Middleware\HttpBasicAuthentication\PdoAuthenticator;

require __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/helper.php';

// $container = new Container();
// $container->set('upload_directory', __DIR__ . '/uploads');
// AppFactory::setContainer($container);

$app = AppFactory::create();

// $container = $app->getContainer();
// $container->set('upload_directory', __DIR__ . '/uploads');

$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);

$app->add(new HttpBasicAuthentication([
    "path" => "/api/",
    // "secure" => true,
    "ignore" => ["/api/prihlasenie"],
    // "realm" => "Protected",
    // "relaxed" => ["127.0.0.1", "localhost"],
    "authenticator" => new PdoAuthenticator([
        "pdo" => getDb(),
        "table" => "uzivatel",
        "user" => "prezyvka",
        "hash" => "heslo"
    ])
    // "users" => [
    //     "cvrcek" => "$2y$10$4Kji0NiLK4.Br4Py8XBpeejd03.dtSqi1smbSoGpGlRgAFlaWnhMy"
    // ]
]));

// $app->add(new HttpBasicAuth('user', 'pass'));

/*$container = $app->getContainer();
$container['db'] = function ($c) {
    $settings = $c->get('config')['db'];
    // $settings = $config['db'];
    $pdo = new PDO("pgsql:host=" . $settings['host'] . ";dbname=" . $settings['dbname'],
        $settings['user'], $settings['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
};*/

$app->get('/api/', function (Request $request, Response $response, $args) {
    return getResponse($response, "API pre CVrČkov Informačný Systém!");
});

$app->post('/api/prihlasenie', function (Request $request, Response $response, $args) {
    $data = getJson($request);
    $prezyvka = stripslashes($data->prezyvka);
    $heslo = stripslashes($data->heslo);

    $db = getDb();
    $stmt = $db->prepare("SELECT * FROM uzivatel WHERE prezyvka = :prezyvka");
    $stmt->execute(['prezyvka' => $prezyvka]);
    $data = $stmt->fetch();

    $db = null;
    if ($data) {
      if (password_verify($heslo, $data['heslo'])) {
        $token = base64_encode($prezyvka . ":" . $heslo);
        return getResponse($response, ['token' => $token]);
      } else {
        return getErrorResponse($response, ['message' => 'Incorrect password'], 403);
      }
    }
    return getErrorResponse($response, ['message' => 'User not found'], 401);
});

$app->get('/api/heslo/{heslo}', function (Request $request, Response $response, $args) {
    // $user = "root";
    // $hash = password_hash("t00r", PASSWORD_DEFAULT);
    // $status = $pdo->exec("INSERT INTO users (user, hash) VALUES ('{$user}', '{$hash}')");

    $heslo = $args['heslo'];
    $hash = password_hash($heslo, PASSWORD_DEFAULT);
    return getResponse($response, ['heslo' => $heslo, 'hash' => $hash]);
});

/* Uzivatelia */

$app->post('/api/uzivatel', function (Request $request, Response $response, array $args) {
    $data = getJson($request);
    $hash = password_hash($data->heslo, PASSWORD_DEFAULT);

    $db = getDb();
    $stmt = $db->prepare("INSERT INTO uzivatel (id, prezyvka, heslo, email, meno, priezvisko, titul, veduci) VALUES (:prezyvka, :heslo, :email, :meno, :priezvisko, :titul)");
    $result = $stmt->execute(['prezyvka' => $data->prezyvka, 'heslo' => $hash, 'email' => $data->email, 'meno' => $data->meno, 'priezvisko' => $data->priezvisko, 'titul' => $data->titul]);
    
    $db = null;
    if ($result) {
        return getResponse($result);
    }
    return getErrorResponse($response, ['message' => 'Failed to post Veduci'], 500);
});

/* Veduci */

$app->get('/api/veduci', function (Request $request, Response $response, array $args) {
    $db = getDb();
    $stmt = $db->prepare("SELECT * FROM uzivatel WHERE veduci = TRUE ORDER BY meno, priezvisko");
    $stmt->execute();
    $data = $stmt->fetchAll();
    
    $db = null;
    return getResponse($response, $data);
});

$app->get('/api/veduci/{id}', function (Request $request, Response $response, array $args) {
    $id = $args['id'];

    $db = getDb();
    $stmt = $db->prepare("SELECT * FROM uzivatel WHERE id=:id AND veduci = TRUE");
    $stmt->execute(['id' => $id]); 
    $data = $stmt->fetch();
    
    $db = null;
    if ($data) {
      return getResponse($response, $data);
    }
    return getErrorResponse($response, ['message' => 'Veduci not found'], 400);
});

/* Kruzky */

$app->get('/api/kruzky', function (Request $request, Response $response, array $args) {
    $db = getDb();
    $stmt = $db->prepare("SELECT k.*, COUNT(u.*) pocetUcastnikov FROM kruzok k INNER JOIN ucastnik u ON k.id = ANY (u.kruzky) GROUP BY k.id ORDER BY nazov");
    $stmt->execute();
    $data = $stmt->fetchAll();
    
    $db = null;
    return getResponse($response, $data);
});

$app->get('/api/kruzok/{id}', function (Request $request, Response $response, array $args) {
    $id = $args['id'];

    $db = getDb();
    $stmt = $db->prepare("SELECT * FROM kruzok WHERE id=:id");
    $stmt->execute(['id' => $id]);
    $data = $stmt->fetch();
    
    if ($data) {
        $stmt = $db->prepare("SELECT u.id, u.pohlavie, u.meno, u.priezvisko, p.poplatok, p.stav FROM ucastnik u INNER JOIN poplatky p ON (p.ucastnik = u.id AND p.kruzok = :id) WHERE :id = ANY(kruzky) ORDER BY priezvisko, meno");
        $stmt->execute(['id' => $id]);
        $ucastnici = $stmt->fetchAll();
        $data['ucastnici'] = $ucastnici;
    }
    
    $db = null;
    return getResponse($response, $data);
});

$app->post('/api/kruzok', function (Request $request, Response $response, array $args) {
    $data = getJson($request);

    $db = getDb();
    $stmt = $db->prepare("INSERT INTO kruzok (nazov, veduci) VALUES (:nazov, :veduci)");
    $result = $stmt->execute(['nazov' => $data->nazov, 'veduci' => $data->veduci]);
    
    $db = null;
    if ($result) {
        return getResponse($result);
    }
    return getErrorResponse($response, ['message' => 'Failed to post Kruzok'], 500);
});

$app->patch('/api/kruzok/{id}', function (Request $request, Response $response, array $args) {
    $data = getJson($request);
    // $id = (int)$data->id['id'];

    $db = getDb();
    $sql = "UPDATE kruzok SET ";
    if ($data->nazov) {
        $sql = $sql . "nazov = :nazov, ";
    }
    if ($data->veduci) {
        $sql = $sql . "veduci = :veduci, ";
    }
    $sql = substr($sql, strlen($sql) - 2);
    $sql = $sql . " WHERE id = :id";
    $stmt = $db->prepare($sql);
    if ($data->nazov) {
        $stmt->bindParam(':nazov', $data->nazov);
    }
    if ($data->veduci) {
        $stmt->bindParam(':veduci', $data->veduci);
    }
    $stmt->bindParam(':id', $data->id); //, PDO::PARAM_INT);
    $result = $stmt->execute();
    
    $db = null;
    if ($result) {
        return getResponse($result);
    }
    return getErrorResponse($response, ['message' => 'Failed to patch Kruzok'], 500);
});

/* Ucastnici */

$app->get('/api/ucastnici', function (Request $request, Response $response, array $args) {
    $db = getDb();
    $stmt = $db->prepare("SELECT * FROM ucastnik ORDER BY priezvisko, meno, datum_narodenia");
    $stmt->execute();
    $data = $stmt->fetchAll();
    
    $db = null;
    return getResponse($response, $data);
});

$app->get('/api/ucastnik/{id}', function (Request $request, Response $response, array $args) {
    $id = $args['id'];

    $db = getDb();
    $stmt = $db->prepare("SELECT * FROM ucastnik WHERE id=:id");
    $stmt->execute(['id' => $id]);
    $data = $stmt->fetch();
    
    if ($data) {
        $idcka = substr($data['kruzky'], 1, strlen($data['kruzky']) - 2);
        $stmt = $db->prepare("SELECT k.id, k.nazov, p.poplatok, p.stav FROM kruzok k INNER JOIN poplatky p ON (p.ucastnik = :id AND p.kruzok = k.id) WHERE id IN (" . $idcka . ") ORDER BY nazov");
        $stmt->execute(['id' => $id]);
        $kruzky = $stmt->fetchAll();
        $data['kruzky'] = $kruzky;
    }
    
    $db = null;
    return getResponse($response, $data);
});

$app->run();
