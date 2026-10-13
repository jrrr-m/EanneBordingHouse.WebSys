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

## Demo Login Credentials

### 1. Landlord (Admin)
- **Username:** `admin`
- **Password:** `admin12345`
- **Destination:** [pages/admin-dashboard.html](pages/admin-dashboard.html)

---

### 2. Tenant (Maria Clara Santos)
- **Username:** `tenant`
- **Password:** `tenant12345`
- **Destination:** [pages/tenant-dashboard.html](pages/tenant-dashboard.html)

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
