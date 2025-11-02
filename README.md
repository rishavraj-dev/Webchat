# Webchat

Webchat is a PHP/MySQL messaging app for personal and group conversations. It supports OTP-verified registration, secure file access, media previews, group management, and a responsive UI.

---

## ✨ Features

- Personal and group chats
- File sharing with previews (images, PDFs, videos)
- Mobile-friendly layout
- Profile and avatar management
- OTP email verification for signup
- Password reset via email OTP
- Active device/session management

---

## 📂 Project structure (key files)

```
├── index.php
├── Conversations.php
├── manage_group.php
├── manage_personal_chats.php
├── settings.php
├── get_file.php
├── CSS/
│   ├── basicstyles.css
│   ├── dashboard.css
│   └── managegroup.css
├── Login/
│   ├── config.php                # DB connection and error reporting
│   ├── login.php / login_process.php
│   ├── register.php / register_process.php
│   ├── verify.php / verify_handler.php  # OTP verification UI + API
│   ├── forgot_password.php / forgot_password_handler.php
│   └── logout.php
├── PHPMailer/                    # PHPMailer library (bundled)
├── uploads/                      # User-uploaded files
└── README.md
```

---

## 🗄️ Database requirements

Minimum tables used by this app include (names can vary in your install):

- users: id, username, public_id, email, password_hash, dob, verified ('yes'|'no')
- conversations, conversation_members, messages (and related attachments if used)
- email_verifications: stores OTP tokens for both signup and password reset
- active_sessions: optional, for device/session limits

SQL snippets for new/updated tables used by the OTP flow:

```sql
-- One-time password storage for verification and password reset
CREATE TABLE IF NOT EXISTS email_verifications (
   id INT AUTO_INCREMENT PRIMARY KEY,
   user_email VARCHAR(255) NOT NULL,
   token VARCHAR(20) NOT NULL,
   expires_at DATETIME NOT NULL,
   INDEX (user_email),
   INDEX (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ensure users table has a verified flag
ALTER TABLE users
   ADD COLUMN IF NOT EXISTS verified ENUM('yes','no') NOT NULL DEFAULT 'no';
```

Note: The rest of the schema (users/conversations/messages) should already exist in your DB. If not, create those per your needs.

---

## ⚙️ Configuration

1) Database

- Edit `Login/config.php` and set your DB host, name, user, and password.
- Error reporting is enabled in dev by default in this file.

2) SMTP (email)

- This project intentionally keeps SMTP settings close to the pages that send mail (per-page config).
- Update the following files with your SMTP username, password, and sender address:
   - `Login/register_process.php`
   - `Login/verify_handler.php`
   - `Login/forgot_password_handler.php`
- Typical SMTP settings (example: Brevo):
   - Host: smtp-relay.brevo.com
   - Port: 587
   - Security: STARTTLS
   - Auth: required

Important: Do not commit real SMTP credentials. Keep them only in your local working copy.

---

## 🔐 Registration and verification (OTP)

- User submits the signup form (`Login/register.php`).
- `register_process.php` validates input, stages the registration data in the session, generates a 6-digit OTP, stores it in `email_verifications`, and emails it.
- User is redirected to `Login/verify.php` to enter the OTP.
- `verify_handler.php` endpoints:
   - `action=send_otp` – re-send an OTP to the account email
   - `action=verify_otp` – verifies the OTP: if the user doesn’t exist yet, it creates the user from staged data and marks `verified='yes'`
   - `action=resend_otp` – rate-limited resend

If verification is skipped, login is blocked until `verified='yes'`.

---

## � Password reset (OTP)

- `forgot_password.php` UI + `forgot_password_handler.php` API
- Flow:
   - `action=send_otp` – send a 6-digit OTP to the account email (by email or public_id)
   - `action=reset_password` – verify OTP and set a new password; used OTP is deleted

---

## � File handling

- File links are URL-encoded to support spaces and special characters.
- `get_file.php` verifies conversation membership (or public access) before serving a file and sends correct headers.

---

## 🚀 Run locally

1) Install PHP 8+ and MySQL/MariaDB
2) Create the database and import/adjust the tables listed above
3) Configure `Login/config.php` and SMTP settings in the mailer files listed earlier
4) Serve the app via your local web server (XAMPP/WAMP/IIS) and open `index.php`

Optional (built-in PHP server):

```sh
php -S localhost:8080
```

Then browse to http://localhost:8080/

---

## 🛡️ Security notes

- Don’t commit real SMTP credentials or DB passwords
- OTP codes expire in 10 minutes; re-sends are rate-limited
- Passwords are hashed with PHP’s `password_hash`
- File access is permission-checked; paths are sanitized

---

## 📜 Changelog

- Added OTP-based registration with deferred user creation until verify
- Added password reset via OTP
- Fixed file URL handling for names with spaces
- Mobile UI tweaks for message input bar and footer

See `updates.html` for more details.

---

## 🤝 Contributing

Issues and PRs are welcome. Please avoid submitting secrets and include reproducible steps.

---

## 📄 License

MIT License

---

## 📬 Contact

Open an issue for support or feature requests.
