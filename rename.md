# Homesync to Nyumbaflow Rename Plan

## Overview
This document outlines all the UI changes required to rename "Homesync" to "Nyumbaflow" across the application. The focus is exclusively on UI/visual elements in the frontend to maintain all functionality, icons, and responsiveness.

---

## Files to Edit

### 1. auth.html
**Path:** `c:/xampp/htdocs/homesync/auth.html`

| Line | Original | Replace With |
|------|----------|--------------|
| 6 | `<title>Homesync | Portal</title>` | `<title>Nyumbaflow | Portal</title>` |
| 130 | `<h1>Homesync</h1>` | `<h1>Nyumbaflow</h1>` |

---

### 2. home.html
**Path:** `c:/xampp/htdocs/homesync/home.html`

| Line | Original | Replace With |
|------|----------|--------------|
| 7 | `<title>Billing - Homesync</title>` | `<title>Billing - Nyumbaflow</title>` |
| ~1179 | `<h1><i class="fas fa-home"></i> HomeSync</h1>` | `<h1><i class="fas fa-home"></i> Nyumbaflow</h1>` |
| ~1186 | `<p>Property Management System</p>` | `<p>Property Management System</p>` (keep unchanged) |

---

### 3. index.html
**Path:** `c:/xampp/htdocs/homesync/index.html`

| Line | Original | Replace With |
|------|----------|--------------|
| 6 | `<title>Homesync | Dashboard</title>` | `<title>Nyumbaflow | Dashboard</title>` |
| 112 | `<div class="brand__title">Home Sync</div>` | `<div class="brand__title">Nyumba Flow</div>` |
| 117 | `<div class="foot small">© <strong>Home Sync</strong> • Billing & tenant management</div>` | `<div class="foot small">© <strong>Nyumba Flow</strong> • Billing & tenant management</div>` |

---

### 4. index.php
**Path:** `c:/xampp/htdocs/homesync/index.php`

| Line | Original | Replace With |
|------|----------|--------------|
| 123 | `<title>Dashboard - HomeSync</title>` | `<title>Dashboard - Nyumbaflow</title>` |
| ~437 (sidebar header) | `<h1><i class="fas fa-home"></i> HomeSync</h1>` | `<h1><i class="fas fa-home"></i> Nyumbaflow</h1>` |
| ~443 (sidebar subtitle) | `<p>Property Management</p>` | `<p>Property Management</p>` (keep unchanged) |
| ~451 (footer) | `<p>&copy; <?php echo date('Y'); ?> HomeSync. All rights reserved.</p>` | `<p>&copy; <?php echo date('Y'); ?> Nyumbaflow. All rights reserved.</p>` |

---

### 5. sidebar.php
**Path:** `c:/xampp/htdocs/homesync/sidebar.php`

| Line | Original | Replace With |
|------|----------|--------------|
| ~437 (sidebar header) | `<h1><i class="fas fa-home"></i> HomeSync</h1>` | `<h1><i class="fas fa-home"></i> Nyumbaflow</h1>` |
| ~443 (sidebar subtitle) | `<p>Property Management</p>` | `<p>Property Management</p>` (keep unchanged) |
| ~451 (footer) | `<p>&copy; <?php echo date('Y'); ?> HomeSync. All rights reserved.</p>` | `<p>&copy; <?php echo date('Y'); ?> Nyumbaflow. All rights reserved.</p>` |

---

### 6. onboarding.html
**Path:** `c:/xampp/htdocs/homesync/onboarding.html`

| Line | Original | Replace With |
|------|----------|--------------|
| 7 | `<title>Property Setup - HomeSync</title>` | `<title>Property Setup - Nyumbaflow</title>` |
| ~453 | `value="HOMESYNC"` (placeholder for SMS Sender ID) | `value="NYUMBAFLOW"` |

---

### 7. onboarding.php
**Path:** `c:/xampp/htdocs/homesync/onboarding.php`

| Line | Original | Replace With |
|------|----------|--------------|
| ~31 | `$celcom_id = $_POST['celcom_id'] ?? 'HOMESYNC';` | `$celcom_id = $_POST['celcom_id'] ?? 'NYUMBAFLOW';` |

---

### 8. visitors.php
**Path:** `c:/xampp/htdocs/homesync/visitors.php`

| Line | Original | Replace With |
|------|----------|--------------|
| ~313 | `<title>Visitor - Homesync</title>` | `<title>Visitor - Nyumbaflow</title>` |

---

### 9. tenants.php
**Path:** `c:/xampp/htdocs/homesync/tenants.php`

| Line | Original | Replace With |
|------|----------|--------------|
| Check title | `<title>Tenant Management - Homesync</title>` | `<title>Tenant Management - Nyumbaflow</title>` |

---

## Important Notes

### DO NOT CHANGE:
1. **Database references** - Database name `homesync` in config.php, database.sql
2
3. **Git repository URLs** - Git remote URLs in .git/ folder
4. **Server configurations** - Apache config files mentioning homesync
5. **Icons** - All icon files in `icons/` folder remain unchanged
6. **Functionality** - All PHP logic, JavaScript, and backend code remains unchanged

### ONLY UI Changes:
- Page titles (`<title>` tags)
- Sidebar branding (logo text)
- Login page branding
- Onboarding page branding
- Footer copyright text

### Naming Convention:
- Use **Nyumbaflow** (as specified - not NyumbaFlow)
- For SMS Sender ID: **NYUMBAFLOW** (uppercase, as per SMS ID conventions)

---

## Execution Order

1. Edit `auth.html`
2. Edit `home.html`
3. Edit `index.html`
4. Edit `index.php`
5. Edit `sidebar.php`
6. Edit `onboarding.html`
7. Edit `onboarding.php`
8. Edit `visitors.php`
9. Edit `tenants.php`

---

## Verification Checklist

After making changes, verify:
- [ ] All page titles display "Nyumbaflow"
- [ ] Sidebar branding shows "Nyumbaflow"
- [ ] Login page shows "Nyumbaflow"
- [ ] Footer shows "Nyumbaflow" copyright
- [ ] All icons remain in place
- [ ] All functionality works as before
- [ ] Responsive design is unchanged
- [ ] Email addres- `noreply@homesync.com` in config.php (backend config) to `jacetechnologies@gmail.com`
