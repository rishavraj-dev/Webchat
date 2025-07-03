# Webchat

Webchat is a modern, privacy-focused messaging platform for personal and group conversations. It supports secure chat, file sharing, media previews, group management, and more—all with a clean, responsive interface.

---

## ✨ Features

- **Personal & Group Chats:** Secure one-on-one and group messaging.
- **File Sharing:** Send images, PDFs, and videos with previews.
- **Auto-Deleting Messages:** Messages can auto-delete after 24 hours for privacy.
- **Rich Media Support:** Inline previews for images, PDFs, and videos.
- **Group Management:** Create, join, and manage public/private groups and channels.
- **Profile Customization:** Upload avatars and manage your profile.
- **Dashboard:** View and manage your active devices and sessions.
- **Emoji Picker:** Express yourself with emoji support.
- **Responsive Design:** Works on desktop and mobile browsers.

---

## 📂 Project Structure

```
Web Chat/
├── index.html                # Landing page
├── Conversations.php         # Main chat logic (frontend & backend)
├── manage_group.php          # Group management UI
├── manage_personal_chats.php # Personal chat management UI
├── settings.php              # User settings/profile
├── Login/                    # Login & registration scripts
├── CSS/
│   ├── basicstyles.css       # Main styles
│   ├── managegroup.css       # Group management styles
│   └── dashboard.css         # Dashboard styles
├── updates.html              # Changelog & updates
└── README.md                 # This file
```

---

## 🗄️ Database Schema

Below is the database structure used by Webchat:

refer the images folder

---

## 🚀 Getting Started

1. **Clone the repository:**
   ```sh
   git clone https://github.com/yourusername/webchat.git
   cd webchat
   ```

2. **Backend Setup:**
   - Requires PHP and MySQL/MariaDB.
   - Configure database credentials in the PHP files as needed.
   - Import the database schema (not included here).

3. **Run Locally:**
   - Start a local PHP server or use XAMPP/WAMP.
   - Open `index.html` in your browser.

---

## 🛡️ Security & Privacy

- All messages are encrypted in transit.
- Files are protected from unauthorized access.
- Auto-delete for messages is available for privacy.

---

## 📜 Changelog

See [updates.html](updates.html) for the latest release notes and feature history.

---

## 🤝 Contributing

Pull requests and suggestions are welcome! Please open an issue or submit a PR.

---

## 📄 License

MIT License

---

## 📬 Contact

For support or feedback, open an issue on GitHub.

---

You can further customize this README with your project details or contribution guidelines.
