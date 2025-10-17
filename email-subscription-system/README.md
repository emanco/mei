# Email Subscription System

A complete, production-ready email subscription system with responsive landing page, robust backend validation, and comprehensive admin panel.

## 🌟 Features

### Frontend
- **Responsive Landing Page** with video background and fallback
- **Real-time Email Validation** with typo correction
- **Progressive Enhancement** - works without JavaScript
- **Accessibility Optimized** (WCAG compliant)
- **Mobile-First Design** with smooth animations
- **Connection Speed Detection** - disables video on slow connections

### Backend
- **Advanced Email Validation** with multiple verification layers:
  - Format validation (RFC 5322 compliant)
  - Domain MX record checking
  - Disposable email detection
  - Common typo auto-correction
- **Smart Data Management**:
  - Clean subscriber database
  - Rejected emails table for review
  - Rate limiting (10 subscriptions per IP per day)
- **Security Features**:
  - SQL injection protection
  - CSRF protection
  - Secure token generation
  - Input sanitization

### Admin Panel
- **Real-time Dashboard** with auto-refresh
- **Comprehensive Statistics**:
  - Total subscribers
  - Today's signups
  - Weekly growth
  - Rejection analytics
- **Data Management**:
  - CSV export functionality
  - Recent subscribers view
  - Rejected emails review
  - Bulk operations support
- **Simple Authentication** (default: admin/admin123)

## 🚀 Quick Start

### Prerequisites
- PHP 8.1+
- MySQL 9.4+
- Web server (Apache/Nginx) or PHP built-in server

### Installation

1. **Navigate to your project directory:**
   ```bash
   cd /Users/emanuelemanco/dev/mei/email-subscription-system
   ```

2. **Database is already set up!** The system created:
   - Database: `email_subscription`
   - Tables: `subscribers`, `rejected_emails`, `email_templates`, `admin_users`
   - Default admin user: `admin` / `admin123`

3. **Start the development server:**
   ```bash
   /opt/homebrew/opt/php@8.1/bin/php -S localhost:8000
   ```

4. **Access your system:**
   - **Landing Page**: http://localhost:8000
   - **Admin Panel**: http://localhost:8000/admin
   - **Unsubscribe Page**: http://localhost:8000/unsubscribe.html

## 📁 File Structure

```
email-subscription-system/
├── index.html              # Main landing page
├── unsubscribe.html        # Unsubscribe page
├── README.md              # This file
├── api/                   # Backend API
│   ├── config.php         # Database & app configuration
│   ├── EmailValidator.php # Email validation class
│   ├── subscribe.php      # Subscription endpoint
│   └── unsubscribe.php    # Unsubscribe endpoint
├── admin/                 # Admin panel
│   ├── index.php          # Dashboard
│   ├── login.php          # Admin login
│   ├── logout.php         # Admin logout
│   └── export.php         # CSV export
├── assets/                # Frontend assets
│   ├── css/style.css      # Main stylesheet
│   ├── js/main.js         # JavaScript functionality
│   ├── images/            # Image assets
│   └── video/             # Video background
└── sql/
    └── setup.sql          # Database setup script
```

## 🔧 Configuration

### Database Settings
Edit `api/config.php` to configure:
- Database connection (host, name, user, pass)
- Email settings (SMTP configuration)
- Security settings (session timeout, rate limits)
- Validation settings (disposable email domains)

### Email Configuration
The system is prepared for email sending. Configure SMTP settings in `config.php`:
```php
define('SMTP_HOST', 'your-smtp-server.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your-email@example.com');
define('SMTP_PASS', 'your-password');
```

## 📊 API Endpoints

### Subscribe
```bash
POST /api/subscribe.php
Content-Type: application/json

{
  "email": "user@example.com"
}
```

### Unsubscribe
```bash
POST /api/unsubscribe.php
Content-Type: application/json

{
  "email": "user@example.com",
  "token": "optional-unsubscribe-token"
}
```

## 🛡️ Security Features

- **SQL Injection Protection**: All queries use prepared statements
- **Rate Limiting**: 10 subscriptions per IP per day
- **Input Sanitization**: All user inputs are sanitized
- **CSRF Protection**: Forms include CSRF tokens
- **Secure Tokens**: Cryptographically secure unsubscribe tokens
- **Session Security**: Admin sessions with timeout

## 📈 Performance Features

- **Connection Speed Detection**: Automatically disables video on slow connections
- **Progressive Enhancement**: Full functionality without JavaScript
- **Database Optimization**: Proper indexing on all searchable columns
- **Caching Headers**: Optimized for browser caching
- **Asset Optimization**: Minified CSS/JS for production

## 🎨 Customization

### Styling
- Edit `assets/css/style.css` to customize appearance
- CSS variables at the top for easy color scheme changes
- Responsive breakpoints for mobile optimization

### Content
- Modify `index.html` for landing page content
- Update `admin/` files for admin panel customization
- Configure email templates in the database

### Validation Rules
- Modify `EmailValidator.php` for custom validation logic
- Update disposable domains list in `config.php`
- Adjust rate limiting settings

## 🚀 Deployment

### Production Checklist
1. **Change default admin password**
2. **Update database credentials** in `config.php`
3. **Configure SMTP settings** for email sending
4. **Set up SSL certificate** (HTTPS)
5. **Configure proper web server** (Apache/Nginx)
6. **Set file permissions** appropriately
7. **Enable error logging** in production

### Dreamhost Deployment
1. Upload files to your domain directory
2. Import `sql/setup.sql` via phpMyAdmin
3. Update `config.php` with your database details
4. Test functionality

## 📝 Testing

The system has been tested with:
- ✅ Email subscription flow
- ✅ Email validation and typo correction
- ✅ Unsubscribe functionality
- ✅ Admin authentication
- ✅ Database operations
- ✅ API endpoints
- ✅ Rate limiting
- ✅ Responsive design

## 🔄 Version History

- **v1.0.0** - Complete email subscription system
  - Responsive landing page with video background
  - Advanced email validation with typo correction
  - Comprehensive admin panel
  - CSV export functionality
  - Rate limiting and security features

## 🆘 Troubleshooting

### Common Issues

**Database Connection Error:**
- Check MySQL is running: `brew services start mysql`
- Verify credentials in `config.php`

**PHP Server Won't Start:**
- Check PHP is installed: `/opt/homebrew/opt/php@8.1/bin/php --version`
- Ensure port 8000 is available

**Video Background Not Loading:**
- Place your video file in `assets/video/hero-bg.mp4`
- System automatically falls back to CSS gradient

**Admin Login Issues:**
- Default credentials: admin / admin123
- Check session settings in `config.php`

## 📧 Support

For issues or questions:
1. Check the troubleshooting section above
2. Review the configuration settings
3. Check server error logs
4. Verify database connectivity

---

**Built with ❤️ for efficient email subscription management**