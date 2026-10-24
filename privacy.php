<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy - Webchat</title>
    <meta name="description" content="Privacy Policy for Webchat. Learn how we handle and protect your personal information and messages.">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        .bg-gradient-custom { background: linear-gradient(135deg, #111827 0%, #0f172a 100%); }
    </style>
</head>
<body class="bg-gradient-custom text-gray-200 min-h-screen py-10 px-4 sm:px-6 lg:px-8">
    <div class="max-w-4xl mx-auto bg-gray-800 rounded-2xl shadow-2xl border border-gray-700 overflow-hidden">
        
        <!-- Header -->
        <div class="bg-gray-900 border-b border-gray-700 px-6 py-8 sm:px-10 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-xl bg-blue-600 flex items-center justify-center text-white font-bold text-xl shadow-lg">
                    W
                </div>
                <div>
                    <h1 class="text-2xl font-bold text-white">Webchat</h1>
                    <p class="text-xs text-blue-400">Privacy-First Messaging</p>
                </div>
            </div>
            <div class="flex items-center space-x-4 text-sm font-medium">
                <a href="index.php" class="text-gray-300 hover:text-white transition-colors">Home</a>
                <a href="Login/login.php" class="text-blue-400 hover:text-blue-300 transition-colors">Sign In</a>
                <a href="Login/register.php" class="bg-blue-600 hover:bg-blue-500 text-white px-3 py-1.5 rounded-lg transition-colors">Register</a>
            </div>
        </div>

        <!-- Content Body -->
        <div class="p-6 sm:p-10 space-y-8 text-gray-300 leading-relaxed text-sm sm:text-base">
            <div>
                <h2 class="text-3xl font-extrabold text-white mb-2">Privacy Policy</h2>
                <p class="text-xs sm:text-sm text-gray-400">Last updated: October 2026</p>
            </div>

            <div class="p-4 rounded-xl bg-blue-950/40 border border-blue-800/50 text-blue-200 text-sm">
                <strong>Our Commitment:</strong> Webchat is engineered with privacy as a foundational principle. We do not sell your personal data, we do not track you across the internet, and messages are designed with automated retention limits.
            </div>

            <!-- Section 1 -->
            <section class="space-y-3">
                <h3 class="text-xl font-bold text-white flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-blue-500"></span> 1. Information We Collect
                </h3>
                <p>When you create an account and use Webchat, we collect only the minimum required information to provide the messaging service:</p>
                <ul class="list-disc list-inside space-y-1.5 pl-2 text-gray-300">
                    <li><strong class="text-white">Account Information:</strong> Display name, unique public ID (e.g., @username), email address, and date of birth (for age verification).</li>
                    <li><strong class="text-white">Authentication Credentials:</strong> Passwords are cryptographically hashed using secure one-way algorithms (bcrypt). We never store or transmit plaintext passwords.</li>
                    <li><strong class="text-white">Chat Data:</strong> Text messages, shared media (images, PDFs), and reaction metadata necessary to deliver conversations between users.</li>
                    <li><strong class="text-white">Technical & Session Information:</strong> IP address and user-agent string stored temporarily in our active sessions table to manage multi-device logins and protect accounts from unauthorized access.</li>
                </ul>
            </section>

            <!-- Section 2 -->
            <section class="space-y-3">
                <h3 class="text-xl font-bold text-white flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span> 2. How We Use Your Information
                </h3>
                <p>Your information is strictly used for the following operational purposes:</p>
                <ul class="list-disc list-inside space-y-1.5 pl-2 text-gray-300">
                    <li>To facilitate real-time 1-on-1 personal chats, group chats, and public channels.</li>
                    <li>To verify your email address upon registration and allow account password recovery.</li>
                    <li>To enforce device connection limits (up to 2 concurrent devices per account for security).</li>
                    <li>To prevent spam, abuse, and malicious activities on the platform.</li>
                </ul>
            </section>

            <!-- Section 3 -->
            <section class="space-y-3">
                <h3 class="text-xl font-bold text-white flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-purple-500"></span> 3. Message Retention & Auto-Deletion
                </h3>
                <p>Webchat supports automatic message lifecycle management. Direct messages are periodically purged according to our 24-hour self-destruct policy, minimizing data footprints and preserving user privacy.</p>
            </section>

            <!-- Section 4 -->
            <section class="space-y-3">
                <h3 class="text-xl font-bold text-white flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-yellow-500"></span> 4. Cookies & Session Storage
                </h3>
                <p>Webchat uses essential PHP session cookies solely to authenticate your browser session and keep you securely logged into your dashboard. We do not use third-party advertising cookies, trackers, or behavioral profiling tools.</p>
            </section>

            <!-- Section 5 -->
            <section class="space-y-3">
                <h3 class="text-xl font-bold text-white flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-red-500"></span> 5. Third-Party Disclosures
                </h3>
                <p>We do not sell, trade, or transfer your personal identification or chat data to outside parties. Transactional emails (such as verification codes and password reset requests) are securely relayed through our transactional email service provider (Brevo SMTP) strictly to ensure delivery.</p>
            </section>

            <!-- Section 6 -->
            <section class="space-y-3">
                <h3 class="text-xl font-bold text-white flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-blue-500"></span> 6. Security & Protection
                </h3>
                <p>We employ standard technical measures to safeguard your credentials and conversations, including salted password hashing, prepared SQL queries to prevent injection attacks, and strict role-based access controls on conversation memberships.</p>
            </section>

            <!-- Section 7 -->
            <section class="space-y-3">
                <h3 class="text-xl font-bold text-white flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span> 7. Contact Us & Terms
                </h3>
                <p>If you have any questions regarding this Privacy Policy or wish to request data removal, please contact the Webchat administrator or review our <a href="terms.php" class="text-blue-400 underline hover:text-blue-300">Terms of Service</a>.</p>
            </section>
        </div>

        <!-- Footer -->
        <div class="bg-gray-900 border-t border-gray-700 px-6 py-6 sm:px-10 flex flex-col sm:flex-row items-center justify-between text-xs text-gray-400 gap-4">
            <p>&copy; <?php echo date('Y'); ?> Webchat. All rights reserved.</p>
            <div class="flex items-center space-x-6">
                <a href="index.php" class="hover:text-gray-200 transition-colors">Home</a>
                <a href="terms.php" class="hover:text-gray-200 transition-colors">Terms of Service</a>
                <a href="updates.html" class="hover:text-gray-200 transition-colors">Updates</a>
            </div>
        </div>

    </div>
</body>
</html>
