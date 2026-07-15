# Booking Appointment — Activity Diagram & Main Workflow Stages

## Activity Diagram

```mermaid
flowchart TD
    START([Patient logs in]) --> LOAD_PAGE

    subgraph LOAD_PAGE [Page Initialization]
        A1[Session check: verify patient role] --> A2[Fetch departments from get_departments.php]
        A2 --> A3[Fetch family profiles from get_family.php]
        A3 --> A4[Set date limits: today → 30 days ahead]
        A4 --> WAIT_DEPT
    end

    subgraph WAIT_DEPT [Step 1 — Select Department]
        B1[Patient selects a department] --> B2[Reset all subsequent sections]
        B2 --> B3[Fetch doctors for filter dropdown from get_doctors.php]
        B3 --> B4[Advance step indicator to 2]
        B4 --> B5[Show Date & Slot section]
        B5 --> WAIT_DATE
    end

    subgraph WAIT_DATE [Step 2 — Select Date & Filter Doctor]
        C1[Patient optionally filters by doctor] --> C2[Patient selects a date]
        C2 --> C3{Backend: validate date}
        C3 -- Invalid --> C3a[Return error]
        C3 -- Valid --> C4{Backend: check hospital holidays}
        C4 -- Holiday --> C4a[Return holiday message + stop]
        C4 -- Not holiday --> C5{Backend: check doctor availabilty<br/>(if specific doctor requested)}
        C5 -- Doctor unavailable --> C5a[Return unavailable message + stop]
        C5 -- Available --> C6[Backend: query available slots from time_slots table<br/>Filter: dept_id, date, is_active=1, is_booked=0,<br/> past time slots excluded]
        C6 --> C7{Any slots found?}
        C7 -- No --> C7a[Return fully_booked message]
        C7 -- Yes --> C8[Backend: calculate availability status<br/>(few_slots if ≤ 3, otherwise available)]
        C8 --> C9[Return slot data to frontend]
        C9 --> C10{Frontend: same-day?}
        C10 -- Yes --> C11[Filter out slots that have already passed<br/>(start_time <= current time)]
        C10 -- No --> C12[Keep all slots]
        C11 --> C12
        C12 --> C13{Any remaining slots?}
        C13 -- No --> C13a[Show "No available slots" message]
        C13 -- Yes --> C14[Render slot buttons in grid<br/>with time + doctor name if assigned]
        C14 --> WAIT_SLOT
    end

    subgraph WAIT_SLOT [Slot Selection]
        D1[Patient clicks a time slot button] --> D2{Frontend: safety check<br/>has start_time passed?}
        D2 -- Passed --> D2a[Show error alert + reject]
        D2 -- OK --> D3[Highlight selected button<br/>store slot_id in hidden input]
        D3 --> D4{Slots has pre-assigned doctor?}
        D4 -- Yes --> D5[Show doctor-bound info alert<br/>with doctor name & specialization]
        D5 --> D6[Automatically set doctor_id to bound doctor]
        D6 --> D7[Advance step indicator to 4<br/>Show doctor section (read-only) + confirm section]
        D7 --> WAIT_CONFIRM
        D4 -- No --> D8[Advance step indicator to 3]
        D8 --> D9[Show Doctor Selection section]
        D9 --> WAIT_DOCTOR
    end

    subgraph WAIT_DOCTOR [Step 3 — Optional Doctor Selection]
        E1[Load doctors for department from get_doctors.php] --> E2{Patient chooses?}
        E2 -- Select a doctor --> E3[Set doctor_id to selected doctor]
        E2 -- Skip / Auto-assign --> E4[Keep doctor_id as null for auto-assign]
        E3 --> E5[Advance step indicator to 4]
        E4 --> E5
        E5 --> E6[Show Confirm section + update summary]
        E6 --> WAIT_CONFIRM
    end

    subgraph WAIT_CONFIRM [Step 4 — Confirm Booking]
        F1[Patient selects family member<br/>(Self or family profile)] --> F2[Enter optional notes / symptoms<br/>(max 500 chars)]
        F2 --> F3[Review booking summary:<br/>Department · Date · Time · Doctor]
        F3 --> F4[Click "Confirm Booking"]
        F4 --> F5{Frontend: final time check<br/>has slot passed during interaction?}
        F5 -- Passed --> F5a[Show error + reject]
        F5 -- OK --> SUBMIT
    end

    subgraph SUBMIT [Backend — book_appointment.php]
        G1[Validate: slot_id, dept_id required] --> G2{Slot exists in DB?<br/>is_active=1, is_booked=0}
        G2 -- No --> G2a[Return 409: slot no longer available]
        G2 -- Yes --> G3{Department matches?}
        G3 -- No --> G3a[Return 400: department mismatch]
        G3 -- Yes --> G4{Server-side: past time check<br/>slot_date < today?<br/>OR same-day but start_time <= now?}
        G4 -- Past --> G4a[Return 400: cannot book past slot]
        G4 -- Valid --> G5{Dose slot have pre-assigned doctor?}
        G5 -- Yes --> G6[Honor the pre-assigned doctor_id]
        G5 -- No --> G7{Did patient select a doctor?}
        G7 -- Yes --> G8[Validate selected doctor exists,<br/>is active, belongs to same department]
        G8 -- Invalid --> G8a[Return 400: doctor not available]
        G8 -- Valid --> G9
        G7 -- No / Auto-assign --> G10[Auto-assign: SELECT random active doctor<br/>from same department]
        G10 --> G9
        G9 --> TRANSACTION
    end

    subgraph TRANSACTION [Database Transaction]
        H1[Begin transaction] --> H2[UPDATE time_slots<br/>SET booked_count=1, is_booked=1<br/>WHERE slot_id=? AND is_booked=0]
        H2 --> H3{rowCount === 0?<br/>(slot taken by another user)}
        H3 -- Yes --> H3a[ROLLBACK + return 409: slot taken]
        H3 -- No --> H4[INSERT into appointments<br/>(patient, family_profile, dept, doctor,<br/>slot, notes, status='confirmed')]
        H4 --> H5[Get new appointment ID<br/>(lastInsertId)]
        H5 --> H6[COMMIT transaction]
        H6 --> H7[Log audit record]
        H7 --> RETURN_SUCCESS
    end

    subgraph RETURN_SUCCESS [Result]
        I1[Return JSON: success=true, appt_id=X] --> I2[Frontend: hide booking form]
        I2 --> I3[Show success message with<br/>link to My Appointments page]
        I3 --> END([Done])
    end

    RETURN_ERROR([Return JSON error message]) --> I2a[Frontend: show error notification]
    I2a --> WAIT_CONFIRM
```

---

## Main Workflow Stages

The booking system follows a **4-step wizard** with comprehensive validation at every stage. Below is each stage broken down with its purpose, trigger, key logic, and error handling.

---

### Stage 0: Page Initialization

| Aspect | Details |
|---|---|
| **Trigger** | Patient navigates to `pages/book.html` |
| **Key Actions** | ① Verify session via `session_check.php` — redirect to login if not a patient ② Fetch departments from `get_departments.php` ③ Fetch family profiles from `get_family.php` ④ Set date input limits (`today` to `today + 30 days`) |
| **Output** | Department dropdown populated; family dropdown populated; date picker constrained |

---

### Stage 1: Select Department

| Aspect | Details |
|---|---|
| **Trigger** | Patient changes the department `<select>` dropdown |
| **Key Actions** | ① Reset all downstream sections (date, slots, doctor, confirm) ② Fetch doctors for the selected department via `get_doctors.php?dept_id=X` — populates the optional filter dropdown ③ Advance step indicator from 1 → 2 ④ Show the Date & Slot section |
| **Validation** | No validation needed here — the dropdown ensures only valid departments |
| **Error Handling** | If doctor fetch fails, the filter dropdown is left empty (non-blocking) |
| **Output** | Step 2 visible; doctor filter dropdown ready |

---

### Stage 2: Select Date & Choose Slot

This is the most complex stage, involving both frontend interaction and backend validation.

#### 2A — Backend Slot Availability Check (`get_slots.php`)

When the patient selects a date, the frontend calls `get_slots.php?dept_id=X&date=YYYY-MM-DD` (optionally with `&doctor_id=Y`).

The PHP backend performs a **cascading series of checks** in order:

| Check # | Condition | Failure Response |
|---|---|---|
| 1 | **Hospital-wide holiday**: query `holidays` table for the date | `{ status: "holiday", message: "This date is a hospital holiday..." }` |
| 2 | **Doctor exception** (if filtering by doctor): query `schedule_exceptions` for the doctor+date | `{ status: "unavailable", message: "Dr. X is unavailable..." }` |
| 3 | **Doctor working day** (if filtering by doctor): check `template_days` for the day-of-week | `{ status: "not_working", message: "Dr. X does not work on Monday" }` |
| 4 | **Doctor template holiday** (if filtering by doctor): check `template_holidays` | `{ status: "holiday", message: "Dr. X has a scheduled holiday" }` |
| 5 | **No doctor filter**: check if *any* slots exist in `time_slots` for that dept+date (excluding past) | `{ status: "unavailable", message: "No appointments available on Sunday" }` |
| 6 | **Final query**: fetch available slots with JOIN on `doctors` for doctor name | Empty result → `{ status: "fully_booked", message: "All slots are booked" }` |

#### 2B — Frontend Rendering

| Aspect | Details |
|---|---|
| **Same-day filtering** | If the selected date is today, the frontend calls `filterPassedSlots()` which removes any slot whose `start_time` has already passed (compares `slotDateTime > now`) |
| **Status display** | If status is `few_slots` (≤ 3 remaining), a warning banner is shown |
| **Slot buttons** | Each slot renders as a clickable button showing the time (12-hour format) and optionally the doctor's name if the slot is pre-assigned |

#### 2C — Slot Selection

| Aspect | Details |
|---|---|
| **Trigger** | Patient clicks a slot button |
| **Double-check** | Frontend performs an **additional safety check** — if same-day, re-verifies the slot start time hasn't passed *since the list was loaded* |
| **Pre-assigned doctor?** | If `slot.doctor_id` is set: skip doctor selection entirely, auto-populate doctor, advance to step 4. Otherwise: proceed to optional doctor selection (step 3) |
| **Visual** | Selected slot gets `.selected` class; slot_id stored in hidden input |

---

### Stage 3: Optional Doctor Selection

| Aspect | Details |
|---|---|
| **Trigger** | A slot without a pre-assigned doctor is selected |
| **Key Actions** | ① Fetch doctors for the department via `get_doctors.php?dept_id=X` ② Populate doctor dropdown with "Auto-assign doctor" as default ③ Advance step indicator to 3 |
| **Patient Options** | • **Select a doctor** — the dropdown value is set as `doctor_id` • **Skip** — clicks "Skip Doctor Selection" button, which leaves `doctor_id` as empty string (null) for auto-assignment |
| **Auto-assign Logic** | The backend (`book_appointment.php`) handles auto-assignment: `SELECT doctor_id FROM doctors WHERE dept_id = ? AND is_active = 1 ORDER BY RAND() LIMIT 1` |
| **Output** | Step indicator moves to 4; Confirm section shown with updated summary |

---

### Stage 4: Confirm & Submit Booking

#### 4A — Booking Preparation

| Aspect | Details |
|---|---|
| **Family selection** | Patient chooses who the appointment is for ("Myself" with `profile_id=0`, or a linked family member) |
| **Notes/Symptoms** | Optional text area, max 500 characters with live character counter |
| **Summary** | Auto-generated: `Department · Date at Time · Doctor Name` |

#### 4B — Frontend Final Verification

Before sending to the backend, the frontend performs one last check:

```
if selectedDate === today:
    construct slotDateTime from start_time
    if slotDateTime <= now:
        → reject with error "This time slot has already passed"
```

#### 4C — Backend Submission (`book_appointment.php`)

The booking is submitted via `POST` with a JSON body containing `{ slot_id, dept_id, doctor_id, notes, family_profile_id }`.

The backend processes the request through a **transactional pipeline**:

| Step | Action | Failure Handling |
|---|---|---|
| 1 | **Validate required fields**: slot_id, dept_id must be non-zero | 400 "Missing required fields" |
| 2 | **Verify slot existence**: `SELECT FROM time_slots WHERE slot_id=? AND is_active=1 AND is_booked=0` | 409 "Slot no longer available" |
| 3 | **Department match**: confirm `slot.dept_id === dept_id` | 400 "Department mismatch" |
| 4 | **Server-side past-time check**: compare slot_date + start_time against server's current time | 400 "Cannot book a past slot" |
| 5 | **Doctor resolution**: honor pre-assigned doctor → else if patient selected → validate → else auto-assign random active doctor from department | 400 if selected doctor invalid for department |
| 6 | **BEGIN TRANSACTION** | |
| 7 | **Reserve slot**: `UPDATE time_slots SET booked_count=1, is_booked=1 WHERE slot_id=? AND is_booked=0` | If `rowCount() === 0` → ROLLBACK, 409 "Slot taken" |
| 8 | **Insert appointment**: `INSERT INTO appointments (patient_user_id, family_profile_id, dept_id, doctor_id, slot_id, notes, status='confirmed')` | ROLLBACK on failure |
| 9 | **COMMIT transaction** | |
| 10 | Return `{ success: true, appt_id: X }` | |

#### 4D — Post-Submission Result

| Outcome | Frontend Behavior |
|---|---|
| **Success** | Hide the booking form card; show `#success-box` with green success message and link to My Appointments page; scroll to top |
| **Error** | Show error notification via `notify()` (modern toast); re-enable the confirm button; patient can try again |

---

## State Diagram (Summary)

```
[Empty Form]
    │
    ▼
[Step 1: Department Selected] ──► Next
    │
    ▼
[Step 2: Date + Slot Selected] ──► Next
    │
    ├── Slot has pre-assigned doctor ──► Skip to Step 4
    │
    └── Slot has no doctor ──► [Step 3: Optional Doctor] ──► Next
                                        │
                                        ├── Doctor selected
                                        └── Skipped (auto-assign)
    │
    ▼
[Step 4: Confirm + Submit]
    │
    ├── Frontend validation passes ──► POST to book_appointment.php
    │                                     │
    │                                     ├── Success ──► Show success screen
    │                                     └── Error ──► Show error + return to confirm
    │
    └── Frontend validation fails ──► Show error + stay on confirm
```

---

## Key Design Decisions

| Decision | Rationale |
|---|---|
| **4-step wizard** | Breaks a complex booking into manageable, focused steps; reduces cognitive load |
| **Server-side slot reservation via transaction** | Prevents double-booking; the `UPDATE ... WHERE is_booked=0` combined with `rowCount()` check is an atomic optimistic lock |
| **Cascading holiday/availability checks** | Early termination avoids unnecessary DB queries and provides specific, actionable error messages |
| **Same-day past-slot filtering on both frontend AND backend** | Defense-in-depth — the backend is the authoritative source, but the frontend provides immediate UX feedback |
| **Doctor auto-assignment as random** | Simple and fair; could be enhanced with load-balancing logic (e.g., least-booked doctor first) |
| **Transaction with ROLLBACK** | Ensures atomicity: either both the slot reservation and appointment insert succeed, or neither does |

---

## Sequence of HTTP Requests

```
1. GET  php/session_check.php         → verify patient is logged in
2. GET  php/get_departments.php        → load department list
3. GET  php/get_family.php             → load family profiles
4. GET  php/get_doctors.php?dept_id=X → load doctors (for filter dropdown)
5. GET  php/get_slots.php?dept_id=X&date=Y → load available slots
6. GET  php/get_doctors.php?dept_id=X → load doctors (for doctor selection, step 3)
7. POST php/book_appointment.php       → submit booking (JSON body)