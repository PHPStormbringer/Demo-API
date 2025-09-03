<?php
// Allow CORS & JSON response
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Load DB config
require 'config.php';

// --- DB Connection ---
try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "DB connection failed"]);
    exit;
}

// --- Authenticate User ---
$user = authenticate($pdo);

// --- Parse Request ---
$method     = $_SERVER['REQUEST_METHOD'];
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$base = $_SERVER['SCRIPT_NAME'];
$path = trim(str_replace($base, '', $requestUri), '/');
$segments = explode('/', $path);

$resource = $segments[0] ?? null;
$id       = isset($segments[1]) ? (int)$segments[1] : null;

// --- Route Requests ---
switch ($resource) {
    case 'employees':
        handleEmployees($method, $id, $pdo, $user);
        break;

    case 'manager':
        if ($id) {
            if ($user['role'] === 'admin' || $user['role'] === 'manager') {
                getEmployeesByManager($pdo, $id);
            } else {
                respond(403, "Forbidden: managers or admins only");
            }
        } else {
            respond(400, "Manager ID required");
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(["error" => "Resource not found"]);
        exit;
}

// ======================================================
// FUNCTIONS
// ======================================================

// --- Authentication ---
function authenticate($pdo) {
    $headers = getallheaders();
    if (!isset($headers['Authorization'])) {
        respond(401, "Missing Authorization header");
    }

    if (!preg_match('/Bearer\s+(\S+)/', $headers['Authorization'], $matches)) {
        respond(401, "Invalid Authorization format");
    }

    $apiKey = $matches[1];

    $stmt = $pdo->prepare("SELECT api_key, role, owner_name FROM api_keys WHERE api_key = ?");
    $stmt->execute([$apiKey]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        respond(403, "Invalid API key");
    }

    return $user; // ['api_key' => ..., 'role' => ..., 'owner_name' => ...]
}

// --- Handlers ---
function handleEmployees($method, $id, $pdo, $user) {
    switch ($method) {
        case 'GET':
            $id ? getEmployee($pdo, $id) : getEmployees($pdo);
            break;

        case 'POST':
            if ($user['role'] !== 'admin') {
                respond(403, "Forbidden: only admin can create employees");
            }
            createEmployee($pdo);
            break;

        case 'PUT':
            if ($user['role'] === 'employee') {
                respond(403, "Forbidden: employees cannot update");
            }
            if ($user['role'] === 'manager') {
                if (!managerOwnsEmployee($pdo, $user['owner_name'], $id)) {
                    respond(403, "Forbidden: not your employee");
                }
            }
            $id ? updateEmployee($pdo, $id) : respond(400, "Employee ID required for update");
            break;

        case 'DELETE':
            if ($user['role'] !== 'admin') {
                respond(403, "Forbidden: only admin can delete");
            }
            $id ? deleteEmployee($pdo, $id) : respond(400, "Employee ID required for delete");
            break;

        default:
            respond(405, "Method not allowed");
    }
}

// --- Response Helper ---
function respond($status, $message) {
    http_response_code($status);
    echo json_encode(["error" => $message]);
    exit;
}

// --- Manager-ownership check ---
function managerOwnsEmployee($pdo, $managerName, $employeeId) {
    $stmt = $pdo->prepare("SELECT 1 FROM employees WHERE employee_id = ? AND manager_id = ?");
    $stmt->execute([$employeeId, $managerName]);
    return (bool)$stmt->fetchColumn();
}

// ======================================================
// CRUD
// ======================================================

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
    $stmt = $pdo->prepare("SELECT employee_id, name, email FROM employees WHERE manager_id = ?");
    $stmt->execute([$manager_id]);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($employees ?: []);
    exit;
}

function createEmployee($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['name'], $input['email'], $input['manager_id'])) {
        respond(400, "Missing name, email, or manager_id");
    }

    $stmt = $pdo->query("SELECT MAX(employee_id) AS max_id FROM employees");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $nextId = (int)$row['max_id'] + 1;

    $stmt = $pdo->prepare("INSERT INTO employees (employee_id, name, email, manager_id) VALUES (?, ?, ?, ?)");
    $stmt->execute([$nextId, $input['name'], $input['email'], $input['manager_id']]);

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
    if (isset($input['manager_id'])) {
        $fields[] = 'manager_id = ?';
        $values[] = $input['manager_id'];
    }

    if (empty($fields)) {
        respond(400, "Nothing to update");
    }

    $values[] = $id;
    $sql = "UPDATE employees SET " . implode(', ', $fields) . " WHERE employee_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);

    echo json_encode(["message" => "Employee updated"]);
    exit;
}

function deleteEmployee($pdo, $id) {
    $stmt = $pdo->prepare("DELETE FROM employees WHERE employee_id = ?");
    $stmt->execute([$id]);
    echo json_encode(["message" => "Employee deleted"]);
    exit;
}
