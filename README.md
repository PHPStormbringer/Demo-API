
# Employee API

A simple RESTful API for managing employees, built with PHP and PDO. Supports CRUD operations and fetching employees by manager.

---

## Table of Contents

- [Getting Started](#getting-started)  
- [API Endpoints](#api-endpoints)  
- [Request Examples](#request-examples)  
- [Error Handling](#error-handling)  
- [Notes](#notes)  

---

## Getting Started

1. Clone or copy the repository into your local `htdocs` folder (XAMPP):
   ```
   C:\xampp\htdocs\api_sample
   ```

2. Configure the database in `config.php`:
   ```php
   <?php
   $dbHost = 'localhost';
   $dbName = 'your_database_name';
   $dbUser = 'your_db_user';
   $dbPass = 'your_db_password';
   ```

3. Ensure your `employees` table has at least:
   ```sql
   CREATE TABLE employees (
       employee_id INT PRIMARY KEY,
       name VARCHAR(100) NOT NULL,
       email VARCHAR(100) NOT NULL,
       manager_id INT
   );
   ```

4. Start XAMPP Apache and ensure PHP is running.  
5. Access the API via:
   ```
   http://localhost/api_sample/api.php
   ```

---

## API Endpoints

| Method | Endpoint                     | Description |
|--------|------------------------------|-------------|
| GET    | `/employees`                 | Get all employees |
| GET    | `/employees?limit=N`         | Get up to N employees |
| GET    | `/employees/{id}`            | Get a single employee by `employee_id` |
| POST   | `/employees`                 | Create a new employee (JSON body) |
| PUT    | `/employees/{id}`            | Update an existing employee (JSON body) |
| DELETE | `/employees/{id}`            | Delete an employee |
| GET    | `/manager/{manager_id}`      | Get all employees under a specific manager |

---

## Request Examples

### PowerShell Examples

#### Get All Employees
```powershell
(Invoke-WebRequest -Uri "http://localhost/api_sample/api.php/employees" -Method GET).Content
```

#### Get Employees with Limit
```powershell
(Invoke-WebRequest -Uri "http://localhost/api_sample/api.php/employees?limit=5" -Method GET).Content
```

#### Get Employee by ID
```powershell
(Invoke-WebRequest -Uri "http://localhost/api_sample/api.php/employees/1" -Method GET).Content
```

#### Create Employee
```powershell
$body = '{"name":"Alice Example","email":"alice@example.com"}'
(Invoke-WebRequest -Uri "http://localhost/api_sample/api.php/employees" -Method POST -Body $body -ContentType "application/json").Content
```

#### Update Employee
```powershell
$body = '{"name":"Alice Updated","email":"alice.updated@example.com"}'
(Invoke-WebRequest -Uri "http://localhost/api_sample/api.php/employees/1" -Method PUT -Body $body -ContentType "application/json").Content
```

#### Delete Employee
```powershell
(Invoke-WebRequest -Uri "http://localhost/api_sample/api.php/employees/1" -Method DELETE).Content
```

#### Get Employees by Manager
```powershell
(Invoke-WebRequest -Uri "http://localhost/api_sample/api.php/manager/123" -Method GET).Content
```

### curl Examples (Linux/macOS/Windows)

#### Get All Employees
```bash
curl -X GET http://localhost/api_sample/api.php/employees
```

#### Get Employees with Limit
```bash
curl -X GET "http://localhost/api_sample/api.php/employees?limit=5"
```

#### Get Employee by ID
```bash
curl -X GET http://localhost/api_sample/api.php/employees/1
```

#### Create Employee
```bash
curl -X POST http://localhost/api_sample/api.php/employees      -H "Content-Type: application/json"      -d '{"name":"Alice Example","email":"alice@example.com"}'
```

#### Update Employee
```bash
curl -X PUT http://localhost/api_sample/api.php/employees/1      -H "Content-Type: application/json"      -d '{"name":"Alice Updated","email":"alice.updated@example.com"}'
```

#### Delete Employee
```bash
curl -X DELETE http://localhost/api_sample/api.php/employees/1
```

#### Get Employees by Manager
```bash
curl -X GET http://localhost/api_sample/api.php/manager/123
```

---

## Error Handling

All errors return JSON with HTTP status codes:

```json
{
  "error": "Employee not found"
}
```

- 400 – Bad request  
- 404 – Resource not found  
- 405 – Method not allowed  
- 500 – Server/database error  

---

## Notes

- All responses are JSON.  
- `employee_id` is automatically sequential.  
- `manager_id` must exist if assigning employees to a manager.  
- Test via PowerShell, curl, or JavaScript fetch.  

---

**Author:** Victor Venning  
**License:** MIT
