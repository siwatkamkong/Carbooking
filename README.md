# 🚗 Vehicle Booking System (NT Internship Project)
A web-based Vehicle Booking System developed during my internship at National Telecom Public Company Limited (NT).
This system replaces traditional paper-based booking forms with a digital solution to reduce paper usage, printing costs, and manual processes.
## 📌 Overview

Previously, vehicle reservations were handled using paper forms that required printing and physical signatures. This project was created to modernize the workflow by providing a fully digital booking and approval system.

The system is currently in real-world use within the organization.

---

## ✨ Features
📝 Online Vehicle Booking Form

* Users can submit booking requests via web interface
* Automatically stores data in MySQL database

✍️ Digital Signature System

* Supports 8 operational positions (auto display signatures)
* Includes 3 admin approvers for vehicle approval
* Displays signatures dynamically based on user roles

👤 Driver employee code

* Search/filter driver by employee ID
* Automatically display driver name
  
📄 PDF Export

* Generate official booking documents in PDF format
* Compatible with existing NT form structure
  
🔍 Data Management

* View, edit, and export booking records
* Mileage tracking support
  
🌐 Multi-language Support (basic structure included)

---
## 🛠️ Tech Stack

* Backend: PHP
* Database: MySQL
* Database Tool: phpMyAdmin
* Frontend: HTML, CSS, JavaScript
* PDF Generator: TCPDF
* Server: Apache (XAMPP / LAMP)
---

## 📁 Project Structure
```text
├── datas/              # Database-related files
├── js/                 # JavaScript files
├── language/           # Language support
├── modules/            # Core modules
├── pdf-lib/            # TCPDF library
├── signaturePic/       # Signature images
├── settings/           # System configuration
├── index.php           # Main entry point
├── generate_pdf.php    # PDF generation
├── api.php             # API handling
└── README.md
 ``` 
---
## 🔐 User Roles

User
* Create booking requests
* View booking status

🚗 Driver
* Record mileage and trip details

🛠️ Admin
* Approve or reject booking requests
---
## 🖊️ Signature System

The system supports:
* 8 operational roles → auto display signatures
* 3 admin approvers → approval signatures

Signatures are stored as images in:
```text
/signaturePic/
 ```

Automatically rendered in generated PDF

---


## 👨‍💻 Developer

* Siwat Kamkong (Computer Engineering)
* Intern at National Telecom Public Company Limited (NT)


