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
   - Create a database named `prs_system` first, then select it.
   - Click "Import", choose the file `sql/schema.sql`, and click "Go".
   - This creates the required tables and optional sample records.
5. **Open the system:** go to `http://localhost/prs_system/` in your browser.
6. If you import the supplied schema, use the administrator account below.
   On a completely empty database, the first application request creates it.
   Change the password immediately after first login.

The distributable package does not include `includes/config.php` because it
contains private database credentials. Copy `includes/config.example.php` to
`includes/config.php` and enter the credentials for the new installation.

## Deploying to InfinityFree

1. Register at `https://infinityfree.net` and create a free hosting account.
2. Create a website/subdomain and open the control panel.
3. Create a MySQL database in the InfinityFree control panel. Note the
   database host, database name, username, and password.
4. In your project, copy `includes/config.example.php` to `includes/config.php`.
5. Edit `includes/config.php` and set the InfinityFree database values.
6. Upload the entire project folder to InfinityFree using either:
   - the InfinityFree File Manager, or
   - FTP via FileZilla / WinSCP.
   Make sure you upload all files and folders, including `includes/`, `admin/`,
   `assets/`, `sql/`, and `uploads/`.
7. In InfinityFree phpMyAdmin, import `sql/schema.sql` into the new database.
8. If `uploads/` does not exist on the server, create it manually and set
   permissions to `755`.
9. Open your site URL and confirm it loads.

### Uploading with the InfinityFree File Manager

- Open the File Manager in your InfinityFree control panel.
- Upload the project files into the `htdocs/` folder for your website.
- If there is already a default `index.html`, delete it before uploading.
- Create the `uploads/` folder if needed.

### Uploading with FTP

- Use your InfinityFree FTP host and credentials.
- Connect to the `htdocs/` folder.
- Upload everything from the local `prs_system` folder.
- Do not upload the local XAMPP `htdocs` parent folder; upload the project
  contents directly into `htdocs/`.

Once done, visit your site URL and log in with the default admin credentials.
For security, change default admin passwords and remove demo records before production deployment.

## Folder Structure

```
prs_system/
├── index.php                 Landing page with campus recovery stats
├── register.php              User registration with password complexity & ID validation
├── login.php                 Authentication with CSRF & session fixation protection
├── logout.php                Secure session invalidation & cookie removal
├── dashboard.php             Student/staff portal with report search & claims tracking
├── claim_item.php            Direct property claim submission with proof of ownership
├── report_lost.php           Report a lost item
├── report_found.php          Report a found item with hardened photo upload
├── search.php                Search lost/found reports with pagination & claim CTA
├── notifications.php         Notification Center with read/unread status management
├── profile.php               Account settings & secure password change
├── install.php               Interactive database migration & initialization utility
├── admin/
│   ├── dashboard.php         Operational overview, claims count & quick actions
│   ├── manage_lost.php       Manage lost item reports & link matches with pagination
│   ├── manage_found.php      Manage found item reports with status filters
│   ├── matches.php           Verify matches, trigger bilateral alerts & generate vouchers
│   ├── manage_claims.php     Review student claims & inspect ownership proof
│   ├── voucher.php           Printable official Property Handover Voucher
│   ├── users.php             Manage registered users, search & toggle status
│   └── audit_logs.php        Security Unit Audit Trail viewer
├── includes/
│   ├── db.php                Pure PDO database connection
│   ├── config.php            Database credentials configuration
│   ├── config.example.php    Template credentials file
│   ├── auth.php              Session, role & suspension watchdog helpers
│   ├── csrf.php              CSRF token generation & validation utilities
│   ├── helpers.php           Global constants, audit logging & alert dispatchers
│   ├── pagination.php        Universal database query pagination engine
│   ├── header.php            Responsive navigation bar with mobile hamburger drawer
│   └── footer.php            Institutional footer
├── assets/
│   └── css/style.css         Modern institutional stylesheet (Plus Jakarta Sans)
├── uploads/                  Hardened found-item photo uploads directory
└── sql/schema.sql            Enterprise database schema with indexes & constraints
```

## Core Modules & Enterprise Features

| Module | Description |
|---|---|
| Security & Authentication | CSRF token protection on all forms, strict cookie flags (`httponly`, `samesite=Lax`), session fixation prevention, and real-time suspended account lockout. |
| Student Claim Flow | Students spotting their lost property can click "Claim This Item" and submit private distinguishing marks (serial numbers, concealed marks, lock codes) for Security Unit verification. |
| Handover Voucher | Official, printable A4 **Property Handover Certificate** issued upon release, recording claimant details, releasing officer credentials, and physical dual signatures. |
| Security Audit Trail | Immutable log recording administrative actions (`SUSPEND_USER`, `CONFIRM_MATCH`, `RELEASE_ITEM`, `APPROVE_CLAIM`) with timestamps and IP addresses for campus accountability. |
| Report Lost & Found | Strict validation, future date guards, and defense-in-depth file upload inspection (`finfo` MIME check, `getimagesize`, 3MB cap, randomized filename). |
| Universal Pagination | Reusable pagination engine across search and all administrative tables preserving query filters. |
| Notification Center | In-app alerts informing users of match confirmations, claim decisions, and property releases, with unread badge tracking and mark-as-read controls. |
| Profile & Security | User profile management and secure self-service password updating. |
