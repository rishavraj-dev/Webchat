<?php
// We must start the session to access session variables
session_start();

// Check if the user is logged in by looking for your session variable (e.g., 'user_id')
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    // If they are logged in, redirect them to the conversations page
    header("Location: conversations.php");
    // It's crucial to exit the script after a redirect to prevent the rest of the page from loading
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<!-- The rest of your HTML code goes here -->
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Webchat – Secure, Modern Messaging</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .bg-gradient-custom { background: linear-gradient(135deg, #1f2937 0%, #0f172a 100%); }
    </style>
</head>
<body class="bg-gradient-custom min-h-screen flex items-center justify-center p-4">
    <div class="bg-gray-800 p-8 md:p-12 lg:p-16 rounded-xl shadow-2xl max-w-4xl w-full text-center transform hover:scale-105 transition-transform duration-300 ease-in-out">
        <!-- Main Title -->
        <h1 class="text-5xl md:text-6xl font-extrabold text-white mb-4 leading-tight">
            Webchat: Secure, Modern Messaging
        </h1>
        <p class="text-lg md:text-xl text-gray-200 mb-8 max-w-2xl mx-auto">
            Experience privacy-first chat with auto-deleting messages, rich media, and powerful group features.
        </p>

        <!-- Action Buttons -->
        <div class="flex flex-col sm:flex-row justify-center gap-4 mb-8">
            <a href="Login/register.php" class="inline-block bg-emerald-600 text-white px-8 py-4 rounded-lg font-semibold text-xl hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 focus:ring-offset-gray-800 transition-colors duration-300 ease-in-out shadow-lg">
                Register
            </a>
            <a href="Login/login.php" class="inline-block bg-blue-600 text-white px-8 py-4 rounded-lg font-semibold text-xl hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-gray-800 transition-colors duration-300 ease-in-out shadow-lg">
                Login
            </a>
            <a href="updates.html" class="inline-block bg-gray-700 text-gray-200 px-8 py-4 rounded-lg font-semibold text-xl hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 focus:ring-offset-gray-800 transition-colors duration-300 ease-in-out shadow-lg">
                Update History
            </a>
        </div>

        <!-- Latest Major Update Highlight -->
        <div class="bg-green-700 p-6 rounded-lg shadow-md border-2 border-green-500 text-white mb-10 max-w-2xl mx-auto text-left">
            <h2 class="text-2xl font-bold mb-3 text-center">🎉 Latest Webchat Enhancements! (Version 2.x Series) 🎉</h2>
            <ul class="list-disc list-inside space-y-2">
                <li><b>Expanded Media:</b> Share images and PDFs directly in chats.</li>
                <li><b>Advanced Group Features:</b> Create and manage private & public groups and channels with full management tools.</li>
                <li><b>Enhanced Messaging:</b> Enjoy easy replies, one-click forwarding, and improved context menus.</li>
                <li><b>Personalized Experience:</b> Set profile pictures and manage devices from your dashboard.</li>
                <li><b>Robust Security:</b> Files are securely protected from unauthorized access.</li>
                <li><b>UI Refinements:</b> Smoother navigation with improved Hamburger menu and more robust search.</li>
                <li><b>Channel Management:</b> Create and join channels.</li>
            </ul>
            <p class="mt-4 text-center text-green-100">
                See all details in the <a href="updates.html" class="underline font-semibold hover:text-white">Update History</a>.
            </p>
        </div>

        <!-- Feature Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-10">
            <div class="bg-gray-700 p-6 rounded-lg shadow-md flex flex-col items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 text-blue-400 mb-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <h3 class="text-2xl font-bold text-white mb-2">Privacy-Focused</h3>
                <p class="text-gray-300">Chats are always secure and private. No unauthorized access.</p>
            </div>
            <div class="bg-gray-700 p-6 rounded-lg shadow-md flex flex-col items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 text-purple-400 mb-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <h3 class="text-2xl font-bold text-white mb-2">Self-Destructing Chats</h3>
                <p class="text-gray-300">Messages auto-delete after 24 hours.</p>
            </div>
            <div class="bg-gray-700 p-6 rounded-lg shadow-md flex flex-col items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 text-emerald-400 mb-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H16.5m-13.5 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0018.75 4.5H5.25A2.25 2.25 0 003 6.75v10.5A2.25 2.25 0 005.25 19.5z" />
                </svg>
                <h3 class="text-2xl font-bold text-white mb-2">Personal Chats</h3>
                <p class="text-gray-300">One-on-one secure conversations with privacy at the core.</p>
            </div>
            <div class="bg-gray-700 p-6 rounded-lg shadow-md flex flex-col items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12 text-yellow-400 mb-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.455 3.633 2.513 8.25 2.513s8.25-1.058 8.25-2.513V6.75c0-1.456-3.633-2.513-8.25-2.513S3.75 5.294 3.75 6.75v6.76zM12 18.75c-4.617 0-8.25-1.058-8.25-2.513m8.25 2.513v-2.25V18.75z" />
                </svg>
                <h3 class="text-2xl font-bold text-white mb-2">Channels & Groups</h3>
                <p class="text-gray-300">Create or join public/private groups and channels. Full group management tools.</p>
            </div>
        </div>

        <!-- Core Features List -->
        <div class="mb-10">
            <h2 class="text-3xl md:text-4xl font-bold text-white mb-4">All Core Features</h2>
            <ul class="text-lg md:text-xl text-gray-200 space-y-3 max-w-lg mx-auto">
                <li class="flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-emerald-400 mr-3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H16.5m-13.5 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0018.75 4.5H5.25A2.25 2.25 0 003 6.75v10.5A2.25 2.25 0 005.25 19.5z" />
                    </svg>
                    <span>Personal Chats</span>
                </li>
                <li class="flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-emerald-400 mr-3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.455 3.633 2.513 8.25 2.513s8.25-1.058 8.25-2.513V6.75c0-1.456-3.633-2.513-8.25-2.513S3.75 5.294 3.75 6.75v6.76zM12 18.75c-4.617 0-8.25-1.058-8.25-2.513m8.25 2.513v-2.25V18.75z" />
                    </svg>
                    <span>Channels</span>
                </li>
                <li class="flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-emerald-400 mr-3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.75c-.276 0-.543-.01-.806-.027M15.75 18H12m3.75 0a3.375 3.375 0 003.375-3.375c0-.622-.115-1.224-.325-1.761M15.75 18H5.25c-.75 0-1.5-.02-2.22-.063M15.75 18V6.375c0-.621-.504-1.125-1.125-1.125h-3.879a1.125 1.125 0 01-.813-.375l-2.94-2.94M15.75 18c-1.35 0-2.67-.184-3.925-.516m0 0a9.07 9.07 0 01-3.925-.516m0 0a3.375 3.375 0 00-3.375-3.375c-.622 0-1.224.115-1.761.325M5.25 6.375V18m0-11.625a3.375 3.375 0 01-3.375-3.375C1.875 2.753 3.011 1.5 4.343 1.5c1.298 0 2.503.743 3.14 1.831A9.76 9.76 0 0112 2.25c2.184 0 4.242.434 6.134 1.206 1.087 1.347 1.845 2.92 2.164 4.075M18 10.5h.008v.008H18zm-3.75 2.25h.008v.008H14.25z" />
                    </svg>
                    <span>Groups (Private & Public)</span>
                </li>
                <li class="flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-emerald-400 mr-3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75L4.5 18h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5H4.5A2.25 2.25 0 002.25 6.75v8.25z" />
                    </svg>
                    <span>File Sharing (PDF, Images)</span>
                </li>
                <li class="flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-emerald-400 mr-3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                    <span>Profile Pictures & Customization</span>
                </li>
                <li class="flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6 text-emerald-400 mr-3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 8h2a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2v-8a2 2 0 012-2h2m10 0V6a4 4 0 10-8 0v2" />
                    </svg>
                    <span>Device Management & Dashboard</span>
                </li>
            </ul>
        </div>
    </div>
</body>
</html>
