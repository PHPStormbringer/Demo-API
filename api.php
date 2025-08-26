<?php
// Allow CORS & JSON response
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");


// Load DB config and connect (make sure config.php defines $dbHost, $dbName, $dbUser, $dbPass)
require 'config.php';

// Always return JSON

try {

	/*
	$dbName="company";
	$dbHost="localhost";
	$dbUser="root";
	$dbPass="";
	*/

    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "DB connection failed"]);
    exit;
}



// Parse request method and path
$method = $_SERVER['REQUEST_METHOD'];
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$base = $_SERVER['SCRIPT_NAME'];
$path = trim(str_replace($base, '', $requestUri), '/');

// Explode path into segments
$segments = explode('/', $path);
$resource = $segments[0] ?? null;
$id       = $segments[1] ?? null;

// Route request
switch ($resource) {
    case 'employees':
        handleEmployees($method, $id, $pdo);
        break;

	case 'manager':
		if ($id) {
			getEmployeesByManager($pdo, $id);
		} else {
			respond(400, "Manager ID required");
		}
		break;


    default:
        http_response_code(404);
        echo json_encode(["error" => "Resource not found"]);
        exit;
}

// --- Handlers ---

function handleEmployees($method, $id, $pdo) {
    switch ($method) {
        case 'GET':
            $id ? getEmployee($pdo, $id) : getEmployees($pdo);
            break;

        case 'POST':
            createEmployee($pdo);
            break;

        case 'PUT':
            $id ? updateEmployee($pdo, $id) : respond(400, "Employee ID required for update");
            break;

        case 'DELETE':
            $id ? deleteEmployee($pdo, $id) : respond(400, "Employee ID required for delete");
            break;

        default:
            respond(405, "Method not allowed");
    }
}

// --- Utility response function ---
function respond($status, $message) {
    http_response_code($status);
    echo json_encode(["error" => $message]);
    exit;
}

// --- CRUD Functions ---

function getEmployees($pdo) {
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : null;

    if ($limit && $limit > 0) {
        $stmt = $pdo->prepare("SELECT employee_id, name, email FROM employees LIMIT ?");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
    } else {
        $stmt = $pdo->query("SELECT employee_id, name, email FROM employees");
    }

    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($employees);
    exit;
}

function getEmployee($pdo, $id) {
    $stmt = $pdo->prepare("SELECT employee_id, name, email FROM employees WHERE employee_id = ?");
    $stmt->execute([$id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($employee) {
        echo json_encode($employee);
    } else {
        respond(404, "Employee not found");
    }
    exit;
}

function getEmployeesByManager($pdo, $manager_id) {
    // Prepare query to select employees under the given manager
    $stmt = $pdo->prepare("SELECT employee_id, name, email FROM employees WHERE manager_id = ?");
    $stmt->execute([$manager_id]);

    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($employees) {
        echo json_encode($employees);
    } else {
        // Return an empty array if no employees found
        echo json_encode([]);
    }
    exit;
}


function createEmployee($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['name'], $input['email'])) {
        respond(400, "Missing name or email");
    }

    // Get next sequential employee_id
    $stmt = $pdo->query("SELECT MAX(employee_id) AS max_id FROM employees");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $nextId = $row['max_id'] + 1;

    $stmt = $pdo->prepare("INSERT INTO employees (employee_id, name, email) VALUES (?, ?, ?)");
    $stmt->execute([$nextId, $input['name'], $input['email']]);

    http_response_code(201);
    echo json_encode([
        "message" => "Employee created",
        "employee_id" => $nextId
    ]);
    exit;
}


function updateEmployee($pdo, $id) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        respond(400, "Invalid input");
    }

    $fields = [];
    $values = [];

    if (isset($input['name'])) {
        $fields[] = 'name = ?';
        $values[] = $input['name'];
    }
    if (isset($input['email'])) {
        $fields[] = 'email = ?';
        $values[] = $input['email'];
    }

    if (empty($fields)) {
        respond(400, "Nothing to update");
    }

    $values[] = $id;

    $sql = "UPDATE employees SET " . implode(', ', $fields) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);

    echo json_encode(["message" => "Employee updated"]);
    exit;
}

function deleteEmployee($pdo, $id) {
    $stmt = $pdo->prepare("DELETE FROM employees WHERE id = ?");
    $stmt->execute([$id]);
    echo json_encode(["message" => "Employee deleted"]);
    exit;
}
