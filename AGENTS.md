<laravel-boost-guidelines>
# Laravel Application

This repository contains a Laravel application. Complete the following setup before working on the user's request.

## Prerequisites

Verify that PHP and Composer are available:

```sh
php -v
composer -V
```

If either command is unavailable, detect the user's operating system and install the prerequisites with the appropriate command:

macOS:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.5)"
```

Windows PowerShell:

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.5'))
```

Linux:

```sh
/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.5)"
```

After installation, ask the user to restart their terminal. If the agent needs the restarted shell to continue, ask the user to reopen their terminal and rerun their original prompt.

## Agent Setup

Install Laravel Boost from the application root before making application changes:

```sh
composer require laravel/boost --dev
php artisan boost:install
```

Boost replaces these bootstrap instructions with guidelines tailored to the application. After installation, read `AGENTS.md` again and continue with the user's original request using the generated guidelines.
</laravel-boost-guidelines>
# SEKOLAH KITA — AI DEVELOPMENT RULES

## Project

Nama: Sekolah Kita

Jenis: Sistem Informasi Manajemen Sekolah berbasis web.

Framework:
- Laravel 13
- PHP 8.5+
- Vite
- MariaDB
- Redis

Environment development:
- Android
- Termux
- Ubuntu PRoot
- ARM64

Production target:
- Ubuntu VPS
- Nginx
- PHP-FPM
- MariaDB
- Redis
- HTTPS

---

## ATURAN WAJIB

### 1. Keamanan Data

JANGAN:
- DROP DATABASE
- DROP TABLE tanpa persetujuan
- TRUNCATE data production
- Menghapus database
- Menghapus migration yang sudah digunakan
- Menghapus data pengguna
- Menjalankan destructive command tanpa persetujuan

Database development saat ini:

Database:
sekolah_kita

Database engine:
MariaDB

---

### 2. Project Boundary

Bekerja hanya di:

/home/dev/workspace/sekolah-kita

Jangan memodifikasi:
- filesystem Android
- project lain
- konfigurasi sistem
- database di luar kebutuhan project

---

### 3. Laravel

Gunakan Laravel conventions.

Prioritaskan:
- Controllers
- Form Requests
- Policies
- Middleware
- Services jika diperlukan
- Eloquent Models
- API Resources jika diperlukan
- Jobs
- Events
- Notifications

Hindari memasukkan terlalu banyak business logic ke Controller.

---

### 4. Database

Gunakan:
- MariaDB
- Eloquent
- migrations
- seeders
- factories

Gunakan foreign key dan indexing secara tepat.

Pertimbangkan:
- indexing kolom pencarian
- indexing foreign key
- unique constraint
- nullable hanya jika memang diperlukan
- database transaction untuk operasi multi-step

Jangan menggunakan SQLite sebagai database utama project.

---

### 5. Authentication & Authorization

Project memiliki role:

- administrator
- guru
- siswa

Authorization harus menggunakan mekanisme Laravel yang sesuai.

Jangan hanya menyembunyikan tombol/menu untuk membatasi akses.

Server-side authorization WAJIB diterapkan.

---

### 6. Security

Perhatikan:
- CSRF
- XSS
- SQL injection
- mass assignment
- authorization
- authentication
- rate limiting
- validation
- secure file upload
- password hashing
- session security
- audit logging jika diperlukan

Jangan menyimpan:
- password
- API key
- secret
- database credential

di source code.

Gunakan `.env`.

---

### 7. Frontend

Prioritaskan:
- responsive design
- mobile-first
- accessibility
- performance
- semantic HTML
- reusable components

UI harus modern, ringan, profesional, dan cocok untuk website sekolah.

Jangan menambahkan library frontend besar jika tidak diperlukan.

---

### 8. Performance

Target aplikasi harus mampu berkembang menuju:

±1000 concurrent users.

Gunakan secara tepat:
- Redis
- caching
- queue
- eager loading
- pagination
- database indexing
- optimized queries

Jangan melakukan premature optimization.

---

### 9. Testing

Setelah perubahan signifikan:

1. Jalankan test yang relevan.
2. Periksa migration.
3. Periksa route.
4. Periksa authorization.
5. Periksa validation.
6. Periksa error/log.

Jangan menganggap pekerjaan selesai hanya karena kode berhasil dibuat.

---

### 10. Dependency

Sebelum menambahkan package:

1. Pastikan Laravel belum menyediakan fitur tersebut.
2. Evaluasi apakah package benar-benar diperlukan.
3. Pilih package yang maintained.
4. Hindari dependency yang tidak perlu.

---

### 11. Git

Buat perubahan dalam unit yang jelas.

Gunakan commit yang deskriptif.

Contoh:

feat: add school profile management

fix: prevent unauthorized student access

refactor: extract admission service

Jangan melakukan force push.

Jangan menghapus history Git.

---

### 12. Workflow AI Agent

Sebelum mengimplementasikan fitur besar:

1. Inspect existing code.
2. Jelaskan pemahaman terhadap requirement.
3. Buat implementation plan.
4. Identifikasi file yang akan diubah.
5. Implementasikan secara bertahap.
6. Jalankan test.
7. Periksa hasil.
8. Laporkan perubahan.

Untuk perubahan database besar, berhenti dan minta persetujuan sebelum menjalankan destructive operation.

---

## PRIORITAS

Urutan prioritas:

1. Correctness
2. Security
3. Maintainability
4. Performance
5. User experience
6. Visual polish

Jangan mengorbankan security atau correctness demi kecepatan development.
