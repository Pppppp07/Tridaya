@echo off
REM ================================================================
REM  Monitoring Tindak Lanjut Hasil Pemeriksaan (SIMTLHP)
REM
REM  Klik dua kali berkas ini. Peramban akan terbuka sendiri.
REM  Untuk berhenti: tekan Ctrl+C di jendela ini, atau tutup saja.
REM ================================================================
setlocal enabledelayedexpansion
cd /d "%~dp0"
title Monitoring TLHP - jangan tutup jendela ini selama dipakai

REM ---- cari php.exe ----------------------------------------------
set "PHP="
for /f "delims=" %%D in ('dir /b /ad "C:\laragon\bin\php\php-8*" 2^>nul') do (
  if exist "C:\laragon\bin\php\%%D\php.exe" set "PHP=C:\laragon\bin\php\%%D\php.exe"
)
if not defined PHP if exist "C:\xampp\php\php.exe" set "PHP=C:\xampp\php\php.exe"
if not defined PHP for /f "delims=" %%P in ('where php 2^>nul') do set "PHP=%%P"

if not defined PHP (
  echo.
  echo   PHP tidak ditemukan di komputer ini.
  echo   Pasang Laragon atau XAMPP lebih dulu, lalu jalankan lagi berkas ini.
  echo.
  pause
  exit /b 1
)

REM ---- siapkan basis data kalau belum ada -------------------------
if not exist "database\database.sqlite" (
  echo   Menyiapkan basis data untuk pertama kali, mohon tunggu...
  type nul > "database\database.sqlite"
  "%PHP%" artisan migrate --force
  "%PHP%" artisan db:seed --force
  echo.
)

cls
echo.
echo   ==============================================================
echo     MONITORING TINDAK LANJUT HASIL PEMERIKSAAN
echo     Sekretariat Badan - LHA Inspektorat dan LHP BPK
echo   ==============================================================
echo.
echo     Alamat:  http://localhost:8000
echo.
echo     Akun contoh, semuanya bersandi:  rahasia123
echo.
echo       setba@contoh.test          Sekretariat Badan
echo       bandung@contoh.test        Balai Bandung
echo       makassar@contoh.test       Balai Makassar
echo       uki@contoh.test            Unit Kepatuhan Intern
echo       inspektorat@contoh.test    Inspektorat Jenderal
echo       pimpinan@contoh.test       Pimpinan - hanya melihat
echo.
echo     Hentikan dengan Ctrl+C, atau tutup jendela ini.
echo     Jendela kecil "pengirim email" ikut berjalan; tutup juga.
echo   ==============================================================
echo.

REM Pengirim email (antrean): jendela kecil terpisah, tutup juga saat selesai.
start "Tridaya - pengirim email" /min "%PHP%" artisan queue:work --queue=email,default --sleep=3 --max-time=3600
start "" /min cmd /c "timeout /t 3 /nobreak >nul & explorer http://localhost:8000"
"%PHP%" artisan serve --host=127.0.0.1 --port=8000

echo.
echo   Server berhenti.
pause
