# SSLG Voting System

A secure, web-based voting system designed for Supreme Secondary Learner Government (SSLG) elections, optimized for development and testing on Chromebook R11.

## Features

### 👥 User Roles
- **Voter (Student)**: Secure login, one-vote-only enforcement, anonymous voting
- **Admin (SSLG Adviser/COMELEC)**: Full system management, real-time monitoring

### 🗳️ Voter Features
- Secure login using Student ID + password
- One-vote-only enforcement per position
- Clear candidate display with photos, names, positions, parties
- Confirmation page before final submission
- Non-traceable vote receipt with reference code
- Automatic logout after voting session

### 🛡️ Admin Portal
- Secure admin login with hashed passwords
- Session-based authentication
- Dashboard with real-time statistics
- Candidate management (add/edit/delete)
- Student management (upload via CSV, activate/deactivate)
- Election controls (open/close voting, reset election)
- Voter ID card generation and printing
- Audit log for all admin actions

### 🔒 Security Features
- Password hashing using bcrypt
- Prepared statements (PDO) to prevent SQL injection
- CSRF protection on all forms
- Input validation and sanitization
- Role-based access control (RBAC)
- Session timeout and logout protection
- Anonymous voting (votes stored separately from voter identity)
- Election token system for additional security

## System Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- Chrome browser (optimized for Chromebook R11)

## Installation

### 1. Database Setup

1. Create a new MySQL database named `sslg_voting`
2. Import the database schema from `sql/schema.sql`

```sql
mysql -u root -p sslg_voting < sql/schema.sql
```

### 2. Configuration

1. Update database credentials in `includes/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'sslg_voting');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
```

2. Ensure the `assets/images/` directory is writable for photo uploads:
```bash
chmod 755 assets/images/
```

### 3. Web Server Setup

Place the project files in your web server's document root (e.g., `/var/www/html/` or `htdocs/` for XAMPP).

### 4. Access the System

- **Home Page**: `http://localhost/index.php`
- **Admin Login**: `http://localhost/login.php` (use ADMIN001/admin123)
- **Student Login**: `http://localhost/login.php` (use STU001/admin123)

## Usage

### For Administrators

1. **Login** with admin credentials
2. **Configure School Information** in Admin → School Info
3. **Upload Students** via CSV in Admin → Students
4. **Add Candidates** in Admin → Candidates
5. **Generate Election Token** in Admin → Election Settings
6. **Open Election** when ready
7. **Monitor** voting progress on the dashboard
8. **Close Election** when voting period ends
9. **View Results** and export data

### For Voters

1. **Login** with student ID and password
2. **Access Voting** using the election token (if required)
3. **Review Candidates** for each position
4. **Cast Votes** for available positions
5. **Confirm** vote submission
6. **View Results** after voting or when election closes

## CSV Format for Student Upload

Create a CSV file with the following columns:
```
student_id,first_name,last_name,grade,section,password
STU001,John,Doe,12,A,password123
STU002,Jane,Smith,12,B,password456
```

## Security Considerations

- Change default admin password immediately
- Use HTTPS in production
- Regularly backup the database
- Monitor audit logs for suspicious activity
- Keep PHP and MySQL updated
- Use strong passwords for all accounts

## File Structure

```
sslg-voting/
├── index.php              # Home page
├── login.php              # Login page
├── vote.php               # Voting interface
├── results.php            # Election results
├── logout.php             # Logout handler
├── admin/                 # Admin panel
│   ├── dashboard.php      # Admin dashboard
│   ├── school.php         # School info management
│   ├── students.php       # Student management
│   ├── candidates.php     # Candidate management
│   ├── election.php       # Election settings
│   └── print_ids.php      # Voter ID printing
├── includes/              # Core files
│   └── config.php         # Configuration and functions
├── assets/                # Static assets
│   ├── css/
│   ├── js/
│   └── images/
├── sql/                   # Database files
│   └── schema.sql         # Database schema
└── README.md              # This file
```

## Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Verify database credentials in `config.php`
   - Ensure MySQL service is running
   - Check database exists and user has permissions

2. **File Upload Errors**
   - Ensure `assets/images/` directory exists and is writable
   - Check PHP upload limits in `php.ini`

3. **Session Issues**
   - Ensure `session.save_path` is writable
   - Check for conflicting session configurations

4. **Permission Errors**
   - Set proper file permissions (755 for directories, 644 for files)
   - Ensure web server user can read/write necessary directories

### Debug Mode

Enable error reporting for debugging:
```php
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

## License

This project is developed for educational purposes. Please ensure compliance with local election laws and regulations when deploying in production.

## Support

For technical support or questions, please contact the development team.
