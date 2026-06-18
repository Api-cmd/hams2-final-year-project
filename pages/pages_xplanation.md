

---

**HTML Pages — what each one does**

Here's every page explained plainly:

---

**`index.html`**
The login page. The first page anyone sees. Has an email and password form that submits to `php/login.php`. PHP checks the database, and if correct sends the user to their dashboard based on their role — patient, staff, or admin.

---

**`pages/register.html`**
Where a new patient creates their account. Collects name, email, phone, and password. Has a live password strength bar and a confirm password match check built in JavaScript. Submits to `php/register.php` which saves the account to the database.

---

**`pages/dashboard.html`** *(patient)*
The patient's home page after login. Shows four summary numbers at the top — total booked, upcoming, completed, cancelled. Below that is a table of their 5 most recent appointments. At the bottom are three shortcut cards linking to booking, appointments list, and family profiles. All numbers and table rows are loaded from PHP by JavaScript when the page opens.

---

**`pages/book.html`** *(patient)*
Where a patient books an appointment. Works in four steps that reveal one at a time — first pick a department, then a doctor, then a date and available time slot, then confirm. Each step loads data from PHP without reloading the page. When confirmed it saves the appointment and marks the slot as taken.

---

**`pages/appointments.html`** *(patient)*
Shows all the patient's appointments in a table with filter tabs — All, Upcoming, Past, Cancelled. Clicking a row opens a detail panel below the table showing full information. Appointments that are pending or confirmed have a Cancel button. Cancelling frees the slot so another patient can book it.

---

**`pages/family.html`** *(patient)*
Lets a patient add family members like a child, spouse, or parent. Each member appears as a card showing their name, relationship, and age. When booking an appointment on the booking page, the patient can choose to book for a family member instead of themselves.

---

**`pages/profile.html`** *(all roles)*
Any logged-in user can view and update their personal information — name, email, and phone. There is also a separate section to change their password. The current password must be entered correctly before a new one is accepted.

---

**`pages/staff-dashboard.html`** *(staff)*
The doctor's home page. Shows four stat cards — today's total patients, how many have arrived, how many have been seen, and how many are still waiting. Below is a table of today's patient queue in time order. Each row has an action button — Check In when a patient arrives, Mark Seen after the consultation.

---

**`pages/staff-schedule.html`** *(staff)*
The doctor's full appointment history across all dates — not just today. Appointments are grouped by date with a clear header for each day. Today's section is highlighted in blue with a TODAY label. Has five filter tabs — All, Upcoming, Today, Past, No Shows — plus a date range picker and a search box to find a specific patient by name or phone. The Jump to Today button scrolls straight to today's section.

---

**`pages/admin-dashboard.html`** *(admin)*
The system overview for the administrator. Four stat cards show total patients, total doctors, today's appointments, and how many appointments are pending confirmation. Below is a table of today's appointments across all doctors with a quick Confirm button on each row. A second table shows the five most recently registered patients.

---

**`pages/admin-users.html`** *(admin)*
Full list of all patient and staff accounts. Has a search box and role filter that work instantly without reloading. The Add User button opens a popup form to create a new account. Each row has an Edit button that opens the same form pre-filled, and a disable/enable button to block or restore access.

---

**`pages/admin-doctors.html`** *(admin)*
Lists all doctors with their department and specialization. The Add Doctor button opens a form where the admin picks an existing staff account, assigns a department, and adds a specialization. Only staff accounts that don't already have a doctor profile appear in the dropdown.

---

**`pages/admin-departments.html`** *(admin)*
Lists all hospital departments. Admin can add new ones, edit names and descriptions, and disable departments that are no longer in use. Each department card shows how many doctors are assigned to it.

---

**`pages/admin-slots.html`** *(admin)*
Where the admin creates time slots for doctors. Instead of creating one slot at a time, the admin picks a doctor, a date, a start time, an end time, and a slot duration — then the system generates all slots in that range at once. For example 08:00 to 12:00 with 30-minute slots creates 8 slots in one click. Free slots can be deactivated; booked slots cannot be touched.

---

**`pages/admin-appointments.html`** *(admin)*
Full table of every appointment in the system across all doctors and patients. Has a search box, status filter, and date filter. The admin can change any appointment's status directly from a dropdown in the table — no separate page needed.