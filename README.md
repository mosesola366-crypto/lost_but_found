# Property Reporting and Recovery System
Security Unit, The Polytechnic Ibadan

A web-based system for reporting, searching, and matching lost and found
property, built to replace the manual, paper-based process described in
Chapter Three of this project.

## Technology Stack
- PHP 8 (procedural, PDO for database access)
- MySQL / MariaDB
- HTML5, CSS3, JavaScript

## How to Run This on Your Computer (XAMPP)

1. **Install XAMPP** (if you don't already have it): https://www.apachefriends.org
2. **Copy this folder** (`prs_system`) into your XAMPP `htdocs` directory, e.g.
   `C:\xampp\htdocs\prs_system`
3. **Start Apache and MySQL** from the XAMPP Control Panel.
4. **Create the database:**
   - Open `http://localhost/phpmyadmin`
   - Click "Import", choose the file `sql/schema.sql`, and click "Go".
   - This creates the `prs_system` database and all required tables.
5. **Open the system:** go to `http://localhost/prs_system/` in your browser.
6. The first time any page loads, the system automatically creates a
   default administrator account (see below). You do not need to insert
   this manually.

## Default Login Credentials

**Administrator**
- Email: `admin@polyibadan.edu.ng`
- Password: `admin123`

**Regular users** register their own accounts from the "Register" page.

> Note: the admin password is intentionally simple for demonstration/
> testing purposes during your project defence. For real deployment,
> change it immediately after first login (or update the seed logic in
> `includes/db.php`) to a stronger password.

## Folder Structure

```
prs_system/
├── index.php              Landing page
├── register.php           User registration
├── login.php               Login (users + admin)
├── logout.php
├── dashboard.php           User dashboard
├── report_lost.php         Report a lost item
├── report_found.php        Report a found item
├── search.php               Search lost/found reports
├── admin/
│   ├── dashboard.php        Admin overview
│   ├── manage_lost.php       Manage lost item reports, propose matches
│   ├── manage_found.php      Manage found item reports
│   ├── matches.php            Confirm/reject/release matches
│   └── users.php               Manage registered users
├── includes/
│   ├── db.php                Database connection + admin auto-seed
│   ├── auth.php               Session/authentication helpers
│   ├── header.php             Shared navigation header
│   └── footer.php             Shared footer
├── assets/
│   └── css/style.css         Stylesheet
├── uploads/                   Found-item photo uploads
└── sql/schema.sql             Database schema
```

## Core Modules

| Module | Description |
|---|---|
| Registration & Login | Users self-register; admin has a fixed seeded account. Passwords are hashed with PHP's `password_hash()`. |
| Report Lost Item | Users submit lost item details: name, category, description, location, date, contact. |
| Report Found Item | Users submit found item details, with optional photo upload. |
| Search | Search/filter lost or found reports by keyword and category. |
| Admin Manage Reports | Admin views all lost/found reports and links a lost report to a found report as a potential match. |
| Admin Matches | Admin confirms or rejects proposed matches, and marks confirmed matches as "released" once the item is physically returned. |
| Admin Users | Admin views registered users and can suspend/reactivate accounts. |
| Notifications | Users are notified in-app when a match involving their report is confirmed. |
