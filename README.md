# HAMS2 - Hospital Appointment Management System (Simplified)

A simplified online appointment booking system with only **Patient** and **Admin** roles. Unlike the original HAMS system, HAMS2 does not have doctor login - appointments are booked by department instead.

## Key Differences from HAMS

- **No Doctor Role**: Staff/doctor login removed - only Patient and Admin roles
- **Department-Based Booking**: Patients book appointments by department, not by specific doctor(also he can book by a specif doctor)
- **Simplified Workflow**: 3-step booking process (Department → Date/Time → Confirm) instead of 4-step
- **Reduced Complexity**: No doctor management, schedules, or staff dashboards

## Features

### For Patients
- Self-registration with email verification
- Book appointments by department
- View appointment history
- Book for family members
- Cancel appointments
- Real-time slot availability

### For Admins
- User management (patients and admins)
- Department management
- Time slot management
- Appointment overview and status management
- Dashboard with statistics
- Auto-refreshing data

## Database Schema

The system uses MySQL/MariaDB with the following tables:

- `users` - Patient and admin accounts
- `departments` - Hospital departments
- `time_slots` - Available appointment slots by department
- `appointments` - Booked appointments
- `family_profiles` - Family member profiles for booking
- `login_attempts` - Security monitoring

## Installation

### Prerequisites
- PHP 7.4 or higher
- MySQL/MariaDB
- Web server (Apache/Nginx)
- XAMPP (recommended for Windows)

### Setup Instructions

1. **Copy the project**
   - Place the `hams2` folder in your web server's document root (e.g., `C:\xampp\htdocs\hams2`)

2. **Create the database**
   ```bash
   # Import the SQL schema
   mysql -u root -p < hams2_database_schema.sql
   ```
   Or use phpMyAdmin to import `hams2_database_schema.sql`

3. **Configure database connection**
   - Edit `php/config.php` if needed (default: root/empty password, database: hams2_db)

4. **Access the application**
   - Patient registration: `http://localhost/hams2/pages/register.html`
   - Login: `http://localhost/hams2/index.html`
   - Admin dashboard: `http://localhost/hams2/pages/admin-dashboard.html`

### Default Admin Credentials
- **Email**: admin@hams2.com
- **Password**: admin123

⚠️ **Important**: Change the default admin password in production!

## Project Structure

```
hams2/
├── css/
│   └── style.css          # Main stylesheet
├── js/
│   └── main.js            # Shared JavaScript functions
├── pages/
│   ├── index.html         # Login page
│   ├── register.html      # Patient registration
│   ├── dashboard.html     # Patient dashboard
│   ├── book.html          # Appointment booking
│   ├── appointments.html  # Patient appointment history
│   ├── family.html        # Family member management
│   ├── profile.html       # Patient profile
│   ├── admin-dashboard.html
│   ├── admin-users.html
│   ├── admin-departments.html
│   ├── admin-slots.html
│   └── admin-appointments.html
├── php/
│   ├── config.php         # Database configuration
│   ├── login.php          # Login handler
│   ├── register.php       # Registration handler
│   ├── logout.php         # Logout handler
│   ├── session_check.php  # Session validation
│   ├── get_departments.php
│   ├── get_slots.php
│   ├── book_appointment.php
│   ├── cancel_appointment.php
│   ├── get_appointments.php
│   ├── get_family.php
│   ├── save_family.php
│   ├── get_profile.php
│   ├── save_profile.php
│   ├── admin_get_stats.php
│   ├── admin_get_users.php
│   ├── admin_save_user.php
│   ├── admin_get_departments.php
│   ├── admin_save_department.php
│   ├── admin_get_slots.php
│   ├── admin_save_slots.php
│   ├── admin_get_appointments.php
│   ├── admin_update_appointment.php
│   ├── admin_get_today.php
│   └── cache.php
├── cache/                  # File-based cache
└── hams2_database_schema.sql
```

## Security Features

- Password hashing with bcrypt
- SQL injection protection via prepared statements
- CSRF protection via session tokens
- Rate limiting on login attempts
- IP-based and email-based login throttling
- Session management with secure cookies
- Input sanitization and validation

## Time Zone

The system is configured for **East Africa Time (EAT)** - `Africa/Dar_es_Salaam`. To change this, edit `php/config.php`:

```php
date_default_timezone_set('Africa/Dar_es_Salaam');
```

## API Endpoints

### Patient Endpoints
- `POST /php/login.php` - User login
- `POST /php/register.php` - Patient registration
- `GET /php/get_departments.php` - Get departments
- `GET /php/get_slots.php?dept_id=X&date=YYYY-MM-DD` - Get available slots
- `POST /php/book_appointment.php` - Book appointment
- `POST /php/cancel_appointment.php` - Cancel appointment
- `GET /php/get_appointments.php` - Get patient appointments
- `GET /php/get_family.php` - Get family profiles
- `POST /php/save_family.php` - Save family profile

### Admin Endpoints
- `GET /php/admin_get_stats.php` - Dashboard statistics
- `GET /php/admin_get_users.php` - Get all users
- `POST /php/admin_save_user.php` - Create/update user
- `GET /php/admin_get_departments.php` - Get departments
- `POST /php/admin_save_department.php` - Create/update department
- `GET /php/admin_get_slots.php` - Get time slots
- `POST /php/admin_save_slots.php` - Create/update slots
- `GET /php/admin_get_appointments.php` - Get all appointments
- `POST /php/admin_update_appointment.php` - Update appointment status
- `GET /php/admin_get_today.php` - Get today's appointments

## Browser Support

- Chrome/Edge (latest)
- Firefox (latest)
- Safari (latest)

## License

This project is provided as-is for educational and commercial use.

## Support

For issues or questions, refer to the code comments or contact the development team.
