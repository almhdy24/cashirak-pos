CASHIRAK POS - Installation & User Guide
=========================================

📦 SYSTEM REQUIREMENTS
- Windows 7/10/11, Linux, or macOS
- PHP 7.4+ with SQLite3 extension enabled
- A modern web browser (Chrome, Firefox, Edge)

🚀 QUICK START (WINDOWS)
------------------------
1. Download a portable PHP build for Windows from:
   https://windows.php.net/download#php-8.4
   (Choose "Non Thread Safe" Zip package)

2. Extract the PHP zip contents into the "php" folder located next to this file.

3. Double-click "start.bat" to launch the built-in PHP server.

4. Open a terminal (Command Prompt) in the project folder and run the installer:
   php install-cli.php

   This creates the database and default users automatically.

5. Open your browser and log in at:
   http://localhost:8000/login.php
   Default credentials:
      Admin   : admin / admin123
      Cashier : cashier / cashier123

🔧 ALTERNATIVE INSTALLATION (COMMAND LINE)
------------------------------------------
If you prefer the terminal or need to reset the database:

1. Open a terminal in the project folder.
2. Run: php install-cli.php
3. Start the server: php -S localhost:8000 -t public
4. Open http://localhost:8000/login.php

🔐 SECURITY NOTE
----------------
For production use, it is strongly recommended to:
- Change the default passwords immediately from the Admin Panel.
- Ensure the database file (database/cashirak.sqlite) is stored outside the public web root.
- Consider using a proper web server (Apache/Nginx) instead of the built-in PHP server.

📁 FOLDER STRUCTURE (brief)
---------------------------
cashirak-pos/
├── app/          # Backend logic (Models, Services, Core)
├── public/       # Web root (index, login, assets)
├── database/     # SQLite database file
├── storage/      # Session files and logs
├── views/        # UI partials (header, footer)
├── start.bat     # Windows launcher script
└── install-cli.php   # CLI installer

🌍 ACCESS FROM OTHER DEVICES (ON SAME NETWORK)
-----------------------------------------------
Run the server with:
php -S 0.0.0.0:8000 -t public
Then use your computer's IP address, e.g., http://192.168.1.10:8000

💡 TROUBLESHOOTING
------------------
- If you see "PDOException: could not find driver", enable the SQLite extension
  in php.ini (uncomment extension=pdo_sqlite and extension=sqlite3).
- If the browser shows a redirect loop, clear cookies or check the permissions
  for the cashier user (should have ["process_order"] in the users table).

📄 LICENSE & SUPPORT
--------------------
Cashirak POS is free and open-source software.
For issues or suggestions, visit the GitHub repository.

Thank you for choosing Cashirak POS!