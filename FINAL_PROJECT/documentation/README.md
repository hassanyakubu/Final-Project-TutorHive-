# TutorHive - Online Tutoring Platform

TutorHive is a web-based tutoring platform that connects students with tutors, facilitating online learning through scheduled sessions and progress tracking.

## 🌟 Features

- **User Authentication**
  - Secure login and registration
  - Role-based access (Student, Tutor, Admin)
  - Profile management with picture uploads

- **Session Management**
  - Book tutoring sessions
  - Track session status
  - View session history

- **Progress Tracking**
  - Monitor learning progress
  - View completed sessions
  - Track course progress

- **Admin Dashboard**
  - User management
  - Session oversight
  - System monitoring

## 🚀 Quick Start

### Prerequisites
- XAMPP (or equivalent with PHP 8.x and MySQL)
- Web browser
- Internet connection

### Installation

1. Clone the repository to your XAMPP htdocs folder:
```bash
cd /Applications/XAMPP/xamppfiles/htdocs
git clone https://github.com/hassanyakubu/Final-Project-TutorHive- FINAL_PROJECT
```

2. Create the database:
- Open phpMyAdmin
- Create a new database named 'tutorplatform_db'
- Import the database schema 

3. Configure database connection:
- Open `db/connect.php`
- Update database credentials if needed

4. Set up file permissions:
```bash
chmod 755 /Applications/XAMPP/xamppfiles/htdocs/FINAL_PROJECT
chmod 777 /Applications/XAMPP/xamppfiles/htdocs/FINAL_PROJECT/uploads
```

5. Access the application:
```
http://localhost/FINAL_PROJECT
```

## 🚀 Deployment

### Option 1: InfinityFree (Free)
1. Create an account at [InfinityFree](https://infinityfree.net)
2. Create a new hosting account
3. Upload files using File Manager or FTP:
   ```bash
   # If using FTP
   ftp ftpupload.net
   # Enter your username and password
   cd htdocs
   put -r * .
   ```
4. Create MySQL database in control panel
5. Import database:
   - Go to phpMyAdmin
   - Create new database
   - Import your SQL file
6. Update `db/connect.php`:
   ```php
   $host = 'YOUR_DB_HOST';
   $dbname = 'YOUR_DB_NAME';
   $username = 'YOUR_DB_USER';
   $password = 'YOUR_DB_PASSWORD';
   ```

### Option 2: 000WebHost (Free)
1. Sign up at [000WebHost](https://www.000webhost.com)
2. Create a new website
3. Upload files using File Manager:
   - Go to File Manager
   - Upload all files to public_html
4. Set up database:
   - Go to Database Manager
   - Create new database
   - Import your SQL file
5. Update database credentials in `db/connect.php`

### Option 3: Professional Hosting (Recommended for Production)
For a production environment, consider:
- [HostGator](https://www.hostgator.com)
- [Bluehost](https://www.bluehost.com)
- [SiteGround](https://www.siteground.com)

These provide:
- Better performance
- SSL certificates
- Professional support
- Regular backups
- Enhanced security

### Post-Deployment Checklist
1. Test all features
2. Verify database connections
3. Check file permissions
4. Ensure all links are working
5. Test user registration/login
6. Verify file uploads
7. Check email functionality

## 📁 Project Structure

```
FINAL_PROJECT/
├── actions/           # PHP action handlers
├── assets/           # Static assets (CSS, JS, images)
├── db/               # Database configuration
├── documentation/    # Project documentation
├── uploads/          # User uploads
└── views/            # PHP view files
```

## 🔒 Security Features

- CSRF Protection
- Password Hashing
- Input Sanitization
- Prepared SQL Statements
- File Upload Validation
- Session Security

## 👥 User Roles

### Student
- Register/Login
- Book tutoring sessions
- Track learning progress
- View session history

### Tutor
- Manage session requests
- Update availability
- Track teaching sessions
- Provide session feedback

### Admin
- Manage users
- Monitor sessions
- System administration
- Generate reports

## 💻 Technical Stack

- **Frontend**: HTML5, CSS3, JavaScript
- **Backend**: PHP 
- **Database**: MySQL
- **Server**: Apache (XAMPP)

## 📝 API Documentation

### Authentication Endpoints
- POST `/actions/login.php`: User login
- POST `/actions/register.php`: User registration
- GET `/actions/logout.php`: User logout

### Session Endpoints
- POST `/actions/book_session.php`: Book new session
- PUT `/actions/update_session_status.php`: Update session status
- GET `/views/track_progress.php`: View session progress


## 👨‍💻 Author

Hassan Yakubu
- GitHub: https://github.com/hassanyakubu
