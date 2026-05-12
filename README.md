# NexusERP — Setup Guide

## Requirements

- PHP 7.4+ (8.x recommended)
- MySQL 5.7+ / MariaDB 10.3+
- Apache with mod_rewrite enabled
- Composer (optional)

## Installation

### 1. Copy files to your web server

Place the entire `erp/` folder inside your web root:

```
/var/www/html/erp/          ← Linux/Apache
C:/xampp/htdocs/erp/        ← XAMPP (Windows)
/Applications/MAMP/htdocs/erp/ ← MAMP (macOS)
```

### 2. Create the database

Open phpMyAdmin or MySQL CLI and run:

```sql
SOURCE /path/to/erp/config/install.sql;
```

Or import the file via phpMyAdmin → Import.

### 3. Configure database connection

Edit `config/database.php`:

```php
define('DB_HOST', 'localhost');   // your DB host
define('DB_USER', 'root');        // your DB username
define('DB_PASS', '');            // your DB password
define('DB_NAME', 'erp_system');  // keep as-is
```

### 4. Access the system

Open your browser:

```
http://localhost/erp/login.php
```

### Default Login

- **Email:** [admin@erp.com](mailto:admin@erp.com)
- **Password:** password

> Change the password after first login!

---

## Modules Included


| Module                 | Path                                      | Features                        |
| ---------------------- | ----------------------------------------- | ------------------------------- |
| Dashboard              | `index.php`                               | KPIs, charts, recent activity   |
| Finance → Accounts     | `modules/finance/accounts.php`            | Chart of accounts               |
| Finance → Invoices     | `modules/finance/invoices.php`            | Create, send, mark paid         |
| Finance → Transactions | `modules/finance/transactions.php`        | GL entries, debit/credit        |
| HR → Employees         | `modules/hr/employees.php`                | Employee records                |
| HR → Departments       | `modules/hr/departments.php`              | Department management           |
| HR → Attendance        | `modules/hr/attendance.php`               | Daily attendance tracking       |
| HR → Payroll           | `modules/hr/payroll.php`                  | Auto-generate & process payroll |
| Inventory → Products   | `modules/inventory/products.php`          | Product catalogue               |
| Inventory → Stock      | `modules/inventory/stock.php`             | Stock levels, movements         |
| Sales → Customers      | `modules/sales/customers.php`             | CRM — customer records          |
| Sales → Orders         | `modules/sales/orders.php`                | Sales order management          |
| Purchasing → Suppliers | `modules/purchasing/suppliers.php`        | Vendor management               |
| Purchasing → POs       | `modules/purchasing/purchase_orders.php`  | Purchase order tracking         |
| Manufacturing          | `modules/manufacturing/manufacturing.php` | Work orders, production log     |
| Projects               | `modules/projects/projects.php`           | Project management              |
| Tasks                  | `modules/projects/tasks.php`              | Kanban task board               |
| Assets                 | `modules/assets/assets.php`               | Asset registry & depreciation   |
| Reports                | `modules/reports/reports.php`             | Business intelligence           |


---

## Technology Stack

- **Backend:** PHP 8.x (OOP + procedural)
- **Database:** MySQL with prepared statements
- **Frontend:** Vanilla HTML5 / CSS3 / JavaScript
- **Icons:** Font Awesome 6
- **Fonts:** Syne + DM Sans (Google Fonts)
- **Design:** Dark industrial-luxury aesthetic

## Security Notes

- All inputs sanitized with `htmlspecialchars` + `strip_tags`
- SQL queries use prepared statements (PDO/MySQLi)
- Session-based authentication
- Sensitive files blocked via `.htaccess`
- Passwords hashed with `password_hash()` (bcrypt)

## Customization

- Colors: Edit CSS variables in `assets/css/main.css` (`:root` block)
- Logo: Replace `.brand-icon` content in `includes/header.php`
- Currency: Search and replace `$` symbol across modules
- Company name: Search for `NexusERP` in header/login files

