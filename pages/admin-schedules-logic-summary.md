# Schedule Templates Page — Logic & Flow Summary

## Overview

The **Schedule Templates** page (`pages/admin-schedules.html`) is an admin-only feature in the HAMS (Hospital Appointment Management System). It allows admins to create reusable weekly schedule templates per doctor, define working days/hours/breaks, manage holidays (both template-specific and hospital-wide), add one-off doctor overrides (leave days or schedule changes), and then **generate monthly time slots** from those templates.

There are **4 backend PHP endpoints** that power this page:

| Endpoint | Purpose |
|---|---|
| `admin_get_schedule_templates.php` | Fetch all templates with days, holidays, stats |
| `admin_save_schedule_template.php` | Create/update a template (days, holidays, exceptions) |
| `admin_generate_schedule_slots.php` | Generate time_slots for a given month from a template |
| `admin_get_holidays.php` / `admin_save_holiday.php` | Manage hospital-wide holidays |
| `admin_get_doctor_exceptions.php` | Fetch one-off schedule overrides per doctor |

---

## Page Layout

```
┌─────────────────────────────────────────────────────────────┐
│  Navigation Bar (HAMS Admin nav with active tab highlight)  │
├─────────────────────────────────────────────────────────────┤
│  Sidebar  │  Page Header [New Template button]             │
│           │                                                 │
│           │  Filters Card (Month / Doctor / Department)     │
│           │                                                 │
│           │  Global Holidays Card (Add/List/Delete)         │
│           │                                                 │
│           │  Loading Spinner / Empty State / Template Grid  │
│           │    ┌──────────┐ ┌──────────┐ ┌──────────┐      │
│           │    │Card 1    │ │Card 2    │ │Card 3    │      │
│           │    └──────────┘ └──────────┘ └──────────┘      │
│           │                                                 │
│           │  Inline Editor (hidden by default, shown on     │
│           │  "New Template" or "Edit" click)                │
└─────────────────────────────────────────────────────────────┘
```

---

## Flow Logic (Step by Step)

### 1. Authentication & Initialisation

- **Session check**: `fetch('../php/session_check.php')` — if not logged in or role ≠ `admin`, redirect to `index.html?msg=access_denied`
- **DOMContentLoaded**: sets the filter month to the next month, attaches `change` listener on the editor's doctor dropdown (to sync department + load doctor exceptions), then loads departments/doctors, global holidays, and templates.

### 2. Loading Departments & Doctors

**File:** `admin_get_departments.php` + `admin_get_doctors.php`

- Both fetched in parallel via `Promise.all`
- Results populate:
  - **Filter dropdowns** (`#filter-dept`, `#filter-doctor`) — for filtering the template grid
  - **Editor dropdowns** (`#tmpl-dept`, `#tmpl-doctor`) — for creating/editing templates

### 3. Loading & Displaying Templates

**File:** `admin_get_schedule_templates.php`

- **Query parameters**: `month`, `dept_id`, `doctor_id` from the filter bar
- **SQL logic**:
  1. Fetches `schedule_templates` joined with `doctors` and `departments`
  2. Fetches all `template_days` rows for those templates (7 per template)
  3. Fetches all `template_holidays` rows
  4. Fetches **lifetime stats** from `time_slots` (total slots, booked, available)
  5. If a month filter is provided, fetches **month-specific stats** from `time_slots` filtered by `slot_date BETWEEN month-start AND month-end`
- **Response**: JSON array of template objects, each containing:
  - `template_id, template_name, doctor_id, dept_id, slot_duration, is_active`
  - `doctor_name, dept_name` (joined)
  - `days[]` — array of 7 day configs
  - `holidays[]` — template-specific holiday dates
  - `stats` — lifetime slot counts
  - `month_stats` — (optional) month-specific slot counts

**Frontend rendering** (`loadTemplates()`):
- Shows loading spinner, hides grid and empty state
- Fetches with filter params
- If no templates → shows empty state with "Create the first template" button
- Otherwise, iterates templates and builds **template cards**:
  - Card Header: template name, doctor, department, active/inactive badge
  - Card Body:
    - Meta block: slot duration, "1 patient per slot", available/booked counts
    - Day summary pills (e.g., "Mon 08:00–16:00, break 12:00–13:00" or "Tue off")
    - Holiday list (formatted dates with notes)
  - Card Footer:
    - **Edit** button → opens inline editor pre-filled
    - **Generate [Month]** button → triggers slot generation

---

### 4. Creating / Editing Templates (Inline Editor)

The editor is an **inline section** at the bottom of the page (class `inline-editor`), shown/hidden by toggling the `open` class.

#### Opening the Editor (`openTemplateEditor(templateId?)`)

- If no `templateId` → **New Template** mode:
  - Clears all fields
  - Sets default days: Mon–Fri working 08:00–16:00 with 12:00–13:00 break; Sat/Sun off
  - Resets holidays and exceptions to empty arrays
  - Calls `loadDoctorExceptions(doctorId)` if a doctor is selected (none yet, so empty)

- If `templateId` provided → **Edit mode**:
  - Finds the template object in the local `templates` array
  - Populates: name, doctor, department, duration, active checkbox
  - Loads holidays into local `holidays` array
  - Calls `buildDayRows(template.days)` to render the 7 day rows
  - Calls `loadDoctorExceptions(template.doctor_id)` to load any existing one-off overrides from the database
  - Calls `refreshExceptionList()` to render them

#### Form Structure

| Section | Fields |
|---|---|
| **Basic Info** | Name, Doctor (dropdown), Department (auto-synced from doctor, disabled), Slot Duration (5–180 min), Active checkbox |
| **Working Days** | 7 rows, each with: checkbox (working day), Start time, End time, Break Start, Break End |
| **Holidays** | Date + Note inputs, "Add holiday" button, list of added holidays with delete |
| **Doctor Exceptions** | Date, Working day checkbox, Start/End/Break times, Note, "Add override" button, list with delete |

#### Validation (Frontend)

- **Per-day**: Start/end required for working days; end > start; break must have both start and end; break must fit inside working hours
- **Global**: Name + doctor required; duration ≥ 5; at least 1 working day enabled
- **Exceptions**: Date required; no duplicate dates; if working, start/end required with same time validations as days

#### Save (`saveTemplate()`)

**POST** to `admin_save_schedule_template.php` with JSON body:
```json
{
  "template_id": null (new) or ID (edit),
  "template_name": "...",
  "doctor_id": 1,
  "slot_duration": 10,
  "is_active": 1,
  "days": [ { "day_of_week": 0, "is_working": 0, ... }, ... ],
  "holidays": [ { "holiday_date": "2026-07-04", "note": "..." }, ... ],
  "exceptions": [ { "exception_date": "2026-07-15", "is_working": 1, ... }, ... ]
}
```

**Backend logic** (in a transaction):
1. **Validate** doctor exists and is active; derive `dept_id` from doctor profile
2. **Validate** all 7 days and at least one working day
3. **Enforce**: One template per doctor — if another template already exists for the same doctor (and different ID), reject
4. **Upsert**: INSERT or UPDATE `schedule_templates`
5. **Replace days**: DELETE + INSERT all 7 `template_days` rows
6. **Replace holidays**: DELETE + INSERT all `template_holidays` rows (with `INSERT IGNORE`)
7. **Replace exceptions**: DELETE all exceptions for that doctor + INSERT all submitted exceptions (with `INSERT IGNORE`)
8. On success → commit, return `{ success: true, template_id }`
9. On failure → rollback, return error

**After save**: close editor, show toast, refresh template grid.

---

### 5. Slot Generation ("Generate [Month]" button)

**File:** `admin_generate_schedule_slots.php`

This is the core workflow — converting a weekly template into actual calendar date time slots for a specific month.

#### Input
- `template_id` — which template to use
- `month` — target month in `YYYY-MM` format
- `confirm` — (optional) boolean to skip duplicate check

#### Process

1. **Fetch template**: Get `doctor_id`, `dept_id`, `slot_duration`, `is_active` from `schedule_templates`. Reject if inactive.

2. **Calculate date range**: First day of month → last day of month.

3. **Fetch working days**: 7 `template_days` rows. Missing days default to non-working.

4. **Fetch template holidays**: Dates in `template_holidays` → stored in a lookup map.

5. **Fetch global holidays**: `SELECT FROM holidays WHERE holiday_date BETWEEN month_start AND month_end` → stored in a lookup map.

6. **Fetch doctor exceptions**: `schedule_exceptions` for this doctor within the month → stored in `exceptionMap` keyed by date.

7. **Check for existing slots**: Query `time_slots` for this doctor in this month. If duplicates found and `confirm` is false → return `{ need_confirmation: true, duplicate_count }`. The frontend shows a confirmation dialog; if user confirms, it re-posts with `confirm: true`.

8. **Iterate each day of the month** (`$current` from start to end):

   a. **Skip past dates** (before today)
   b. **Skip global holidays** — all doctors are off
   c. **Skip template holidays** — this doctor has a specific holiday
   d. **Check for doctor exception override**:
      - If exception exists AND `is_working = 0` → skip (doctor is off/on leave)
      - If exception exists AND `is_working = 1` → use exception's times instead of template day
      - If no exception → use the template day setting
   e. **Check if working day** — if `is_working = 0` → skip
   f. **Calculate time ranges** considering break window:
      - If break exists (start + end valid) → split into 2 ranges: [start → break_start] and [break_end → end]
      - If no break → single range: [start → end]
   g. **Generate slots**: Starting from range start, step by `slot_duration` minutes until end:
      - If today and slot time ≤ current time → skip (past slots)
      - **INSERT IGNORE** into `time_slots` with `capacity = 1` (single-patient slots)
      - Count created vs skipped (duplicates)

9. **Return result**: `{ success: true, created: N, skipped: N, month: "...", message: "..." }`

---

### 6. Global Holidays (Hospital-Wide)

Managed separately from template holidays. These apply to **every doctor**.

| Action | Endpoint | Method |
|---|---|---|
| Load | `admin_get_holidays.php` | GET |
| Add | `admin_save_holiday.php` | POST `{ holiday_date, name }` |
| Delete | `admin_save_holiday.php` | POST `{ action: "delete", holiday_id }` |

- Displayed in their own card at the top of the page with date + name + delete button
- Sorted chronologically
- During slot generation, all global holiday dates are **skipped for every doctor**

---

### 7. Doctor Exceptions / Overrides

- **Loaded dynamically**: When a doctor is selected in the editor's doctor dropdown, `loadDoctorExceptions(doctorId)` fires → fetches from `admin_get_doctor_exceptions.php?doctor_id=X`
- **Stored in**: `schedule_exceptions` table (doctor-level, not template-level)
- **Purpose**: Single-date overrides for a doctor (e.g., leave day, changed hours on a specific date) without modifying the recurring template
- **Saved as part of template save**: The `saveTemplate()` function includes `exceptions` in the POST body; the backend deletes all existing exceptions for that doctor and re-inserts them
- **Used during slot generation**: Exception dates override the template's day-of-week rules

---

## Key Business Rules

| Rule | Where Enforced |
|---|---|
| One template per doctor | Backend (`admin_save_schedule_template.php` line 82) |
| At least 1 working day | Frontend + Backend |
| Slot duration 5–180 min | Frontend + Backend |
| Break must fit within working hours | Frontend + Backend |
| Cannot generate from inactive template | Backend (`admin_generate_schedule_slots.php` line 27) |
| Past dates skipped during generation | Backend (line 115) |
| Past time slots today are skipped | Backend (line 174) |
| Global holidays block all doctors | Backend (line 120) |
| Doctor exceptions override template | Backend (line 125) |
| Duplicate slots use INSERT IGNORE | Backend (line 100, 193) |
| Capacity always 1 (single-patient slots) | Backend (line 190) |

---

## Summary Data Flow Diagram

```
[Page Load]
    │
    ├──→ Session Check ──→ Redirect if not admin
    │
    ├──→ Load Departments + Doctors ──→ Populate filters + editor dropdowns
    │
    ├──→ Load Global Holidays ──→ Render global holiday list
    │
    └──→ Load Templates (with filters) ──→ Render template cards
                                              │
         ┌────────────────────────────────────┼────────────────────────────┐
         ▼                                    ▼                            ▼
   [Edit Button]                       [Generate Button]           [New Template Button]
         │                                    │                            │
         ▼                                    ▼                            ▼
   Open Editor (pre-filled)           POST generate_schedule_      Open Editor (empty)
         │                            slots.php                          │
         ▼                                    │                            ▼
   Modify form                         Check for existing slots     Fill form fields
         │                              (need_confirmation?)              │
         ▼                                    │                            ▼
   POST save_schedule_                  [Confirm dialog]            POST save_schedule_
   template.php                         if duplicates exist         template.php
         │                                    │                            │
         ▼                                    ▼                            ▼
   Refresh template grid               Loop through month days      Refresh template grid
                                        → Skip holidays
                                        → Apply exceptions
                                        → Generate time slots
                                        → INSERT IGNORE
                                        → Return created count