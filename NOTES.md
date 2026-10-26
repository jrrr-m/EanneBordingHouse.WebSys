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

### 2. Complete Tenant Accounts Roster (Diverse Demonstration Personas)

All tenants log in via [pages/login.php](pages/login.php) and access their billing statement and services at [pages/tenant-dashboard.php](pages/tenant-dashboard.php):

| Full Name | Username | Password | Unit Assigned | Rent | Current Balance | Overdue Status / Rule | Demonstration Role |
| :--- | :--- | :--- | :---: | :---: | :---: | :--- | :--- |
| **Juan Dela Cruz** | `juan` | `juan12345` | **BH-03** | ₱3,500.00 | **₱10,500.00** | **3 Mos Overdue (Eviction Risk)** | Demonstrates the **Eviction Risk** alert banner, admin eviction button, and the **mandatory 50% minimum payment rule (≥₱5,250)**. |
| **Maria Clara Santos** | `tenant` | `tenant12345` | **BH-02** | ₱3,500.00 | **₱14,000.00** | **4 Mos Overdue w/ 1-Mo Grace Exemption** | Demonstrates the **Admin Exemption** feature (payroll delay). Balance carried over to month 4 where full 50% settlement (₱7,000) applies across the accumulated total. Historical relocation from `BH-01`. |
| **Carlos Dominguez** | `carlos` | `carlos12345` | **BH-01** | ₱3,500.00 | **-₱1,500.00** | **Advance Credit** | Demonstrates advance credit badge (`-₱1,500.00`), zero balance safeguards, and advance credit application to future rent. |
| **Elena Garcia** | `elena` | `elena12345` | **BH-04** | ₱3,500.00 | **₱3,500.00** | **Normal 1-Month Due** | Standard single-month rental statement, quick payment upload, and digital receipt verification. |
| **Eduardo & Grace Reyes** | `eduardo` | `eduardo12345` | **PB-01** | ₱8,000.00 | **₱0.00** | **Fully Paid (Zero Balance)** | Demonstrates Private Boarding House standalone rental (₱8,000/mo) with full payment clearance. |
| **Ricardo Mendoza** | `ricardo` | `ricardo12345` | *PB-02* | ₱8,000.00 | **₱0.00** | **Inactive / Moved Out** | Demonstrates tenant archiving, past unit code resolution (`PB-02`), and room inventory release. |

---

### 3. Newly Registered Tenants (Runtime Accounts)
- **Username Pattern:** Auto-generated `[clean_name][2-digit-random]` (e.g., `juandelacruz42`)
- **Default Password:** `welcome12345` (configured in `backend/api_tenants.php`)

---

## User Manual: Overdue Rent, 50% Rule & Administrative Exemption

### A. The 3-Month Non-Payment & Eviction Rule
1. **Calculation**: The system continuously monitors each tenant's accumulated balance:
   $$\text{Overdue Months} = \left\lfloor \frac{\text{Current Balance}}{\text{Monthly Rent}} \right\rfloor$$
2. **Eviction Risk Threshold**:
   - If effective overdue months reach **3 or more**, the tenant is flagged as **`EVICTION RISK`**.
   - A red eviction risk badge appears on the Admin Tenants Roster and Admin Dashboard.
   - The tenant portal displays an urgent red **Notice of Eviction / Removal Risk** alert.
3. **Mandatory 50% Minimum Payment**:
   - To prevent eviction, the tenant **MUST pay at least 50% of the entire outstanding balance**.
   - Any payment submission below 50% is blocked both in the portal UI and validated on the backend API.
   - Example: On a ₱3,500/month room, 3 months unpaid = ₱10,500 balance $\rightarrow$ the tenant must pay at least **₱5,250.00**.

### B. Admin Exemption / Deferral Mechanism
1. **Granting an Exemption**:
   - In [`pages/tenants.php`](pages/tenants.php), the administrator clicks the blue **Shield Icon (`Exempt`)** on any tenant row.
   - Select the duration (e.g. 1 Month, 2 Months) and enter an administrative reason (e.g., *"Salary delayed due to payroll transition; agreed to settle next month"*).
   - Enter the Admin Password to confirm.
2. **Accumulation & Carry-Over Behavior**:
   - A 1-month exemption grants a temporary grace period for the current billing cycle, suppressing immediate eviction.
   - **Crucial Rule:** The unpaid balance is **not waived**; it carries over and continues to accumulate.
   - On the following month (e.g., 4th month), the tenant now owes 4 months' rent (e.g., ₱14,000.00). Under the policy, the mandatory 50% minimum payment will now apply to the **full 4-month total** (e.g., ₱7,000.00 required).

### C. Admin Eviction Execution
- If an overdue tenant does not pay the required 50% and has no active exemption, the administrator can click the red **`Evict`** button in [`pages/tenants.php`](pages/tenants.php).
- The action requires Admin Password verification, safely frees up the rental unit back to available inventory, changes the tenant's lease status to `inactive`, and records an audit log entry in `tenant_unit_transfers`.

---

## 1-Year Operational Dataset & Initialization Reference

The database includes a comprehensive operational dataset pre-packaged in the database initializer and SQL schema:

### Dataset Overview:
- **79 Payment Transactions:** 72 verified payments (₱351,000.00 total collections), 3 active pending verification queue submissions, and 4 audit-trailed rejections.
- **13 Maintenance Tickets:** 11 resolved work orders (plumbing, electrical, carpentry), 1 in-progress ticket, and 1 open ticket.
- **4 Viewing Appointments:** Prospective residents scheduling in-person room inspections across confirmed, pending, and completed states.
- **2 Unit Transfer Audits:** Audit trail recording unit relocations and move-out turnover records.
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
