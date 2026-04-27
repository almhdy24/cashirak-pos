# 🧾 Cashirak POS

## 💡 Simple Offline Cashier System

Cashirak POS is a fast and simple point-of-sale system designed for small restaurants, cafés, and shops.

It runs on **one device only** and does not require internet.

---

## 🎯 What this system does

- Take customer orders quickly
- Print and manage receipts
- Track daily sales
- Manage cashier shifts
- Organize menu items
- Cancel or edit orders when needed

---

## ⚡ Key Benefits

✔ Works without internet  
✔ Very fast and lightweight  
✔ Easy to use (no training needed)  
✔ Arabic-friendly interface  
✔ Built for single cashier usage  
✔ Works on any basic computer  

---

## 🖥️ How to Run

### 1. Start the system
Run the server:

php -S localhost:8000 -t public

---

### 2. Open in browser

http://localhost:8000

---

## 👤 Default Login

Username: admin  
Password: admin

---

## 🏪 Who is this for?

- Small restaurants  
- Cafés  
- Food kiosks  
- Small shops  
- Personal business use  

---

## 📌 Important Notes

- This system is designed for **one device only**
- No internet required
- All data is stored locally on the same machine

---

## 👨‍💻 Developer

Built by **Elmahdi Dev**  
GitHub: https://github.com/almhdy24

---

## 🚀 Status

✔ Ready to use  
✔ Stable version  
✔ Offline-first design   http://localhost:8000/login.php
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
