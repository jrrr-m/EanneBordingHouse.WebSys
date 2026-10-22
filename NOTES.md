# Eanne Boarding House Rental - System Notes

## Property & Room Inventory Configuration

1. **How many rooms are for rent:**
   - **6 boarding house units in total**

2. **Different Types of rooms:**
   - **4 Boarding House Rentals:** Individual room and dedicated kitchen area, shared comfort room (CR).
   - **2 Private Boarding Houses:** Individual private space/house, no shared common areas (family home).

3. **Rates for each type of room:**
   - **Boarding House Rental:** ₱3,500 per 1 room / month
   - **Private Boarding House:** ₱8,000 for a private boarding house / month

4. **Inclusions & Utilities Policy:**
   - **For Boarding House Rental:** Individual room, kitchen area, shared common area.
   - **For Private Boarding House:** Own private space (entire house).
   - **Utility Bills:** Excluded from the rent. Electricity and water bills are shouldered directly by tenants.

5. **House Rules:**
   - **For Boarding House Rentals:**
     1. 3 pax per room maximum limit
     2. No karaokes past 10:00 PM (Quiet hours)
     3. No pets allowed
     4. Clean As You Go (CLAYGO)
     5. No drilling and renovations without prior permission
   - **For Private Boarding Houses:**
     - No strict boarding house rules (treated as an independent family house).

---

## System Login Credentials & User Accounts

### 1. Landlord (Administrator)
- **Username:** `admin`
- **Password:** `admin12345`
- **Role:** Administrator (Full compound control, verification queue, room inventory, financial ledgers)
- **Destination:** [pages/admin-dashboard.html](pages/admin-dashboard.html)

---

### 2. Complete Tenant Accounts Roster

All tenants log in via [pages/login.html](pages/login.html) and access their billing statement and services at [pages/tenant-dashboard.html](pages/tenant-dashboard.html):

| Full Name | Username | Password | Unit Assigned | Monthly Rent | Contact No. / Email | Lease Status |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **Maria Clara Santos** | `tenant` | `tenant12345` | **BH-02** | ₱3,500.00 | 0917-882-9901<br>`maria.clara@gmail.com` | **Active** (Primary Demo) |
| **Carlos Dominguez** | `carlos` | `carlos12345` | **BH-01** | ₱3,500.00 | 0917-234-5678<br>`carlos.d@gmail.com` | **Active** |
| **Juan Dela Cruz** | `juan` | `juan12345` | **BH-03** | ₱3,500.00 | 0918-345-6789<br>`juan.delacruz@yahoo.com` | **Active** |
| **Elena Garcia** | `elena` | `elena12345` | **BH-04** | ₱3,500.00 | 0919-456-7890<br>`elena.garcia@outlook.com` | **Active** |
| **Eduardo & Grace Reyes** | `eduardo` | `eduardo12345` | **PB-01** | ₱8,000.00 | 0917-889-4455<br>`reyes.family@gmail.com` | **Active** (Private House) |
| **Ricardo Mendoza** | `ricardo` | `ricardo12345` | **PB-02** | ₱8,000.00 | 0917-991-2233<br>`ricardo.mendoza@gmail.com` | **Inactive** (Lease Completed) |

---

### 3. Newly Registered Tenants (Runtime Accounts)
- **Username Pattern:** Auto-generated `[clean_name][2-digit-random]` (e.g., `juandelacruz42`)
- **Default Password:** `welcome12345` (configured in `backend/api_tenants.php`)

---

## 1-Year Operational Dataset & Initialization Reference

The database includes a 1-year historical dataset (October 2025 to October 2026) pre-packaged in the database initializer and SQL schema:

### Dataset Overview:
- **79 Payment Transactions:** 72 verified payments (₱351,000.00 total collections), 3 active pending verification queue submissions, and 4 audit-trailed rejections.
- **13 Maintenance Tickets:** 11 resolved work orders (plumbing, electrical, carpentry), 1 in-progress ticket, and 1 open ticket.
- **Standard Receipt Placeholder:** [`uploads/payments/receipt_placeholder.svg`](uploads/payments/receipt_placeholder.svg) provides consistent electronic receipt vouchers across all digital proof previews.
- **Setup URL:** `http://localhost/EanneBordingHouse.WebSys/database/init_db.php` initializes and populates all tables in one click.
- **Reset Parameter:** Adding `?reset=1` resets and re-seeds the dataset fresh.

---

## Rent Payment Verification & Rejection Policy

When reviewing tenant payment uploads (GCash, Maya, Bank Transfer) in the **Rent Payment Verification Queue**, administrators cannot reject a receipt without providing an explicit, recorded reason.

### Common Rejection Reasons:
1. **Unclear or blurry proof of payment:** Screenshot cannot be read clearly (reference number, timestamp, or amount is obscured).
2. **Reference number mismatch:** The reference number entered by the tenant does not match the reference number in the uploaded screenshot.
3. **Incorrect payment amount:** The amount sent does not match the tenant's current monthly rent balance (₱3,500 for Boarding House / ₱8,000 for Private House).
4. **Duplicate submission:** The transaction receipt was already previously claimed or submitted.
5. **Payment not received in landlord account:** The funds are not reflected in the landlord's registered GCash or bank transaction history.
6. **Wrong recipient account:** Payment was transferred to an unintended or invalid recipient account.
7. **Invalid or altered screenshot:** Receipt image appears cropped, edited, or expired.

### Audit & Visibility:
- Every rejection records an audit entry into `payments.admin_notes` containing the reason, reviewer name, and timestamp.
- The rejection reason is displayed directly on the tenant's dashboard so they know the exact reason and can re-upload valid proof.
