# 🚀 Quick Start Guide

## Get the Appointment Booking System Running in 5 Minutes

### Prerequisites
- XAMPP, WAMP, MAMP, or similar installed and running
- MySQL running
- PHP 7.0+

---

## Step-by-Step Setup

### Step 1: Place Files in Web Directory
Copy the `hCI` folder to your web server's root:

- **XAMPP (Windows)**: `C:\xampp\htdocs\hCI`
- **XAMPP (Mac)**: `/Applications/XAMPP/htdocs/hCI`
- **WAMP (Windows)**: `C:\wamp64\www\hCI`
- **MAMP (Mac)**: `/Applications/MAMP/htdocs/hCI`

### Step 2: Verify MySQL is Running
- Windows: Check XAMPP Control Panel - MySQL should show as running
- Mac: Check MAMP Control Panel - MySQL should show as running
- Linux: Run `sudo systemctl status mysql`

### Step 3: Configure Database Connection (if needed)
Open `config.php` and verify/update:
```php
$servername = "localhost";
$username = "root";
$password = "";  // Enter password if you have one set
$dbname = "appointment_booking";
```

### Step 4: Initialize Database
1. Open your browser
2. Navigate to: `http://localhost/hCI/setup_db.php`
3. Wait for success message
4. Delete `setup_db.php` (optional but recommended for security)

### Step 5: Access the System!
Open: `http://localhost/hCI/`

---

## What You Get

### 📝 Main Pages

**index.php** - Booking Page
- Book new appointments
- Real-time availability checking
- Form validation

**view_appointments.php** - User Portal
- Search appointments by email
- View booking history
- Cancel future appointments

**admin.php** - Admin Dashboard
- View all appointments
- System statistics
- Update appointment status
- Delete appointments

### 🗄️ Database Tables

**users**
- Stores customer information
- Auto-created on first booking

**appointments**
- All booking records
- Status tracking
- Prevents double-booking

**time_slots**
- Available booking times
- Easily customizable

---

## First Test Booking

1. Go to `http://localhost/hCI/`
2. Fill in the form:
   - Name: `John Doe`
   - Email: `john@example.com`
   - Phone: `555-1234`
   - Service: `Consultation`
   - Date: Tomorrow's date
   - Time: `09:00`
3. Click "Book Appointment"
4. Go to "View My Appointments"
5. Search with `john@example.com`

---

## Customization Quick Tips

### Change Service Types
Edit `index.php` around line 100:
```php
<option value="Your Service">Your Service</option>
```

### Change Available Times
Edit `setup_db.php` time slots array and re-run, or manually edit database

### Change Colors
Edit `style.css`:
- Primary color: `#667eea` (purple)
- Secondary color: `#764ba2` (darker purple)

### Change Booking Window
Edit `index.php` line ~35:
```php
$max_date = date('Y-m-d', strtotime('+30 days')); // Change 30 to another number
```

---

## Accessing Different Sections

| URL | Purpose |
|-----|---------|
| `http://localhost/hCI/` | Main booking page |
| `http://localhost/hCI/view_appointments.php` | View appointments |
| `http://localhost/hCI/admin.php` | Admin dashboard |
| `http://localhost/hCI/config.php` | Database config (don't visit directly) |

---

## Troubleshooting

| Problem | Solution |
|---------|----------|
| "Connection failed" | Ensure MySQL is running in control panel |
| Setup page shows errors | Check MySQL is accessible |
| Time slots all booked | Check admin panel - refresh database or cancel old bookings |
| Can't find appointments | Make sure you're using the exact email |
| Page shows blank | Check browser console for errors, verify PHP is enabled |

---

## Important Files

- `config.php` - Database connection (keep credentials safe)
- `setup_db.php` - One-time setup (delete after use)
- `index.php` - Main application
- `style.css` - All styling

---

## Production Before Deploy

Before going live:
- [ ] Delete `setup_db.php`
- [ ] Set strong MySQL password
- [ ] Use prepared statements (see README.md)
- [ ] Enable HTTPS
- [ ] Set up automated backups
- [ ] Add email notifications
- [ ] Create admin authentication
- [ ] Test thoroughly

---

## Security Notes

⚠️ **Current version uses string escaping** - suitable for small projects
✅ **For production:** Upgrade to prepared statements (see README.md)

---

## Need Help?

1. Check the README.md for detailed documentation
2. Review database_schema.sql for table structures
3. Check admin.php for system statistics
4. Review browser console for JavaScript errors (F12)

---

**You're all set! Enjoy your appointment booking system!** 🎉
