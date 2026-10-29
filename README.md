# 📅 Appointment Booking System

A complete, fully-featured appointment booking system built with PHP and MySQLi.

## Features

✅ **User Registration** - Automatically creates user profiles on booking
✅ **Appointment Booking** - Easy-to-use interface for scheduling appointments
✅ **Real-time Availability** - Prevents double-booking with automatic slot management
✅ **Appointment Management** - View, search, and cancel appointments
✅ **Responsive Design** - Works on desktop, tablet, and mobile devices
✅ **Database Integration** - MySQLi connection with automatic schema creation
✅ **Validation** - Client and server-side input validation
✅ **Professional UI** - Modern gradient design with smooth animations

## System Requirements

- PHP 7.0 or higher
- MySQL 5.7 or higher
- Apache or Nginx web server
- Local development environment (XAMPP, WAMP, MAMP, or similar)

## Installation & Setup

### 1. Prepare Your Environment
- Install XAMPP, WAMP, MAMP, or similar local development server
- Ensure MySQL is running
- Place the project folder in your web server's root directory:
  - **XAMPP**: `C:\xampp\htdocs\` (Windows) or `/Applications/XAMPP/htdocs/` (Mac)
  - **WAMP**: `C:\wamp64\www\`
  - **MAMP**: `/Applications/MAMP/htdocs/`

### 2. Configure Database Connection
Open `config.php` and update the database credentials if needed:

```php
$servername = "localhost";
$username = "root";
$password = ""; // Set your MySQL password here if you have one
$dbname = "appointment_booking";
```

### 3. Initialize the Database
1. Open your web browser
2. Navigate to: `http://localhost/hCI/setup_db.php`
3. Wait for the message: "Setup completed! You can now delete this file and navigate to index.php"
4. **Delete the `setup_db.php` file** after setup completes (optional but recommended for security)

### 4. Access the System
Navigate to: `http://localhost/hCI/`

## File Structure

```
hCI/
├── index.php              # Main booking page
├── view_appointments.php  # View and manage appointments
├── config.php             # Database configuration
├── setup_db.php           # Database initialization (delete after setup)
├── style.css              # Styling
└── README.md              # This file
```

## Database Schema

### Users Table
- `id` - Primary key
- `name` - User's full name
- `email` - Email address (unique)
- `phone` - Phone number
- `created_at` - Registration timestamp

### Appointments Table
- `id` - Primary key
- `user_id` - Foreign key to users
- `appointment_date` - Date of appointment
- `appointment_time` - Time of appointment
- `service_type` - Type of service
- `duration` - Duration in minutes (default: 30)
- `notes` - Additional notes
- `status` - scheduled, completed, or cancelled
- `created_at` - Creation timestamp

### Time Slots Table
- `id` - Primary key
- `slot_time` - Available time slot
- `is_available` - Availability status

## How to Use

### Booking an Appointment
1. Go to the home page (`index.php`)
2. Fill in your personal information:
   - Full Name (required)
   - Email Address (required - used for identification)
   - Phone Number (optional)
3. Select the service type you need
4. Choose the appointment date (up to 30 days ahead)
5. Select an available time slot
6. Add any additional notes (optional)
7. Click "Book Appointment"

### Viewing Your Appointments
1. Go to "View My Appointments" page
2. Enter your email address
3. Click "Search"
4. View all your bookings
5. Cancel future appointments if needed

## Default Time Slots

The system comes with the following available time slots:
- 09:00 - 09:30 - 10:00 - 10:30 - 11:00 - 11:30
- 13:00 - 13:30 - 14:00 - 14:30 - 15:00 - 15:30
- 16:00 - 16:30 - 17:00

To modify time slots, edit the `setup_db.php` file or directly modify the database.

## Features in Detail

### Security
- Input sanitization using `real_escape_string()`
- Prepared statements recommended for production
- Email validation
- Unique email addresses for user identification

### User Experience
- Booked time slots are disabled in the dropdown
- Prevented double-booking with database constraints
- Automatic user creation on first booking
- Confirmation messages with feedback
- Responsive mobile design

### Data Management
- Appointments can only be cancelled if they're in the future
- Cancelled appointments don't free up slots (archived)
- Complete appointment history maintained
- Status tracking (scheduled, completed, cancelled)

## Customization

### Modify Service Types
Edit in `index.php` in the service_type select dropdown:
```php
<option value="Your Service">Your Service</option>
```

### Change Available Dates
In `index.php`, modify the max_date calculation:
```php
$max_date = date('Y-m-d', strtotime('+30 days')); // Change 30 to desired days
```

### Update Time Slots
Edit in `setup_db.php` in the time slot insertion section, or run a custom SQL query:
```sql
INSERT INTO time_slots (slot_time) VALUES ('08:00:00');
```

### Customize Appearance
Edit `style.css` to change colors, fonts, and layout. Current color scheme uses purple gradient (#667eea and #764ba2).

## Troubleshooting

### Database Connection Error
- Ensure MySQL is running
- Check credentials in `config.php`
- Verify database name matches

### "Connection refused"
- Start your MySQL server
- Check if it's listening on localhost:3306

### Time slots showing as booked when they shouldn't be
- Check the appointments table for cancelled appointments
- Ensure appointment dates match the selected date

### Setup page not working
- Ensure `setup_db.php` is in the correct directory
- Check PHP error logs
- Verify MySQL user has CREATE DATABASE permission

## Production Deployment

For production use:

1. **Use Prepared Statements** instead of string concatenation for all queries
2. **Implement User Authentication** for accessing appointments
3. **Add CSRF Protection** to forms
4. **Use HTTPS** to secure data transmission
5. **Implement Rate Limiting** on booking endpoints
6. **Delete `setup_db.php`** from the server
7. **Update database credentials** to a strong password
8. **Set up automated backups** of the database
9. **Add email notifications** for confirmations
10. **Implement admin panel** for managing appointments

## Example Prepared Statement (for production)

```php
$stmt = $conn->prepare("INSERT INTO users (name, email, phone) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $name, $email, $phone);
$stmt->execute();
```

## Support & Maintenance

- Regularly backup your database
- Monitor available time slots
- Archive old appointments periodically
- Update PHP and MySQL regularly
- Review security practices annually

## License

Free to use and modify for personal and commercial projects.

---

**Version**: 1.0
**Last Updated**: April 2026
**Created**: Simple Appointment Booking System
