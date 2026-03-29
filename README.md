# IT Inventory System
**Version 1.0 — MIS Department**

A clean, web-based IT asset management system built with PHP, MySQL, HTML, CSS, and JavaScript. All form operations (add, edit, delete) run via AJAX — no full page reloads.

---

## Requirements

| Software | Version |
|----------|---------|
| PHP      | 8.0+    |
| MySQL    | 5.7+ or MariaDB 10.3+ |
| Web Server | Apache or Nginx (XAMPP / WAMP / Laragon) |

---

## File Structure

```
it-inventory/
│
├── setup.php              ← Run first — creates DB tables + admin account
├── login.php              ← Login page
├── logout.php             ← Destroys session, redirects to login
├── index.php              ← Redirects to dashboard
│
├── dashboard.php          ← Module 5: KPIs, charts, alerts
├── assets.php             ← Module 1: Asset Registry
├── assignment.php         ← Module 2: Device Assignment
├── location.php           ← Module 3: Location Directory
├── stock.php              ← Module 4: Stock / Consumables
├── users.php              ← Module 6: User Roles (Admin only)
├── profile.php            ← Edit own name, email, password
│
├── api.php                ← Central AJAX handler (all writes go here)
│
├── css/
│   └── style.css          ← Shared light theme design system
│
├── js/
│   └── app.js             ← Shared JS: Toast, Modal, Table search,
│                              AjaxForm engine (submit/delete/action)
│
└── includes/
    ├── auth.php           ← Session guard — redirects to login if not authed
    ├── db.php             ← PDO connection + helpers (auto-written by setup.php)
    ├── layout.php         ← Sidebar, topbar, nav (included by every page)
    └── layout_end.php     ← Footer + script tags
```

---

## Installation

### Step 1 — Place files
Copy the `it-inventory/` folder into your web server root:
- **XAMPP** → `C:\xampp\htdocs\it-inventory\`
- **WAMP**  → `C:\wamp64\www\it-inventory\`
- **Linux** → `/var/www/html/it-inventory/`

### Step 2 — Run Setup
```
http://localhost/it-inventory/setup.php
```
Fill in your MySQL credentials and choose an admin username/password. Click **Run Setup**. This will create all tables and your admin account automatically.

### Step 3 — Log in
```
http://localhost/it-inventory/login.php
```

### Step 4 — Delete setup.php
Remove `setup.php` after installation is complete.

---

## Modules

| Module         | File            | Access     | Description                                |
|----------------|-----------------|------------|--------------------------------------------|
| Dashboard      | dashboard.php   | All roles  | KPI cards, charts, warranty + stock alerts |
| Asset Registry | assets.php      | All roles  | Register and manage IT devices             |
| Assignment     | assignment.php  | All roles  | Assign devices to people/departments       |
| Locations      | location.php    | All roles  | Buildings, floors, rooms, departments      |
| Stock          | stock.php       | All roles  | Consumables with restock/issue log         |
| User Roles     | users.php       | Admin only | Create and manage user accounts            |
| Profile        | profile.php     | All roles  | Edit own name, email, password             |

---

## User Roles

| Role     | Assets | Assignment | Locations | Stock | Dashboard | User Roles |
|----------|--------|------------|-----------|-------|-----------|------------|
| Admin    | Full   | Full       | Full      | Full  | Full      | Full       |
| IT Staff | Full   | Full       | Full      | Full  | Full      | Hidden     |
| Viewer   | View   | View       | View      | View  | Full      | Hidden     |

---

## How AJAX Works

All add/edit/delete actions post to `api.php` via `fetch()`. The page never reloads:

1. User fills the modal form and clicks Save
2. `AjaxForm.submit()` sends the data to `api.php`
3. `api.php` validates, writes to DB, returns the saved row as JSON
4. JavaScript injects the new/updated row directly into the table
5. Stat counters update instantly from the DOM
6. A toast notification confirms the action
7. The modal closes — you stay on the same page

---

## Department Dropdown in Assignment

The Department field in Assignment is a **dropdown populated from the Locations table**. Add your departments first via **Locations → Add Location**, then they will appear automatically in the Assignment form. This ensures consistent department names across all records.

---

## Production Notes

- Delete `setup.php` after installation
- Use HTTPS in a live environment
- Back up the database regularly
- All passwords are bcrypt hashed — never stored in plain text
- Users cannot delete or disable their own account

---

## Multi-User Local Network Setup

For 3+ users on the same office network:

### On the XAMPP server PC
1. Open Command Prompt → type `ipconfig` → note the **IPv4 Address** (e.g. `192.168.1.10`)
2. In XAMPP, make sure **Apache** and **MySQL** are running
3. In XAMPP Apache config (`httpd.conf`), find `Require local` and change to `Require all granted`
4. Restart Apache

### On other PCs (clients)
Open browser and go to:
```
http://192.168.1.10/it-inventory/
```
Replace `192.168.1.10` with your server PC's actual IP.

### Live sync between users
The system polls every **5 seconds**. When User A adds an asset, User B sees it within 5 seconds — no page refresh needed. The polling automatically pauses when you are:
- Filling in a form or modal
- Typing in any input field
- On a different browser tab

This prevents any disruption to work in progress.

