# DentalPro — v1 Beta
**Stack:** HTML · CSS · JavaScript · jQuery · PHP · MySQL

---

## Quick Setup

### 1. Database
```sql
-- In phpMyAdmin or MySQL CLI:
SOURCE /path/to/dental-app/database/schema.sql;
```

### 2. Configure DB connection
Edit `includes/Database.php` — change the private variables at the top:
```php
private string $host     = 'localhost';
private string $dbname   = 'dental_app';
private string $username = 'root';
private string $password = 'YOUR_PASSWORD';
```

### 3. Configure email (for password reset)
Edit `includes/Auth.php` — find `sendResetEmail()` and change:
```php
$fromEmail = 'noreply@YOURDOMAIN.com';  // must match your hosting domain
```

### 4. Web server
Point your Apache/Nginx document root to `/dental-app/`.
The app assumes it lives at the root `/` — if it's in a subdirectory, edit
`App.config.baseUrl` in `assets/js/app.js`.

### 5. Default login
| Email | Password |
|-------|----------|
| admin@dentalapp.com | Admin@1234 |

> Change this immediately after first login.

---

## Project Structure

```
dental-app/
│
├── assets/
│   ├── css/
│   │   ├── global.css       ← ALL CSS variables (colors, fonts, spacing)
│   │   ├── layout.css       ← Sidebar, topbar, main content shell
│   │   └── auth.css         ← Login / forgot password pages
│   └── js/
│       ├── app.js           ← Core: Ajax, Loader, Toast, Auth, Modal, Utils
│       └── patients.js      ← Module JS (copy for each new module)
│
├── api/
│   ├── auth/
│   │   ├── login.php        ← POST — handles login
│   │   ├── logout.php       ← POST — destroys session
│   │   ├── check.php        ← GET  — session alive check (called on every page load)
│   │   └── forgot-password.php  ← POST — send_code / verify_code / reset_password
│   ├── dashboard/
│   │   ├── stats.php
│   │   └── todays-appointments.php
│   ├── patients/
│   │   ├── list.php         ← GET  — paginated + search
│   │   ├── get.php          ← GET  — single record
│   │   ├── create.php       ← POST
│   │   ├── update.php       ← POST
│   │   └── delete.php       ← POST — soft delete
│   └── stock/
│       └── low-stock.php
│
├── includes/
│   ├── Database.php         ← PDO wrapper: select/insert/update/delete/query/paginate
│   ├── Auth.php             ← Session auth + Api response helper class
│   ├── page-header.php      ← Shared HTML shell top (sidebar + topbar)
│   └── page-footer.php      ← Shared HTML shell bottom (scripts + modals)
│
├── pages/
│   └── patients.php         ← Module page (template to copy for new modules)
│
├── database/
│   └── schema.sql           ← Full DB schema + seed data
│
├── login.php
├── forgot-password.php
└── dashboard.php
```

---

## Retheme in Seconds

All colors, fonts and spacing live as CSS variables in `assets/css/global.css`:

```css
:root {
  --color-primary:      #1A6B72;   /* ← change this one line for a new brand color */
  --color-accent:       #F0A500;
  --font-primary:       'DM Sans', sans-serif;
  --sidebar-width:      260px;
  /* ... */
}
```

---

## How to Build a New Module

1. **Create the page:**
   Copy `pages/patients.php` → `pages/your-module.php`
   Change `$pageTitle`, `$activePage`, `$breadcrumbs` at the top.

2. **Create the JS:**
   Copy `assets/js/patients.js` → `assets/js/your-module.js`
   Replace all occurrences of `patient` with your module name.

3. **Create the API endpoints:**
   Copy the `api/patients/` folder → `api/your-module/`
   Update table names and field names.

4. **Add to nav:**
   Open `includes/page-header.php` and add your page to `$navSections`.

---

## AJAX — How It Works

**Every single AJAX call** goes through `App.ajax()` in `app.js`:

```javascript
App.ajax({
  url:       'api/patients/list.php',  // relative or absolute
  method:    'POST',                   // default: GET
  data:      { name: 'Ali' },         // plain object or FormData
  loader:    true,                     // show full-screen overlay (default: true)
  loaderMsg: 'Loading…',              // custom overlay message
  silent:    false,                    // skip auto error toast
  btn:       $('#my-btn'),            // button gets inline spinner while loading
  onSuccess: function(data, msg, res) { /* data = res.data from PHP */ },
  onError:   function(errMsg, xhr)    { /* called on failure */ },
  onComplete:function()               { /* always called */ }
});
```

**PHP side** always responds with:
```json
{ "success": true,  "data": {},  "message": "Done" }
{ "success": false, "data": null, "message": "Error message" }
```

---

## Auth Flow

```
Page load
  └─ $(document).ready → App.auth.check()
       └─ GET api/auth/check.php
            ├─ 200 + success:true  → session OK, page loads
            └─ 200 + success:false → App.auth.handleUnauthorized()
                 └─ toast + redirect to login.php
```

---

## Forgot Password Flow

```
Step 1: User enters email
  └─ POST api/auth/forgot-password.php { action: 'send_code', email }
       └─ DB: insert token  →  PHP mail() sends 6-digit code (5 min expiry)

Step 2: User enters 6-digit code
  └─ POST api/auth/forgot-password.php { action: 'verify_code', email, code }
       └─ DB: checks hash + expiry  →  saves reset_verified_email in session

Step 3: User sets new password
  └─ POST api/auth/forgot-password.php { action: 'reset_password', new_password, confirm_password }
       └─ DB: updates users.password (bcrypt)  →  deletes token  →  clears session flag
```
