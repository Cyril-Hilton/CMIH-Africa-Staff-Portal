# SYSTEM GUIDE AND UPDATES

This document is our central feedback log, system guide, and master progress tracker. Whenever we implement or modify features, we will check them off here.

---

## 📋 Master Task Board

### ⏳ To Do (Core Tasks & Enhancements)

#### 1. System-Wide Structural & Core Engine Upgrades
*   **[/] Unified Task, Update & Collective Dashboard (Mega Table):**
        *   [x] Merge `tasks` and `updates` database schemas/tables.
        *   [x] Build the Personal Individual Dashboard (own tasks assigned/created).
        *   [x] Build the Collective Dashboard (The Mega Table) displaying 6 departmental tables:
            *   *Client Relations, Operations/Projects, Brands & Marketing, Creatives, HR & Admin, Finance.*
        *   [x] Implement the master columns: `S/N`, `Client`, `Lead Staff`, `Project/Campaign Name`, `Supporting Staff / Contributors` (with staff selector to add contributors), `Role of Supporting Staff`, `Project Deliverables`, `Deadline`, `Delivery Status`, `Priority`, `Notes / Feedback`.
        *   [x] Apply Delivery Status color coding: `Open` (Gray), `In Progress` (Blue), `Awaiting Approval` (Purple), `Awaiting Feedback` (Orange), `Sent` (Light Blue), `Approved` (Light Green), `Completed` (Deep Green), `Rejected` (Red), `Paid` (Gold), `Overdue` (Bright Red), `Cancelled` (Dark Gray).
        *   [x] Apply Priority color coding: `High` (Red), `Medium` (Orange), `Low` (Green).
        *   [ ] Add Dynamic Customization for Admins/Managers to add/delete/insert/edit columns/rows.
        *   [ ] Implement Task Reassignment option for manager users.
        *   [x] Refactor sidebar navigation tasks section into a parent-child dropdown:
            *   Parent: **TASKS**
            *   Children: **MY TASKS**, **PENDING TASKS**, **CREATE TASK**
        *   [x] Add **PAYROLL** management module and link it inside the navigation bar.
        *   [x] Integrate Rich Text Editors (Quill.js / CKEditor):
            *   Utilize for large text areas (Chat messages, Creative Briefs, Task Details, Announcements, Strategy Blueprints) to allow formatting.
            *   Retain standard text input controls for smaller text fields.
*   **[x] System-Wide Analytics, Charts & Gamification:**
        *   [x] Create Core Metrics & KPI Blocks for individual (Task Rate, Open items, Punctuality, Overtime) and collective (Targets Reached, Project Win-Rate, Critical Bottlenecks) dashboards.
        *   [x] Integrate frontend charting engine (Chart.js / ApexCharts) displaying *Task Performance Velocity* (stacked bar chart), *Punctuality Radar* (attendance/late/overtime mapping), and *Weekly Completion Trends* (timeline).
        *   [x] Build Departmental Performance Badge (Weekly rolling calculation of top performing department automatically awarding the Golden Badge 🥇).
        *   [x] Build Performance Awards Leaderboard: Display month/year Winner, 1st runner up, and 2nd runner up for Employees and Departments, lock awards, and send CSS-rendered winner certificates.
*   **[x] Upgraded Messenger Framework:**
        *   [x] Add Dynamic Chat Initiation dropdown listing all active staff to start a direct chat.
        *   [x] Setup automatic API polling refresh checking for new messages every 60 seconds.
        *   [x] Fix sidebar layout displaying *Recent Chats* and *Groups* lists.
        *   [x] Add Chat Media Gallery tab in chats compiling a download grid of photos, videos, and files.
*   **[x] Advanced User Management, Privilege Elevation & DOB Validation:**
    *   [x] Implement privilege manager panel (Explicitly assign admin roles or checklist matrices to staff).
    *   [x] Setup actions column: Suspend (session revocation), Edit (matrix permissions), Renew (extend contract), and Delete (soft-delete).
    *   [x] Enforce Date of Birth validation constraint: **Must be $\ge$ 18 and $\le$ 65 years old**.
    *   [x] Update frontend registration notice: "Alphanumeric Passwords Only" hint (passwords must be alphanumeric, min 8 characters).
    *   [x] Make email address editable in user profile update form.
*   **[x] Attendance Geolocation & Daily Task Gate:**
    *   [x] Build Clock-In / Clock-Out widget on user dashboard.
    *   [x] Force users to add/have a task for today before Clock-In activates.
    *   [x] Enforce daily objective requirement (min 5 characters) to check in.
    *   [x] Log GPS coordinates, calculate lateness (after 9:00 AM) and overtime (after 6:00 PM).
    *   [x] Inject Leaflet.js dark tilemap on HR/Admin Command Center displaying live coordinate pin drops.
    *   [x] Create clocked_in middleware blocking all portal/admin pages until clocked in.
*   **[x] Multi-Tiered Leave Workflow Engine:**
    *   [x] Tier 1 Routing: Staff $\rightarrow$ selected Line Manager $\rightarrow$ HR Manager.
    *   [x] Tier 2 Routing: Managers $\rightarrow$ CVO $\rightarrow$ HR Manager.
    *   [x] Tier 3 Routing: HR Manager $\rightarrow$ CVO.
    *   [x] Ultimate Override: Allow Super Admin to instantly bypass and approve any leave directly.
    *   [x] Auto-notification: Email duty cover notification to designated colleague on final approval.

#### 2. Individual & Quarterly Appraisal Systems
*   **[ ] Granular Individual Performance Tracking:**
    *   [ ] Implement Profile Tracking Ledger logging task completion histories and punctuality records.
    *   [ ] Build Manager Transparency Matrix showing staff performance trends.
    *   [ ] Generate automated Performance Accountability Reports directly feeding into appraisal sheets.
*   **[ ] Dynamic Quarterly Appraisal Workflow Engine:**
    *   [ ] Build Three-Step Appraisal Pipeline:
        *   *Self-Assessment Form submission* $\rightarrow$ *Line Manager Review & Score* $\rightarrow$ *HR Manager Final Audit & Validation*.
    *   [ ] Super Admin override permission to manually unlock/adjust appraisals mid-flight.
    *   [ ] Build Drag-and-Drop Appraisal Form Builder (HR Manager / Super Admin only) supporting `Add`, `Delete`, `Insert`, and `Edit Header Columns` for dynamic review configurations.

#### 3. Shared Cross-Departmental Modules
*   **[ ] Universal HRM Components:** Asset condition reports, personal leave balance counters, and manager-editable appraisal & performance templates in every departmental view.
*   **[ ] Universal Finance Components:** Out-of-pocket petty cash/reimbursement forms with receipt upload, project budget builders, supplier invoicing ingestion views (with View, Edit, Delete, Import, Export, Add Contributors controls).
*   **[x] User Management, Privileges & Attendance Geolocation**
    *   [x] Add Manager privilege override checksheets in user profiles
    *   [x] Setup DOB constraints (18-65) and alphanumeric password rule (min:8)
    *   [x] Implement attendance check-in gate (daily objective entry) and task restriction
    *   [x] Setup Leaflet.js interactive map on Admin Dashboard showing remote check-in locations
*   **[ ] Universal Import/Export & Sharing Engine:**
    *   [ ] Implement Excel, TXT, Word, CSV ingestion parser for tables, forms, budgets, appraisals, and columns
    *   [ ] Implement CSV/Excel/PDF exports for operational tables
    *   [ ] Build file sharing interface to route file links via portal chat and personal email
*   **[/] Leave Routing Workflow & Departmental Modules**
    *   [x] Create multi-tiered routing logic (T1: staff->manager->HR, T2: manager->CVO->HR, T3: HR->CVO, Super Admin override)
*   **[x] Super Admin Security Layer:** Ensure Super Admin accounts bypass all modular blocks and locks, possessing global vision/access.

#### 4. Specialized Departmental Core Enhancements
*   **[ ] HR & Admin Module (with Transport consolidated):**
    *   [ ] Visitor Management System (Visitor dashboard log, pre-ticketing clearances).
    *   [ ] Employee Lifecycle trackers (onboarding sheets, contract expirations, offboarding exit checklist).
    *   [ ] HR Analytics dashboard (headcounts, turnover, department growth).
    *   [ ] Directories: Separate Phone Directory and classified Vendor Directory.
    *   [ ] Central Vault: Repository for company-wide template and document downloads.
*   **[ ] Finance Module:** Review, approve, reject, flag dashboards for budgets/invoices/claims, and CSV/Excel exports.
*   **[ ] Operations & Projects Module:**
    *   [ ] Third-Party Vendor Management Matrix (identity, project allocation, deliverable status, performance/reliability review logs).
    *   [ ] Freelance Promoter Directory (contact, city, language, T-shirt size, height).
    *   [ ] Asset Log History (`asset_logs` checkout/checkin logs).
*   **[ ] Brands & Marketing Module:**
    *   [ ] Merchandiser & Field Materials Hub (POSM stock, uniform allocation tracking).
    *   [ ] Brand Portfolios compliance parameters & Strategy blue-prints.
*   **[ ] Creative Department Module:** Ingestion lanes for design briefs and design file version control checkins.

#### 5. External Cloud Integrations & BTL Features
*   **[ ] Dropbox API Integration:** Automatic folder structure provisioning on Campaign/Album creation, direct cloud uploads, and in-portal embedded file explorer.
*   **[ ] Client Live-Share View:** Obfuscated public tokens for campaign view sharing. Enable all staff upload/update permissions for real-time field reporting.
*   **[ ] Mobile-First Field UX Engine:** Large touch targets, direct native camera viewport access, and PWA offline storage sync.

---

### 🔄 In Progress
*   **[ ] Scope and Database Planning:** Pre-structuring the model migrations.

---

### ✅ Completed
*   **[x] Initial Portal Feature Audit & Blueprint Design**
*   **[x] Setup Master Feedback File:** Initialized `SYSTEM_GUIDE_AND_UPDATES.md` with complete spec document mapping.

---

# CMIH Portal: Final Master Feature Audit & Engineering Blueprint

## 1. System-Wide Analytics, Charts & Gamification

To provide actionable insights at a glance, both individual and collective dashboards will integrate a real-time data visualization layer using charts, metrics, and gamified performance indicators.

```
┌────────────────────────────────────────────────────────────────────────┐
│                        THE COLLECTIVE DASHBOARD                        │
├────────────────────────────────────────────────────────────────────────┤
│ ┌──────────────────┐ ┌──────────────────┐ ┌──────────────────────────┐ │
│ │  AGENCY WIN-RATE │ │   OVERDUE TASKS  │ │ DEPARTMENT PERFORMANCE   │ │
│ │      84.2%       │ │        04        │ │   🥇 Operations (98%)     │ │
│ └──────────────────┘ └──────────────────┘ └──────────────────────────┘ │
├────────────────────────────────────────────────────────────────────────┤
│ ┌────────────────────────────────────────────────────────────────────┐ │
│ │ [Chart Area] Weekly Completion Trends (Line/Bar Charts)             │ │
│ └────────────────────────────────────────────────────────────────────┘ │
├────────────────────────────────────────────────────────────────────────┤
│ ┌────────────────────────────────────────────────────────────────────┐ │
│ │ [Mega Table] 6 Departmental Sub-Tables (With Filter Views)         │ │
│ └────────────────────────────────────────────────────────────────────┘ │
└────────────────────────────────────────────────────────────────────────┘
```

### A. Core Metrics & KPI Blocks

* **Individual Dashboard Metrics:**
    * `My Task Completion Rate (%)` (Total Completed vs. Total Assigned Tasks).
    * `Active/Open Deliverables` (Count of pending line items).
    * `Punctuality Score` (Percentage of Early/On-Time Clock-Ins).
    * `Total Overtime Hours Accumulated` (Calculated automatically from post-6:00 PM clock-outs).
* **Collective Dashboard Metrics:**
    * `Agency-Wide Target vs. Reached Activations` (Aggregated real-time campaign metrics).
    * `Overall Project Win-Rate` (Completed projects vs. Cancelled/Overdue items).
    * `Critical Bottlenecks Count` (Total count of High-Priority tasks currently flagged as `Delayed` or `At Risk`).

### B. Analytical Charts Engine

Integrate a frontend charting library (such as Chart.js or ApexCharts) to display:
* **Task Performance Velocity:** A stacked bar chart visualizing tasks grouped by department or individual staff member, color-coded by status (`Completed`, `In Progress`, `Delayed`, `Overdue`).
* **Punctuality & Work Rate Radar:** A radar chart mapping attendance trends, late arrivals, and overtime workloads across different teams.
* **Weekly Completion Trends:** A multi-line running timeline showing weekly project velocity to help managers see when operations hit peak output or slowdowns.

### C. The Departmental Performance Badge (Gamification)

* **The Logic:** The system automatically calculates a rolling **Weekly Performance Rate** for each of the 6 departments based on the ratio of tasks moved to `Completed` or `Approved` ahead of deadlines against total tasks assigned.
* **The Incentive:** The department holding the highest performance rating at the end of the calculation window instantly receives a distinctive **Golden Badge** (🥇) next to its name on the Collective Dashboard. This badge dynamically migrates weekly based on real-time task updates to organically encourage healthy inter-departmental competition and performance.

---

## 2. Granular Individual Performance Tracking

* **The Tracking Ledger:** The system logs every task's history from creation to sign-off, attaching a concrete score to each individual staff member's profile.
* **The Transparency Matrix:** Managers can view individual performance charts to instantly filter and pinpoint:
    * Who is consistently completing deliverables ahead of deadlines.
    * Who is creating systemic blockages by constantly dropping tasks into `Delayed`, `At Risk`, or `Overdue` statuses.
* **Performance Accountability Reports:** These automated logs aggregate an employee's task velocity, attendance metrics, and clock-in workloads, providing objective, unalterable data inputs directly into their quarterly appraisal sheets.

---

## 3. Dynamic Quarterly Appraisal Workflow Engine

Appraisals are conducted on a strict **quarterly schedule**. The digital process follows a multi-tiered approval lane to ensure comprehensive reviews before final storage.

### A. The Three-Step Appraisal Pipeline

```
┌───────────────────────────┐      ┌───────────────────────────┐
│   Step 1: Individual      │ ───► │  Step 2: Line Manager     │
│   Self-Assessment Form    │      │  Review & Manager Score   │
└───────────────────────────┘      └───────────────────────────┘
                                                 │
                                                 ▼
┌───────────────────────────┐      ┌───────────────────────────┐
│   Final Storage & HR      │ ◄─── │    Step 3: HR & Admin     │
│   Analytics Integration   │      │    Sign-off & Verification│
└───────────────────────────┘      └───────────────────────────┘
```

1. **Individual Self-Assessment:** At the end of the quarter, the employee is prompted to fill out an individual appraisal form measuring targets hit, project contributions, and core achievements.
2. **Line Manager Intervention:** Upon submission, the document routes directly to the employee's designated **Line Manager** (or to the **CVO** if the individual is a manager/head of department). The manager reviews the self-assessment and appends their formal evaluation scores, operational feedback, and professional comments.
3. **HR & Admin Validation:** The finalized form drops into the HR & Admin module. The HR Manager conducts the final audit, checks it against the system's tracked individual performance analytics, and signs off.
* *Note:* The **Super Admin** profile retains master system permissions to view, unlock, or manually adjust any appraisal in mid-flight if a dispute requires a direct technical override.

### B. Fully Editable Database Structure for HR

While the portal provides a standard, robust appraisal template framework, **the layout must be fully dynamic and custom-configurable only by users with HR & Admin or Super Admin privileges.**

Through a drag-and-drop structural interface in the HR module, HR Managers can fully tailor the appraisal forms per department or per job level by executing the following actions:
* `Add`: Append entirely new operational performance metrics or criteria.
* `Delete`: Strip out irrelevant categories or questions.
* `Insert`: Slide text blocks, rating scales (1–5 or 1–10 matrices), or open-ended feedback areas between sections.
* `Edit Header Columns`: Modify table text headings and labels to cleanly fit the specific vocabulary of separate divisions (e.g., changing "Technical Execution" for the Creative team to "Client Communication Score" for the Client Relations team).

---

## 4. Consolidated Core Architecture Reference

To keep your Antigravity agent fully aligned on the structure, here is how these additions snap perfectly into the core modules built previously:

### A. Updated Collective Dashboard (The Mega Table)

* **Visual Section:** Global Metric Cards + Chart Engine Graphics + Rolling Golden Badge (🥇) Department Identifier.
* **Data Layout:** The 6 Master Departmental Tables containing unified `Tasks` + `Updates` data tracking project contributors and custom row/column manipulations for managers.

### B. Universal HRM Module Additions

* **All User Modules:** Include the rolling individual performance track record and the automated prompt for the **Quarterly Appraisal Submission Window**.
* **HR & Admin Management Module:** Houses the appraisal form builder (`Add`, `Delete`, `Insert`, `Edit` controls), aggregated individual velocity logs, and historical review folders.

### C. Universal Finance Module Additions

* Maintains the unified Reimbursement Pipeline, Project Budget Builder, and Supplier Invoicing fields, with access permissions tied seamlessly to the Dropbox storage gateway for asset preservation.

---

## 5. System-Wide Structural & Core Engine Upgrades

### A. The Unified Task, Update & Collective Dashboard

Instead of keeping Tasks and Updates separate, they are merged into a single **Unified Tracking Engine**. The portal features two master dashboard views:
* **Individual Dashboard:** A personalized view showing only the tasks/updates assigned to or created by that specific staff member.
* **The Collective Dashboard (The Mega Table):** A single master page displaying the entire agency's operations, broken down cleanly into **6 distinct departmental tables**. Everyone can see this dashboard, ensuring absolute transparency across the agency so everyone knows what everyone else is doing.

#### Master Departmental Tables:
1. **Client Relations Table**
2. **Operations / Projects Table**
3. **Brands & Marketing Table**
4. **Creatives Table**
5. **HR & Admin Table**
6. **Finance Table**

#### Default Table Schema Structure:
`S/N` | `Client` | `Lead Staff` | `Project/Campaign Name` | `Supporting Staff / Contributors` | `Role of Supporting Staff` | `Project Deliverables` | `Deadline` | `Delivery Status` *(Dropdown)* | `Priority` *(Dropdown)* | `Notes / Feedback`

* **Project Contributors & Support:** Any staff member can add other staff members as **Contributors** to a project or task if they need collaboration or cross-departmental help.
* **Status & Priority Color Coding:**
    * **Delivery Status:** `Open` (Gray), `In Progress` (Blue), `Awaiting Approval` (Purple), `Awaiting Feedback` (Orange), `Sent` (Light Blue), `Approved` (Light Green), `Completed` (Deep Green), `Rejected` (Red), `Paid` (Gold), `Overdue` (Bright Red), `Cancelled` (Dark Gray).
    * **Priority:** `High` (Red), `Medium` (Orange), `Low` (Green).
* **Dynamic Customization:** Departmental heads/managers (with Admin privileges) can alter their respective departmental tables by adding, deleting, inserting, or editing columns and rows to perfectly suit changing project needs. Standard Executives cannot alter schemas.
* **Task Reassignment:** Managers can easily reassign tasks to alternative staff members if the original assignee falls sick or is unavailable.

---

### B. Upgraded Messenger Framework

* **Dynamic Chat Initiation:** The system must include a dropdown selector listing all staff members. A user can select *any* staff member from this dropdown to instantly initiate a new 1-on-1 direct chat.
* **60-Second Real-Time Polling:** Implement an automated background routine that refreshes the message payload every **60 seconds** to fetch new messages without requiring a manual page refresh.
* **Sidebar Navigation Layout:** Fully build out the missing chat sidebar split into two distinct segments:
    1. *Recent Chats:* Active, ongoing 1-on-1 direct conversations.
    2. *Groups:* Active departmental channels, activation group channels, or project rooms.
* **Chat Media Gallery:** A dedicated tab inside every chat window compiling a clean grid view of all photos, videos, and documents shared within that conversation for rapid, bulk downloading.

---

### C. Advanced User Management, Privilege Elevation & Age Validation

* **Privilege Elevation:** Managers and Super Admins have access to a dedicated User Management panel where an Admin can explicitly grant Admin privileges to a regular staff member, or customize their access by checking off specific feature permissions.
* **User Action Controls:** Features an actions panel for managers to `Suspend` (revoke sessions), `Edit` (page-by-page permission matrix), `Renew` (extend contract/profile expiry), and `Delete` (soft-delete).
* **Age Validation Constraint:** Registration and profile updates must strictly enforce age filters on the Staff Date of Birth: **Must be $\ge$ 18 years and $\le$ 65 years old**.
* **Password Complexity Hint:** The registration UI must clearly display that passwords require an **Alphanumeric Only** layout.

---

### D. Multi-Tiered Leave Workflow Engine

The system logic follows a strict rule: *Everyone reports to someone.*

```
[Regular Staff] ───► [Line Manager] ───► [HR Manager] ───► [Leave Approved]
                                             ▲
[Department Manager] ────────────────────────┘ ───► [CVO Approval Required]
                                             ▲
[HR Manager] ────────────────────────────────┘ ───► [CVO Approval Required]

*Note: SUPER ADMIN can bypass all tiers and instantly approve any leave directly.
```

* **Tier 1 (Standard Staff):** Routed to their selected Line Manager $\rightarrow$ Routed to HR Manager for final sign-off.
* **Tier 2 (Managers & Department Heads):** Because they do not have a standard Line Manager, their leave requests route directly to the **CVO** for initial sign-off $\rightarrow$ Routed to HR Manager.
* **Tier 3 (HR Manager):** Because they cannot approve their own leave, their request routes directly to the **CVO** for final approval.
* **The Ultimate Override (Super Admin):** The Super Admin acts as the ultimate authority and can instantly approve any leave application directly, completely bypassing the CVO or HR loops.
* **Auto-Notification:** Once a leave request is fully approved, an automated system email is instantly dispatched to the staff member designated to cover duties during the leave window.

---

## 5. Shared Cross-Departmental Modules (Core Infrastructure)

To make the platform completely functional for day-to-day operations, **every single department module** must contain localized, integrated versions of HRM and Finance modules.

### A. Universal HRM Components (Available in All Modules)

* **Asset Management Access:** Every staff member can view their assigned office equipment or field gear and report its current condition.
* **Leave Portal:** Personal dashboard to select line managers, track remaining leave balances, and apply for time off.
* **Appraisal & Performance Templates:** A standard performance evaluation framework embedded in each department view, which can be modified or tailored by managers depending on the specific department or job role.

### B. Universal Finance Components (Available in All Modules)

* **Reimbursement / Petty Cash Pipeline:** A built-in form across all modules allowing staff to log out-of-pocket expenses incurred during errands, upload supporting receipts, and submit them directly to Finance.
* **Project Budget Builder:** Staff can select an active task/project from a dropdown menu and construct a detailed line-item budget for that execution, routing it directly to Finance.
* **Invoicing & Ingestion:** Ability to submit third-party supplier invoices with descriptions for asset or item purchases, supporting actions to View, Edit, Delete, Import, Export, and Add Contributors to the budget layout.

### C. The Super Admin Security Layer ("Silent God" Access)

* **Rule:** While certain sensitive modules—like the *Secure Personal Details Section (HR & Finance Lock)*—are explicitly hidden from regular staff and restricted to HR and Finance managers, the **Super Admin account has absolute, unrestricted access to every single module, database table, record, and feature across the entire application.** No data or action can be hidden from the Super Admin.

---

## 6. Specialized Departmental Core Enhancements

### A. HR & Admin Module (Including Logistical Transport)

* *Note: Transport is officially consolidated as a sub-unit here, not as a standalone department.*
* **Visitor Management System:** Front desk logging tools for the reception/admin team to enter visitor names, companies, purposes, and arrival/departure timestamps.
* **Visitor Pre-Ticketing:** Internal staff can log an upcoming visitor. The front desk admin receives this ticket in advance, streamlining guest check-ins.
* **Employee Lifecycle Trackers:** Onboarding checksheets, automated contract expiration flags, and offboarding/exit workflow protocols.
* **HR Analytics:** High-level executive dashboards tracking headcount metrics, turnover rates, and departmental growth trends.
* **Phone & Vendor Directories:** A central dashboard managing the corporate phone directory alongside a classified Vendor Directory (*Projects Vendors* vs. *General Office Vendors*).
* **Central Vault:** A shared storage repository for downloading all company-wide and department-specific forms and templates.

### B. Finance Module

* Dedicated management dashboards to review, approve, reject, or flag incoming project budgets, asset invoices, and staff petty cash/reimbursement forms.
* Full export options (CSV/Excel) for financial reconciliations.

### C. Operations & Projects Module (With Third-Party Vendor Tracking)

* **Third-Party Vendor Management Matrix:** A dedicated tracking engine to manage external execution vendors. It must track:
    * *Vendor Identity & Allocation:* Which vendor is being used for which specific job and which active project they are assigned to.
    * *Deliverable Status:* Clear indicators showing whether the vendor's job is done or pending.
    * *Performance Review Notes:* A critical feedback field to log notes on the quality of work (e.g., "excellent fabricator", "shallow work done", "pending reimbursement issues"). This serves as an internal reference sheet so any staff member looking to hire a vendor in the future knows exactly who is reliable and who is not.
* **Freelance Promoter Directory:** A specialized database tracking freelance hostesses, promoters, and field supervisors, logging their contact info, operational cities, language fluencies, and physical metrics (T-Shirt Size, Height) for costume allocation.
* **Asset Log History (`asset_logs`):** A strict check-out tracking schema recording the deployment of physical campaign assets (AV setups, sound bars, tablets) from warehouse to venue, capturing the equipment status on both checkout and checkin.

### D. Brands & Marketing Module (Expanded)

* **Merchandiser & Field Materials Hub:** Specialized sub-module to manage the distribution, allocation, and warehouse stock-tracking of point-of-sale materials (POSMs), pull-up banners, uniforms, and retail merchandising items.
* **Brand Portfolios & Compliance:** Digital housing for individual client brand guidelines, color profiles, asset packages, and target demographic data.
* **Campaign Strategy Blueprints:** Collaborative workspaces for mapping out market trends, competitive intelligence reports, and experiential strategy frameworks.

### E. Creative Department Module

* Direct ingestion lanes for incoming creative design briefs submitted by Client Relations or Operations.
* Internal design version controls, proofing feedback chains, and direct links to master asset deliverables.

---

## 7. External Cloud Integrations & BTL Features

### A. Dropbox API Integration

* **The System Rule (Server Offloading):** To handle massive creative design files, video reels, client brand books, and daily campaign media without crashing or slowing down the core application servers, the portal must natively integrate **Dropbox** (or a cloud bucket equivalent).
* **Core Behaviors:**
    * *Automatic Folder Provisioning:* When a new Campaign or Portfolio Album is created in the database, the portal automatically provisions a corresponding folder structure in Dropbox via the API.
    * *Direct Cloud Uploads (Messenger & Modules):* To protect server bandwidth and prevent disk storage bloat, **all file, picture, and video attachments sent in the Messenger chat channels**, Creative Briefs, and Project Budget modules are uploaded directly to Dropbox, saving only the generated Dropbox shared link paths in the local database.
    * *File Browsing:* Implement an embedded file explorer view within the portal so staff can view, organize, and download assets directly from the agency's Dropbox storage without leaving the app.

### B. Client Live-Share View

* A single-click button on Campaign records that generates an obfuscated, token-secured link (e.g., `cmih.africa/shared/campaign/xk9n-23ld-45pr`) that requires no login.
* **Privilege to Update:** *All staff members across all departments* have the system privilege to upload real-time operational photos and notes from field locations into the project feed.
* **The Client Experience:** Corporate and FMCG clients can open this link to monitor live field activation photo galleries, read crowd metrics, and check off completed project deliverables transparently.

### C. Mobile-First Field UX Engine

* **Enlarged Hit-Targets:** Optimized button padding and layout bounds designed specifically for high-stress field activation environments.
* **Native Camera Hooks:** File input fields programmatically trigger the device's native camera viewfinder on iOS/Android devices, shortening the path from live field capture to portal upload.
* **PWA Offline Data Sync:** Utilizes local database caching (Progressive Web App framework) to save message threads, asset log sign-outs, and vendor updates when working in weak-signal environments, automatically pushing all cached payloads to the live server the moment connectivity drops back online.

---

## 8. Universal Data Integration & File Sharing System

To maximize operational speed across standard and admin divisions, the portal includes an integrated file processor and direct distribution hub:

### A. Universal Imports (Excel, TXT, Word, CSV)
*   **Format Flexibility:** Ingestion parser accepting `.xlsx`, `.xls`, `.txt`, `.docx`, `.doc`, and `.csv` files.
*   **Intelligent Auto-Mapping:** Automatically maps table headers to columns for budgets, appraisals, forms, survey metrics, invoice lines, and employee databases.
*   **Column Adaptability:** Uploading a structured text/Excel file dynamically restructures/renames dashboard table columns to accommodate customized data models on the fly.

### B. Universal Exports (CSV, Excel, PDF)
*   **One-Click Extraction:** Converts active database grids, campaign activations feeds, and financial sheets into downloadable, print-ready documents.

### C. Direct Sharing Hub (Chat & Email Routing)
*   **Dropdown Recipient Selector:** Select any active user from a dynamic staff dropdown to share any campaign dashboard link, budget preview, or document path.
*   **Multi-Channel Delivery:**
    *   **Portal Message:** Drops the link automatically into the recipient's direct chat box.
    *   **Email Notification:** Automatically emails the file link to the staff member's personal contact email (the email logged during signup for receiving credentials).

---

## 9. CMIH Merchandiser Portal (New Sub-Portal Additions)

This sub-portal manages external field agents (Merchandisers) and integrates retail market tracking with administrative staff oversight.

### A. Split Authentication & Registration
*   **Internal Staff Single Sign-On:** Brands Team, HR, CVO, and Superadmins use their existing corporate logins. They do not register new accounts.
*   **External Merchandiser Self-Registration:** Merchandisers register independently.
    *   **Dob Constraint:** Date of birth must be between 18 and 65 years old.
    *   **Password Rules:** Passwords must be strictly alphanumeric (letters and numbers only) and at least 8 characters long.
    *   **Status Approval:** Registered users start as `pending` and must be activated/paired by a Brands Team administrator before they can log in.

### B. Geofenced Clock-In & Timezone Bounds
*   **Local Timezone Mapping:** Clock-in windows are validated against the local timezone of the region/KD/outlet (e.g. GMT `Africa/Accra` for Ghana shops, WAT `Africa/Lagos` for Nigerian shops).
*   **Strict Operational Windows:** Enforces three geofenced clock-in slots:
    *   *Morning:* 09:00 AM - 10:00 AM
    *   *Midday:* 12:00 PM - 01:00 PM
    *   *COB:* 04:30 PM - 05:30 PM
    *   Clocking attempts outside these windows throw a strict `403 AccessDenied: Window Closed` exception.
*   **Admin-Adjustable Geofence Radius:** Check-ins enforce a distance radius constraint (defaulting to 30 meters) using the Haversine formula, editable by admins from the System Settings panel.
*   **GPS Status Banner:** Red warning banner displays on the agent dashboard if GPS is unavailable or blocked, preventing clock-in. Background location pings are sent automatically every 5 minutes.

### C. Retail Visit Metrics & Key Distributor Ordering
*   **Store Execution Forms:** Captures POSM availability (Branded shelf and hangers) and individual SKU performance stats (OSA quantity, NPD presence, Facing count, Share of Shelf percentage, and Planogram compliance).
*   **KD Orders:** Automatically generates Carton/Crate orders to the Key Distributor for selected SKUs during visit submissions.

### D. Admin Control, Staff Pairing & Cascading Reassignment Wizard
*   **Staff Pairings:** Panel to link merchandisers to a Supervisor (Line Manager), Key Distributor (KD), Region, Territory Manager (TM), DSR, and RSM, which activates the merchandiser account.
*   **Locked Outlet Coordinates:** Outlets coordinates can be set and updated only by administrators.
*   **Cascading Reassignment Wizard:** Deleting a Key Distributor checks for dependent merchandisers, TMs, DSRs, and outlets, prompting the admin to choose an adopting KD to reassign all dependents automatically before the KD is deleted.
*   **Archiving (Soft-Delete):** Deleting a user soft-archives them. Archived profiles can be restored from the "Archive Directory" tab in Staff Management, and their historical visit records remain locked in the database under their name.
*   **Live Tracking map:** Utilizes Leaflet.js dark themed maps to draw real-time marker pins of all active merchandisers' coordinates and traces today's breadcrumbs polyline routes.

### E. Integrated HRM & Financial Tools for Field Agents
*   **Collapsible Sidebar Navigation:** Rebuilt the Merchandiser dashboard navigation to feature a collapsible, portal-style left sidebar that functions as a persistent drawer on desktop and slides in as a mobile-responsive overlay toggled by the header menu button. Grid content layouts are wrapped to ensure column structural stability on theme switches.
*   **Profile & Banking management:** Merchandisers can update their full name, email address, phone number, residential details, bank accounts, and MOMO payout numbers directly.
*   **Leaves & Absences Submission:** Agents can request leave, selecting start/end dates, leave types, and a duty-covering colleague. Approved leave dates protect the agent from payroll deduction penalties.
*   **Petty Cash Reimbursements:** Support for uploading receipt files to request out-of-pocket expenses (transit, BTL activations, logistics).
*   **Salary Advances (Loans):** Requests are validated to block submissions exceeding double the agent's monthly base salary.
*   **Self-Appraisals:** Quarterly submission of self-rating scores (1-10) and feedback directly serialized into appraisal records.
*   **POSM & Field Gear Inventory:** Tracks checkout allocations of promotional banners, materials, and uniforms.
*   **Surveys Ingestion & Builder:** Merchandisers can directly answer active surveys broadcasted by managers. Includes a self-contained survey builder/creator block directly inside the Active Surveys tab, matching the CMIH main portal survey dynamic builder.
*   **Broadcast Announcements & Alerts:** Mapped global pinned announcements and personal alerts into the main navigation with support for read status tracking.
*   **Automatic Lateness & Absence Payroll Deductions:**
    *   Dynamic calculation scans working weekdays of the billing month.
    *   Each missed clock-in slot (morning, midday, COB) incurs a **1% base salary deduction**.
    *   Each late clock-in slot (past the window start buffer) incurs a **0.5% base salary deduction**.
    *   **Leave protection:** Any day covered by an approved leave application bypasses all attendance deductions.
    *   Displays a real-time pay stub with expected slots, missed slots count, late slots count, and net payout calculation directly to the agent.


