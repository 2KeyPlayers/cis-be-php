<?php
// use Psr\Container\ContainerInterface as Container;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Exception\HttpNotFoundException;
// use Slim\Extras\Middleware\HttpBasicAuth;
use Tuupola\Middleware\HttpBasicAuthentication as HttpBasicAuthentication;
use Tuupola\Middleware\HttpBasicAuthentication\PdoAuthenticator as PdoAuthenticator;
// use Slim\Middleware\HttpBasicAuthentication as HttpBasicAuthentication;
// use Slim\Middleware\HttpBasicAuthentication\PdoAuthenticator as PdoAuthenticator;

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

$app->options('/{routes:.+}', function ($request, $response, $args) {
    return $response;
});

$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Powered-By, X-Requested-With, Content-Type, Accept, Origin, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
});

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
        // remove heslo
        unset($data['heslo']);
        // add token
        $token = base64_encode($prezyvka . ":" . $heslo);
        $data['token'] = $token;
        return getResponse($response, $data);
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
    return getErrorResponse($response, ['message' => 'Failed to post Veduci'], 400);
});

/* Veduci */

$app->get('/api/veduci', function (Request $request, Response $response, array $args) {
    $db = getDb();
    $stmt = $db->prepare("SELECT id, meno, priezvisko, titul FROM uzivatel WHERE veduci = TRUE ORDER BY id");
    $stmt->execute();
    $data = $stmt->fetchAll();
    
    $db = null;
    return getResponse($response, $data);
});

$app->get('/api/veduci/{id}', function (Request $request, Response $response, array $args) {
    $id = $args['id'];

    $db = getDb();
    $stmt = $db->prepare("SELECT * FROM uzivatel WHERE id = :id AND veduci = TRUE");
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
    $stmt = $db->prepare("SELECT k.*, v.id idveduceho, v.meno menoVeduceho, v.priezvisko priezviskoVeduceho, v.titul titulveduceho, COUNT(u.*) pocetUcastnikov FROM kruzok k INNER JOIN uzivatel v ON k.veduci = v.id LEFT JOIN ucastnik u ON k.id = ANY (u.kruzky) GROUP BY k.id, v.id, v.meno, v.priezvisko, v.titul ORDER BY nazov");
    $stmt->execute();
    $data = $stmt->fetchAll();
    
    $db = null;
    return getResponse($response, $data);
});

$app->get('/api/kruzok/{id}', function (Request $request, Response $response, array $args) {
    $id = $args['id'];

    $db = getDb();
    $stmt = $db->prepare("SELECT * FROM kruzok WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $data = $stmt->fetch();
    
    if ($data) {
        $stmt = $db->prepare("SELECT u.id, u.pohlavie, u.meno, u.priezvisko, u.platby, p.poplatok, p.dochadzka FROM ucastnik u INNER JOIN prihlaska p ON (p.ucastnik = u.id AND p.kruzok = :id) WHERE :id = ANY(u.kruzky) ORDER BY u.priezvisko, u.meno");
        $stmt->execute(['id' => $id]);
        $ucastnici = $stmt->fetchAll();
        $data['ucastnici'] = $ucastnici;

        $db = null;
        return getResponse($response, $data);
    } else {
        $db = null;
        return getErrorResponse($response, ['message' => 'Kruzok not found'], 400);
    }
});

$app->post('/api/kruzok/skontroluj', function (Request $request, Response $response, array $args) {
    $data = getJson($request);

    $db = getDb();
    if ($data->id) {
        $stmt = $db->prepare("SELECT * FROM kruzok WHERE id != :id AND nazov = :nazov");
        $stmt->execute(['id' => $data->id, 'nazov' => $data->nazov]);
    } else {
        $stmt = $db->prepare("SELECT * FROM kruzok WHERE nazov = :nazov");
        $stmt->execute(['nazov' => $data->nazov]);
    }
    $data = $stmt->fetch();
    
    $db = null;
    if ($data) {
        return getResponse($response, ['nazovExistuje' => true]);
    }    
    return getResponse($response, ['nazovExistuje' => false]);
});

$app->post('/api/kruzok', function (Request $request, Response $response, array $args) {
    $data = getJson($request);

    $db = getDb();
    $stmt = $db->prepare("INSERT INTO kruzok (nazov, veduci, zadarmo, vytvoreny, uzivatel) VALUES (:nazov, :veduci, :zadarmo, CURRENT_DATE, :uzivatel)");
    $result = $stmt->execute(['nazov' => $data->nazov, 'veduci' => $data->veduci, 'zadarmo' => ($data->zadarmo ? 't' : 'f'), 'uzivatel' => $data->uzivatel]);
    
    $db = null;
    if ($result) {
        return getResponse($response);
    }
    return getErrorResponse($response, ['message' => 'Failed to post Kruzok'], 400);
});

$app->patch('/api/kruzok/{id}', function (Request $request, Response $response, array $args) {
    $id = $args['id'];
    $data = getJson($request);
    // $id = (int)$data->id['id'];

    $db = getDb();
    $sql = "UPDATE kruzok SET nazov = :nazov, veduci = :veduci, zadarmo = :zadarmo, upraveny = CURRENT_DATE, uzivatel = :uzivatel WHERE id = :id";
    $stmt = $db->prepare($sql);
    $result = $stmt->execute(['nazov' => $data->nazov, 'veduci' => $data->veduci, 'zadarmo' => ($data->zadarmo ? 't' : 'f'), 'uzivatel' => $data->uzivatel, 'id' => $id]);
    
    $db = null;
    if ($result) {
        return getResponse($response);
    }
    return getErrorResponse($response, ['message' => 'Failed to patch Kruzok'], 400);
});

$app->delete('/api/kruzok/{id}', function (Request $request, Response $response, array $args) {
    $id = $args['id'];

    $db = getDb();
    $sql = "DELETE FROM kruzok k WHERE k.id = :id AND NOT EXISTS (SELECT * FROM ucastnik u WHERE k.id = any (u.kruzky))";
    $stmt = $db->prepare($sql);
    $result = $stmt->execute(['id' => $id]);
    
    $db = null;
    if ($result) {
        return getResponse($response);
    }
    return getErrorResponse($response, ['message' => 'Failed to delete Kruzok'], 400);
});

/* Ucastnici */

$app->get('/api/ucastnici', function (Request $request, Response $response, array $args) {
    $db = getDb();
    $stmt = $db->prepare("SELECT *, (adresa).* FROM ucastnik ORDER BY cislo_rozhodnutia");
    $stmt->execute();
    $data = $stmt->fetchAll();
    
    if ($data) {
        // in order to be able to directly modify array elements within the loop precede value with &,
        // in that case the value will be assigned by reference
        foreach ($data as &$row) {
            unset($row['adresa']);
            $row['kruzky'] = postgresToPhpArray($row['kruzky']);
        }
    }

    $db = null;
    return getResponse($response, $data);
});

$app->get('/api/ucastnik/cislo', function (Request $request, Response $response, array $args) {
    $db = getDb();
    $stmt = $db->prepare("SELECT MAX(cislo_rozhodnutia) + 1 AS cislo FROM ucastnik");
    $stmt->execute();
    $data = $stmt->fetch();

    $db = null;
    return getResponse($response, $data);
});

$app->get('/api/ucastnik/{id}', function (Request $request, Response $response, array $args) {
    $id = $args['id'];

    $db = getDb();
    $stmt = $db->prepare("SELECT *, (adresa).* FROM ucastnik WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $data = $stmt->fetch();
    
    if ($data) {
        unset($data['adresa']);

        if ($data['kruzky'] && $data['kruzky'] != '{}') {
            $idcka = substr($data['kruzky'], 1, strlen($data['kruzky']) - 2);
            $stmt = $db->prepare("SELECT k.id, k.nazov, p.poplatok, p.dochadzka FROM kruzok k INNER JOIN prihlaska p ON (p.ucastnik = :id AND p.kruzok = k.id) WHERE id IN (" . $idcka . ") ORDER BY nazov");
            $stmt->execute(['id' => $id]);
            $kruzky = $stmt->fetchAll();
            $data['kruzky'] = $kruzky;
        }

        $db = null;
        return getResponse($response, $data);
    } else {
        $db = null;
        return getErrorResponse($response, ['message' => 'Ucastnik not found'], 400);
    }
});

$app->post('/api/ucastnik/skontroluj', function (Request $request, Response $response, array $args) {
    $data = getJson($request);

    $db = getDb();
    if ($data->id) {
        $stmt = $db->prepare("SELECT * FROM ucastnik WHERE id != :id AND cislo_rozhodnutia = :cislo");
        $stmt->execute(['id' => $data->id, 'cislo' => $data->cislo]);
        $result = $stmt->fetch();
        if ($result) {
            $db = null;
            return getResponse($response, ['cisloExistuje' => true]);
        }
        $stmt = $db->prepare("SELECT * FROM ucastnik WHERE id != :id AND meno = :meno AND priezvisko = :priezvisko");
        $stmt->execute(['id' => $data->id, 'meno' => $data->meno, 'priezvisko' => $data->priezvisko]);
        $result = $stmt->fetch();
        if ($result) {
            $db = null;
            return getResponse($response, ['menoExistuje' => true]);
        }
    } else {
        $stmt = $db->prepare("SELECT * FROM ucastnik WHERE cislo_rozhodnutia = :cislo");
        $stmt->execute(['cislo' => $data->cislo]);
        $result = $stmt->fetch();
        if ($result) {
            $db = null;
            return getResponse($response, ['cisloExistuje' => true]);
        }
        $stmt = $db->prepare("SELECT * FROM ucastnik WHERE meno = :meno AND priezvisko = :priezvisko");
        $stmt->execute(['meno' => $data->meno, 'priezvisko' => $data->priezvisko]);
        $result = $stmt->fetch();
        if ($result) {
            $db = null;
            return getResponse($response, ['menoExistuje' => true]);
        }
    }

    return getResponse($response, ['cisloExistuje' => false, 'menoExistuje' => false]);
});

$app->post('/api/ucastnik', function (Request $request, Response $response, array $args) {
    $data = getJson($request);

    $db = getDb();
    $stmt = $db->prepare("INSERT INTO ucastnik (cislo_rozhodnutia, pohlavie, meno, priezvisko, datum_narodenia, adresa.ulica, adresa.cislo, adresa.mesto, adresa.psc, vytvoreny, uzivatel) VALUES (:cislo_rozhodnutia, :pohlavie, :meno, :priezvisko, to_date(:datum, 'YYYY-MM-DD'), :ulica, :cislo, :mesto, :psc, CURRENT_DATE, :uzivatel)");
    $result = $stmt->execute(['cislo_rozhodnutia' => $data->cisloRozhodnutia, 'pohlavie' => $data->pohlavie, 'meno' => $data->meno, 'priezvisko' => $data->priezvisko, 'datum' => $data->datumNarodenia, 'ulica' => $data->adresa->ulica, 'cislo' => $data->adresa->cislo, 'mesto' => $data->adresa->mesto, 'psc' => $data->adresa->psc, 'uzivatel' => $data->uzivatel]);
    
    $db = null;
    if ($result) {
        return getResponse($response);
    }
    return getErrorResponse($response, ['message' => 'Failed to post Ucastnik'], 400);
});

$app->patch('/api/ucastnik/{id}', function (Request $request, Response $response, array $args) {
    $id = $args['id'];
    $data = getJson($request);
    // $id = (int)$data->id['id'];

    $db = getDb();
    $sql = "UPDATE ucastnik SET cislo_rozhodnutia = :cislo_rozhodnutia, pohlavie = :pohlavie, meno = :meno, priezvisko = :priezvisko, datum_narodenia = to_date(:datum, 'YYYY-MM-DD'), adresa.ulica = :ulica, adresa.cislo = :cislo, adresa.mesto = :mesto, adresa.psc = :psc, upraveny = CURRENT_DATE, uzivatel = :uzivatel WHERE id = :id";
    $stmt = $db->prepare($sql);
    $result = $stmt->execute(['cislo_rozhodnutia' => $data->cisloRozhodnutia, 'pohlavie' => $data->pohlavie, 'meno' => $data->meno, 'priezvisko' => $data->priezvisko, 'datum' => $data->datumNarodenia, 'ulica' => $data->adresa->ulica, 'cislo' => $data->adresa->cislo, 'mesto' => $data->adresa->mesto, 'psc' => $data->adresa->psc, 'uzivatel' => $data->uzivatel, 'id' => $id]);
    
    $db = null;
    if ($result) {
        return getResponse($response);
    }
    return getErrorResponse($response, ['message' => 'Failed to patch Ucastnik'], 400);
});

$app->delete('/api/ucastnik/{id}', function (Request $request, Response $response, array $args) {
    $id = $args['id'];

    $db = getDb();
    $sql = "DELETE FROM ucastnik u WHERE u.id = :id AND (u.kruzky IS NULL OR u.kruzky = '{}')";
    $stmt = $db->prepare($sql);
    $result = $stmt->execute(['id' => $id]);
    
    $db = null;
    if ($result) {
        return getResponse($response);
    }
    return getErrorResponse($response, ['message' => 'Failed to delete Ucastnik'], 400);
});

$app->map(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], '/{routes:.+}', function ($request, $response) {
    throw new HttpNotFoundException($request);
});

$app->run();
