# Eanne Boarding House Rental Management System

A web-based Boarding House and Rental Property Management System customized for **Eanne Boarding House**, a residential rental compound located in Soldiers Village, Sta. Lucia, Pasig City, Philippines. Built with a responsive frontend, PHP backend, and MySQL database, supporting real-time payment verifications, tenant statements, room inventory, and maintenance requests.

---

## Project Structure & GitHub File Navigation

Below is a map of the repository files, their roles, and linked scripts:

```text
EanneBordingHouse.WebSys/
├── index.html                   # Public landing page with room inventory highlights & inquiries
├── pages/                       # User Interface Pages (Admin & Tenant)
│   ├── login.html               # Role-based authentication portal (Admin / Tenant)
│   ├── admin-dashboard.html     # Landlord executive console (KPIs, queue, occupancy overview)
│   ├── rooms.html               # File Maintenance: Room inventory (Add, Edit specs, Delete unit, occupancy)
│   ├── tenants.html             # User Maintenance: Tenant accounts (Register/Add, Edit profile, Archive, Delete)
│   ├── payments.html            # Verification queue for uploaded digital receipts & cash logging
│   ├── tickets.html             # Caretaker & maintenance tickets queue (Work orders, status updates)
│   ├── reports.html             # Financial operating ledgers, collection efficiency & CSV export
│   ├── tenant-dashboard.html    # Tenant portal (Monthly statement, proof upload, tickets)
│   ├── accommodations.html      # Public room catalog (Boarding House vs Private House specs)
│   ├── house-rules.html         # Property regulations, quiet hours, and occupancy policies
│   ├── about.html               # Compound history, landlord profile, and property amenities
│   ├── contact.html             # Map directions, viewing appointment scheduler, and inquiries
│   └── gallery.html             # Interactive lightbox photo tour of compound and rooms
│
├── assets/                      # Styling, JavaScript & Media Assets
│   ├── css/
│   │   └── style.css            # Custom CSS design system (Navy/cyan palette, cards, badges)
│   ├── js/
│   │   └── script.js            # Frontend logic, live database synchronizer & modal handlers
│   └── img/                     # Property photography, exterior compound & room mockups
│
├── backend/                     # Backend PHP REST Endpoints (PDO MySQL)
│   ├── db.php                   # Centralized PDO MySQL connection singleton
│   ├── api_auth.php             # Session & credentials authentication against `users` table
│   ├── api_get_stats.php        # Live compound KPI metrics, revenue targets, and recent activity
│   ├── api_rooms.php            # File Maintenance CRUD: Add, Edit specifications, Delete room, status toggles
│   ├── api_tenants.php          # User Maintenance CRUD: Add tenant, Edit details, Move-out archive, Hard delete
│   ├── api_get_payments.php     # Filtered payment transactions, revenue aggregates & rejection reason extraction
│   ├── api_update_payment.php   # Verification endpoint: 'Verified & Cleared' approval or structured rejection with audit notes
│   ├── api_upload_payment.php   # Receipt upload handler (saves images to uploads/payments/)
│   ├── api_record_cash.php      # Walk-in OTC cash payment logger
│   ├── api_tickets.php          # Maintenance ticket submission and status tracking
│   └── api_tenant_profile.php   # Dynamic tenant billing statement & payment clearance status
│
├── database/                    # Database Initializer & SQL Scripts
│   ├── schema.sql               # Complete MySQL database schema & 1-year operational dataset (79 payments, 13 tickets)
│   └── init_db.php              # Automated one-click database table creation & migration tool (supports ?reset=1)
│
├── uploads/                     # File Storage Directory
│   └── payments/                # Uploaded tenant GCash/Maya/Bank screenshots & standard receipt placeholder (receipt_placeholder.svg)
│
├── NOTES.md                     # Business rules, property specs, and developer reference
└── README.md                    # Project documentation, architecture & installation guide
```

---

## Page Directory & Feature Breakdown

| Page | File | Roles | Core Features |
| :--- | :--- | :--- | :--- |
| **Login Portal** | [`pages/login.html`](pages/login.html) | All | Role-based authentication against MySQL (`admin` / `tenant`) with error shake animations. |
| **Admin Dashboard** | [`pages/admin-dashboard.html`](pages/admin-dashboard.html) | Admin | Live KPI cards (Active Tenants, Monthly Target, Queue, Vacancy), Category Occupancy overview, and Recent Activity feed. |
| **Rooms & Houses** | [`pages/rooms.html`](pages/rooms.html) | Admin | **File Maintenance (CRUD):** Add rental units, Edit unit specifications (rate, type, capacity, inclusions), Delete units permanently, and quick vacancy status toggling. |
| **Tenants Roster** | [`pages/tenants.html`](pages/tenants.html) | Admin | **User Maintenance (CRUD):** Register new tenant accounts, Edit resident profile & rent, Mark move-out (lease archiving), and Permanently Delete accounts. |
| **Payments Queue** | [`pages/payments.html`](pages/payments.html) | Admin | Pending receipt review, digital receipt preview with uploaded photo proof, instant **Approve ("Verified & Cleared")**, structured **Reject with Reason Prompt** (preset dropdown, quick tags, custom notes & tenant feedback), and Walk-in Cash logging. |
| **Reports & Ledgers** | [`pages/reports.html`](pages/reports.html) | Admin | Monthly revenue ledger, collection efficiency breakdowns, policy summaries, and dynamic CSV report export. |
| **Tenant Portal** | [`pages/tenant-dashboard.html`](pages/tenant-dashboard.html) | Tenant | Live billing statement, proof of payment upload form, payment history ledger, and maintenance ticket tracker. |
| **Public Landing** | [`index.html`](index.html) | Public | Hero showcase, unit rates teaser, inclusions summary, utility notice, and viewing inquiry form. |
| **Accommodations** | [`pages/accommodations.html`](pages/accommodations.html) | Public | Side-by-side comparison between 4 Boarding House units and 2 Private Houses with detailed specifications. |
| **House Rules** | [`pages/house-rules.html`](pages/house-rules.html) | Public / Tenant | Detailed compound rules: 3-pax limit, 10 PM quiet curfew (no karaoke), CLAYGO, and utility exclusion guidelines. |
| **Photo Gallery** | [`pages/gallery.html`](pages/gallery.html) | Public | Filterable photography grid with full-screen lightbox modal for compound and room views. |
| **Contact & Viewings** | [`pages/contact.html`](pages/contact.html) | Public | Location map, transport routes, Soldiers Village landmark guide, and viewing booking form. |

---

## 📋 System Requirements & Maintenance Functions (Part 4 Compliance)

This system meets all server-side script and maintenance requirements:

| Requirement Component | Implementation Web Page | Backend Script | Supported Functions |
| :--- | :--- | :--- | :--- |
| **Server-Side Scripting** | All Application Pages | [`backend/*.php`](backend/) | Uses PHP PDO with prepared statements connecting to MySQL (`eanne_boarding_house_db`). |
| **Manage Website Records** | Master Ledgers & Grids | [`backend/db.php`](backend/db.php) | Centralized MySQL persistence for rooms, user accounts, payments, and work tickets. |
| **Working Log-in Page** | [`pages/login.html`](pages/login.html) | [`backend/api_auth.php`](backend/api_auth.php) | Secure session authentication verifying credentials, roles, and automated dashboard redirection. |
| **User Maintenance** *(Customer / Tenant Accounts)* | [`pages/tenants.html`](pages/tenants.html) | [`backend/api_tenants.php`](backend/api_tenants.php) | • **Add:** Register new tenant with unit assignment & default password.<br>• **Edit:** Modify full legal name, contact number, email, unit assignment, rent, move-in date, and lease status via modal.<br>• **Delete:** Hard delete tenant account with automated room status freeing and safe payment unlinking. |
| **File Maintenance** *(Room Inventory Maintenance)* | [`pages/rooms.html`](pages/rooms.html) | [`backend/api_rooms.php`](backend/api_rooms.php) | • **Add:** Create new rental unit (Unit code, rate, type, capacity, inclusions).<br>• **Edit:** Modify unit specifications, category (₱3,500 vs ₱8,000), inclusions, and occupancy status via modal.<br>• **Delete:** Permanently remove rental unit from rooms inventory table with tenant unlinking. |

---

## Installation & Setup (XAMPP)

Follow these steps to set up and run the system on a new computer or local development server:

### 1. Prerequisites
- **XAMPP** (Apache + MySQL + PHP 7.4 or PHP 8.x) installed on your system.
- Web browser (Google Chrome, Microsoft Edge, Mozilla Firefox, or Brave).

### 2. Clone or Copy Repository
Copy the project folder into your XAMPP `htdocs` web root directory:
```bash
c:\xampp\htdocs\EanneBordingHouse.WebSys\
```

### 3. Start Apache & MySQL
1. Launch the **XAMPP Control Panel**.
2. Click **Start** for both **Apache** and **MySQL**.
3. Ensure both modules are highlighted in green.

### 4. Setup the Database (Choose Option A or Option B)

#### Option A: Automatic Setup (Fastest)
Open your web browser and navigate to:
```text
http://localhost/EanneBordingHouse.WebSys/database/init_db.php
```
*This script will automatically create the `eanne_boarding_house_db` database, generate all tables (`rooms`, `users`, `payments`, `maintenance_tickets`), and populate the complete 1-year operational dataset (79 payments, 13 maintenance tickets, 6 rental units, and full tenant roster).*

> **Tip:** To re-initialize or reset the database fresh at any time, navigate to:
> `http://localhost/EanneBordingHouse.WebSys/database/init_db.php?reset=1`

#### Option B: Manual Setup via phpMyAdmin
1. Open your browser and go to [http://localhost/phpmyadmin](http://localhost/phpmyadmin).
2. Click **New** in the left sidebar to create a database.
3. Set the database name to: `eanne_boarding_house_db` and collation to: `utf8mb4_unicode_ci` (or `utf8mb4_general_ci`).
4. Click **Create**.
5. Select the newly created `eanne_boarding_house_db` database and click the **Import** tab at the top.
6. Click **Choose File** and select:
   ```text
   c:\xampp\htdocs\EanneBordingHouse.WebSys\database\schema.sql
   ```
7. Click **Import** (or **Go**) at the bottom of the page to execute the SQL script.

### 5. Access the Application
Open your web browser and visit:
```text
http://localhost/EanneBordingHouse.WebSys/
```

To sign in directly to the management console:
```text
http://localhost/EanneBordingHouse.WebSys/pages/login.html
```

---

## Default Credentials

The system provides ready-to-use accounts for immediate access and testing:

### 1. Landlord / Property Admin
- **Username:** `admin`
- **Password:** `admin12345`
- **Role:** Administrator (Full compound control, verification queue, room inventory, financial ledgers)
- **Destination:** [`pages/admin-dashboard.html`](pages/admin-dashboard.html)

### 2. Active Tenant (Maria Clara Santos)
- **Username:** `tenant`
- **Password:** `tenant12345`
- **Role:** Tenant (Unit BH-02 Resident)
- **Destination:** [`pages/tenant-dashboard.html`](pages/tenant-dashboard.html)

*Additional active tenants: Carlos Dominguez (`carlos` / `carlos12345`), Juan Dela Cruz (`juan` / `juan12345`), Elena Garcia (`elena` / `elena12345`), and Eduardo & Grace Reyes (`eduardo` / `eduardo12345`). Historical lease completed: Ricardo Mendoza (`ricardo` / `ricardo12345`).*

---

## 🏢 Business & Property Information

* **Business Name:** Eanne Boarding House Rental
* **Property Location:** #275 105 St., Soldiers Village, Brgy. Sta. Lucia, Pasig City, Metro Manila
* **Total Inventory:** **6 Units in Total**
  1. **4 Boarding House Rentals:**
     - Rate: **₱3,500.00 / month per room**
     - Layout: Individual private room, dedicated kitchen area, shared comfort room (CR) and common areas.
     - Capacity: **Strict maximum 3 pax per room limit**.
  2. **2 Private Boarding Houses:**
     - Rate: **₱8,000.00 / month (entire standalone house)**
     - Layout: Complete standalone private space, private kitchen, private CR, no shared areas.
     - Capacity: Ideal for families or residents seeking independent living.
* **Utility Billing Policy:**
  - **Bills are Excluded:** Electricity (Meralco sub-metered) and water (Manila Water) are shouldered directly by tenants according to actual consumption.
* **Core House Rules:**
  - Strict quiet hours after **10:00 PM** (No loud music or karaokes).
  - Clean As You Go (CLAYGO) in shared premises.
  - No pets allowed on boarding house premises.
  - No drilling or architectural renovations without prior written approval.

---

## Database Architecture & Real-Time Sync

The system uses AJAX polling and unified helper functions in [`assets/js/script.js`](assets/js/script.js) to keep all pages synchronized with MySQL without requiring page reloads:

* **Tables Schema:**
  - `rooms`: Unit identifier (`BH-01`..`BH-04`, `PB-01`, `PB-02`), classification, monthly rate, capacity, inclusions, and status (`available`, `occupied`, `maintenance`).
  - `users`: User profiles with role differentiation (`admin`, `tenant`), unit assignments, contract move-in dates, and active/inactive lease statuses.
  - `payments`: Digital reference numbers, amounts, payment channels (GCash, Maya, BPI, Cash), proof image file paths, timestamps, verification statuses (`pending`, `verified`, `rejected`), and auditor logs.
  - `maintenance_tickets`: Tenant maintenance issues (`ticket_no`, category, urgency, description, status: `open`, `in_progress`, `resolved`).

* **Live Queue & Maintenance Badges:**
  - `window.syncPendingQueueBadges()` periodically monitors [`backend/api_get_payments.php`](backend/api_get_payments.php) every 10 seconds. When an administrator approves a receipt, the transaction immediately transitions to **"Verified & Cleared"**, the queue count decrements across all sidebars and filter tabs, and turns green when the queue reaches zero.
  - `window.syncTicketsQueueBadges()` monitors [`backend/api_tickets.php`](backend/api_tickets.php) every 10 seconds. The admin sidebar shows live red notification badges for open maintenance tickets and enables one-click status transitions (`In Progress`, `Resolved`, `Reopen`).

* **Live Public Website Vacancy & Room Availability:**
  - `window.syncPublicAvailability()` calls [`backend/api_get_stats.php`](backend/api_get_stats.php) on public page loads (`index.html`, `pages/accommodations.html`).
  - Automatically displays remaining total available units left in the compound (e.g. `1 Unit Available Left`).
  - Dynamically updates room type status badges:
    - **Boarding House (4 Units)**: Shows whether units are available or dynamically flags **"Fully Booked (0/4 Available)"** with a waitlist notice.
    - **Private Boarding House (2 Units)**: Shows vacancy counts (e.g. **"1 of 2 Available"**) with move-in ready indicators.

* **Payment Verification & Rejection Audit Workflow:**
  - **Rejection Modal & Standard Presets:** When rejecting an unverified digital receipt on [`pages/payments.html`](pages/payments.html), [`pages/admin-dashboard.html`](pages/admin-dashboard.html), or within the receipt preview lightbox, the administrator is prompted via a modal with:
    - Standard presets: Unclear/blurry screenshot, Reference number mismatch, Incorrect payment amount, Duplicate submission, Payment not received in landlord account, Wrong recipient account used, or Altered screenshot.
    - 1-Click quick tags: *Blurry receipt*, *Ref # mismatch*, *Incorrect amount*, *Not received*.
    - Free-form explanation `<textarea>` with real-time auto-fill, `novalidate` browser protection, and automatic preset fallback.
  - **Backend Persistence & Audit Trail:**
    - [`backend/api_update_payment.php`](backend/api_update_payment.php) processes the rejection, formatting the entry as:
      `Reason: {reason} (Rejected by {reviewer_name} on {M j, Y g:i A})`.
    - Stored directly into the `admin_notes` column in the MySQL `payments` table.
  - **API Exposure & Tenant Portal Feedback:**
    - [`backend/api_get_payments.php`](backend/api_get_payments.php) automatically extracts and exposes the `rejection_reason` string in JSON payloads for rejected transactions.
    - [`assets/js/script.js`](assets/js/script.js) displays a prominent rejection badge and formatted reason box on both Admin ledgers and the Tenant Portal ([`pages/tenant-dashboard.html`](pages/tenant-dashboard.html)), ensuring complete transparency so the resident knows why the payment was declined and can submit corrected proof without delay.
    - The digital receipt lightbox preview modal also highlights a dedicated red rejection banner with the full recorded reason when reviewing previously rejected receipts.

* **1-Year Operational Dataset & Automated Seeding:**
  - **Comprehensive Transaction History (79 Payments):** Spanning October 2025 to October 2026, containing 72 verified rent collections totaling **₱351,000.00**, 3 active pending verification queue submissions ready for review, and 4 audit-trailed rejections.
  - **Standard Electronic Receipt Voucher ([`uploads/payments/receipt_placeholder.svg`](uploads/payments/receipt_placeholder.svg)):** Unified high-fidelity receipt visual featuring compound branding, address, utility notice, transaction details, and audit barcode used across all digital payment proof previews.
  - **Compound Maintenance Work Orders (13 Tickets):** 11 resolved historical maintenance records across plumbing, electrical, and carpentry, plus 2 active tickets (1 open, 1 in-progress).
  - **Lease History & Vacancy Turnover:** Includes full tenant profiles with realistic lease turnover (such as Ricardo Mendoza completing his lease in June 2026, leaving Unit PB-02 available).
  - **One-Click Setup & Migration:** Executable in any browser at `http://localhost/EanneBordingHouse.WebSys/database/init_db.php` or via phpMyAdmin import of [`database/schema.sql`](database/schema.sql).

