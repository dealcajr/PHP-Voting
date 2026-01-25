# SSLG Voting System

A secure, web-based voting system designed for Supreme Secondary Learner Government (SSLG) elections, optimized for development and testing on Chromebook R11.

## Features

### Voter Features
- Secure login with Student ID and password
- One-vote-only enforcement per position
- Clear candidate display with photos, names, parties, and sections
- Vote confirmation before submission
- Non-traceable vote receipt with reference code
- Automatic logout after voting
- Results viewing after voting or election closure

### Admin Features
- Secure admin login with hashed passwords
- Dashboard with real-time statistics
- Election control (open/close/reset)
- Candidate management
- Voter management with CSV import support
- Audit log for all admin actions
- Real-time results with charts

### Security Features
- Password hashing using bcrypt
- Prepared statements to prevent SQL injection
- CSRF protection on all forms
- Input validation and sanitization
- Role-based access control (RBAC)
- Session timeout and logout protection
- Anonymous voting with vote integrity hashing
- Audit logging for admin actions

## System Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx) or PHP built-in server
- Chrome browser (optimized for Chromebook R11)

## Installation

1. **Clone or download the project files** to your web server directory.

2. **Set up the database:**
   - Create a MySQL database named `sslg_voting`
   - Import the schema from `sql/schema.sql`
   - Update database credentials in `includes/config.php`

3. **Configure the application:**
   - Edit `includes/config.php` with your database credentials
   - Ensure the `assets/images/` directory is writable for candidate photos

4. **Access the application:**
   - Open `login.php` in your browser
   - Default admin credentials: Student ID: `ADMIN001`, Password: `admin123`

## Project Structure

```
sslg-voting-system/
├── includes/
│   └── config.php          # Configuration and security functions
├── sql/
│   └── schema.sql          # Database schema and sample data
├── admin/
│   └── dashboard.php       # Admin dashboard
├── assets/
│   ├── css/
│   │   └── style.css       # Main stylesheet
│   ├── js/
│   │   └── script.js       # Client-side JavaScript
│   └── images/             # Candidate photos
├── login.php               # Login page
├── vote.php                # Voting interface
├── results.php             # Election results
├── logout.php              # Logout handler
└── README.md               # This file
```

## Database Schema

### Tables
- `users`: Voter and admin accounts
- `candidates`: Election candidates
- `votes`: Anonymous vote records
- `election_settings`: Election configuration
- `audit_log`: Admin action logging

### Key Security Features
- Votes are stored anonymously with voter_id but not directly linked to voter identity
- Each vote has a SHA256 hash for integrity verification
- Unique constraints prevent multiple votes per position
- Audit log tracks all admin actions with timestamps and IP addresses

## Usage

### For Voters
1. Log in with Student ID and password
2. Review candidates for each position
3. Select one candidate per position
4. Confirm and submit your vote
5. Receive a receipt code (keep for reference)
6. View results after voting

### For Admins
1. Log in with admin credentials
2. Monitor election statistics on the dashboard
3. Open/close the election as needed
4. Manage candidates and voters
5. View audit logs and results

## Security Explanations

### Password Security
- Passwords are hashed using `password_hash()` with bcrypt algorithm
- No plain-text passwords stored in database

### SQL Injection Prevention
- All database queries use prepared statements with PDO
- User input is parameterized, not concatenated into SQL strings

### CSRF Protection
- All forms include a unique CSRF token generated per session
- Tokens are validated on form submission

### Session Security
- Sessions timeout after 30 minutes of inactivity
- Secure session handling prevents fixation attacks

### Vote Anonymity
- Votes are stored separately from voter personal information
- Receipt codes are randomly generated and not linked to voter identity
- Vote integrity ensured through cryptographic hashing

### Access Control
- Role-based permissions restrict access to admin functions
- Voters cannot access admin areas, admins cannot vote

## Development Notes

- Optimized for Chromebook R11 with responsive Bootstrap UI
- Uses Chart.js for result visualization
- Lightweight and fast-loading for limited hardware resources
- Compatible with PHP built-in server for testing

## License

This project is provided as-is for educational purposes. Modify and distribute according to your needs.

## Support

For issues or questions, please check the code comments and configuration files for guidance.
